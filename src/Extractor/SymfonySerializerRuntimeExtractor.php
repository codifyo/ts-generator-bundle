<?php

namespace Codifyo\TsGeneratorBundle\Extractor;

use Codifyo\TsGeneratorBundle\Converter\PhpToTypeScriptTypeConverter;
use Codifyo\TsGeneratorBundle\Converter\TypeConverterInterface;
use Codifyo\TsGeneratorBundle\Model\PropertyDefinition;
use Codifyo\TsGeneratorBundle\Model\TypeConfig;
use Codifyo\TsGeneratorBundle\Model\TypeDefinition;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class SymfonySerializerRuntimeExtractor implements MetadataExtractorInterface
{
    public function __construct(
        private ?NormalizerInterface $normalizer,
        private ?PropertyInfoExtractorInterface $propertyInfoExtractor,
        private TypeConverterInterface $typeConverter,
        private ?ClassMetadataFactoryInterface $classMetadataFactory = null
    ) {
    }

    public function extract(TypeConfig $typeConfig): TypeDefinition
    {
        $className = $typeConfig->getClass();
        $targetGroups = $typeConfig->getGroups();
        $typeDefinition = new TypeDefinition($typeConfig->getName(), $className, [], [], $typeConfig->getKind());

        if (!class_exists($className)) {
            return $typeDefinition;
        }

        $context = [];
        if (!empty($targetGroups)) {
            $context['groups'] = $targetGroups;
        }

        $reflection = new \ReflectionClass($className);
        $typeAliases = $this->parseClassTypeAliases($reflection);
        if ($this->typeConverter instanceof PhpToTypeScriptTypeConverter) {
            $this->typeConverter->setTypeAliases($typeAliases);
        }

        $normalized = [];

        // 1. Try Runtime Normalization if normalizer is available
        if ($this->normalizer !== null) {
            try {
                $instance = $reflection->newInstanceWithoutConstructor();
                $normRes = $this->normalizer->normalize($instance, null, $context);
                if (is_array($normRes)) {
                    $normalized = $normRes;
                }
            } catch (\Throwable) {
                // Fallback
            }
        }

        // 2. Combine attributes from ClassMetadataFactory (for custom getters with #[Groups] without properties)
        if ($this->classMetadataFactory !== null && $this->classMetadataFactory->hasMetadataFor($className)) {
            $classMetadata = $this->classMetadataFactory->getMetadataFor($className);
            foreach ($classMetadata->getAttributesMetadata() as $attrMeta) {
                $propGroups = $attrMeta->getGroups();
                if (!empty($targetGroups) && empty(array_intersect($targetGroups, $propGroups))) {
                    continue;
                }
                $serializedName = $attrMeta->getSerializedName() ?? $attrMeta->getName();
                if (!array_key_exists($serializedName, $normalized)) {
                    $normalized[$serializedName] = null;
                }
            }
        }

        if (empty($normalized)) {
            return $typeDefinition;
        }

        foreach ($normalized as $serializedName => $normalizedValue) {
            $tsTypes = [];
            $isNullable = false;
            $referencedClass = null;

            $propertyCandidate = $this->resolvePropertyName($reflection, (string)$serializedName);

            // 0. Check Doctrine ORM Relation Attributes (ManyToMany, OneToMany, ManyToOne, OneToOne)
            if ($propertyCandidate !== null && $reflection->hasProperty($propertyCandidate)) {
                $refProp = $reflection->getProperty($propertyCandidate);
                $doctrineRel = $this->resolveDoctrineRelation($refProp);
                if ($doctrineRel !== null) {
                    $tsTypes[] = $doctrineRel['tsType'];
                    $referencedClass = $doctrineRel['targetEntity'];
                }
            }

            // 1. Check PropertyInfo
            if (empty($tsTypes) && $propertyCandidate !== null && $this->propertyInfoExtractor !== null) {
                $types = $this->propertyInfoExtractor->getTypes($className, $propertyCandidate);
                if ($types !== null && !empty($types)) {
                    foreach ($types as $type) {
                        if ($type->isNullable()) {
                            $isNullable = true;
                        }
                        $tsTypes[] = $this->typeConverter->convertType($type);
                        $objClassName = $type->getClassName();
                        if ($objClassName !== null && !is_a($objClassName, \DateTimeInterface::class, true)) {
                            $referencedClass = $objClassName;
                        } elseif ($type->isCollection()) {
                            foreach ($type->getCollectionValueTypes() as $valType) {
                                $colClass = $valType->getClassName();
                                if ($colClass !== null && !is_a($colClass, \DateTimeInterface::class, true)) {
                                    $referencedClass = $colClass;
                                }
                            }
                        }
                    }
                }
            }

            // 2. Check Dynamic Getter / Reflection Return Type / PHPDoc
            if (empty($tsTypes)) {
                $getterMethod = $this->resolveGetterMethod($reflection, (string)$serializedName);
                if ($getterMethod !== null) {
                    $returnType = $getterMethod->getReturnType();
                    if ($returnType !== null) {
                        if ($returnType->allowsNull()) {
                            $isNullable = true;
                        }
                        if ($returnType instanceof \ReflectionNamedType) {
                            $tsTypes[] = $this->typeConverter->convertType($returnType->getName());
                            if (!$returnType->isBuiltin()) {
                                $classRef = $returnType->getName();
                                if (!is_a($classRef, \DateTimeInterface::class, true)) {
                                    $referencedClass = $classRef;
                                }
                            }
                        }
                    }

                    // Check PHPDoc @return
                    $docComment = $getterMethod->getDocComment();
                    if ($docComment !== false) {
                        $docTypeStr = $this->extractDocType($docComment, '@return');
                        if ($docTypeStr !== null && $docTypeStr !== '') {
                            $docTypes = explode('|', $docTypeStr);
                            foreach ($docTypes as $dt) {
                                $dt = trim($dt);
                                if (strtolower($dt) === 'null') {
                                    $isNullable = true;
                                    continue;
                                }
                                $convertedDocType = $this->typeConverter->convertType($dt);
                                $tsTypes[] = $convertedDocType;

                                $extractedRef = $this->extractReferencedClass($dt, $reflection->getNamespaceName());
                                if ($extractedRef !== null) {
                                    $referencedClass = $extractedRef;
                                }
                            }
                        }
                    }
                }
            }

            // 3. Check Reflection Property PHPDoc if not getter
            if (empty($tsTypes) && $propertyCandidate !== null && $reflection->hasProperty($propertyCandidate)) {
                $refProp = $reflection->getProperty($propertyCandidate);
                $refType = $refProp->getType();
                if ($refType !== null) {
                    if ($refType->allowsNull()) {
                        $isNullable = true;
                    }
                    if ($refType instanceof \ReflectionNamedType) {
                        $tsTypes[] = $this->typeConverter->convertType($refType->getName());
                        if (!$refType->isBuiltin()) {
                            $colClass = $refType->getName();
                            if (!is_a($colClass, \DateTimeInterface::class, true)) {
                                $referencedClass = $colClass;
                            }
                        }
                    }
                }

                $docComment = $refProp->getDocComment();
                if ($docComment !== false) {
                    $docTypeStr = $this->extractDocType($docComment, '@var');
                    if ($docTypeStr !== null && $docTypeStr !== '') {
                        $docTypes = explode('|', $docTypeStr);
                        foreach ($docTypes as $dt) {
                            $dt = trim($dt);
                            if (strtolower($dt) === 'null') {
                                $isNullable = true;
                                continue;
                            }
                            $convertedDocType = $this->typeConverter->convertType($dt);
                            $tsTypes[] = $convertedDocType;

                            $extractedRef = $this->extractReferencedClass($dt, $reflection->getNamespaceName());
                            if ($extractedRef !== null) {
                                $referencedClass = $extractedRef;
                            }
                        }
                    }
                }
            }

            // 4. Check Runtime Normalized Value Type
            if (empty($tsTypes) && $normalizedValue !== null) {
                if (is_int($normalizedValue) || is_float($normalizedValue)) {
                    $tsTypes[] = 'number';
                } elseif (is_string($normalizedValue)) {
                    $tsTypes[] = 'string';
                } elseif (is_bool($normalizedValue)) {
                    $tsTypes[] = 'boolean';
                } elseif (is_array($normalizedValue)) {
                    $tsTypes[] = array_is_list($normalizedValue) ? 'any[]' : 'Record<string, any>';
                }
            }

            $uniqueTsTypes = array_values(array_unique($tsTypes));

            // Filter out redundant 'any[]', 'any', 'Collection' if specific types exist
            $hasSpecificType = false;
            foreach ($uniqueTsTypes as $t) {
                if ($t !== 'any' && $t !== 'any[]' && $t !== 'Collection') {
                    $hasSpecificType = true;
                    break;
                }
            }

            if ($hasSpecificType) {
                $uniqueTsTypes = array_values(array_filter($uniqueTsTypes, fn($t) => $t !== 'any' && $t !== 'any[]' && $t !== 'Collection'));
            }

            $finalTsType = !empty($uniqueTsTypes) ? implode(' | ', $uniqueTsTypes) : 'any';

            $propDef = new PropertyDefinition(
                (string)$serializedName,
                $finalTsType,
                $isNullable,
                $referencedClass
            );

            $typeDefinition->addProperty($propDef);

            if ($referencedClass !== null) {
                $typeDefinition->addImport(
                    $this->typeConverter->getShortClassName($referencedClass),
                    $referencedClass
                );
            }
        }

        return $typeDefinition;
    }

    private function extractReferencedClass(string $typeStr, string $currentNamespace): ?string
    {
        $typeStr = trim(ltrim($typeStr, '\\'));

        // Handle generic Collection<int, EntityB> or array<EntityB> or Collection<EntityB>
        if (preg_match('/^(?:array|collection|iterable|doctrine\\\\common\\\\collections\\\\collection)<(?:[^,>]+,\s*)?\s*([^>]+)\s*>$/i', $typeStr, $matches)) {
            return $this->extractReferencedClass($matches[1], $currentNamespace);
        }

        // Handle Type[]
        if (preg_match('/^([^\s\[\]]+)\[\]$/i', $typeStr, $matches)) {
            return $this->extractReferencedClass($matches[1], $currentNamespace);
        }

        $candidate = $typeStr;
        if (in_array(strtolower($candidate), ['int', 'integer', 'float', 'double', 'string', 'bool', 'boolean', 'array', 'iterable', 'mixed', 'void', 'null', 'object', 'any'], true)) {
            return null;
        }

        if (!class_exists($candidate)) {
            $nsCandidate = $currentNamespace . '\\' . $candidate;
            if (class_exists($nsCandidate)) {
                $candidate = $nsCandidate;
            }
        }

        if (class_exists($candidate) && !is_a($candidate, \DateTimeInterface::class, true)) {
            return $candidate;
        }

        return null;
    }

    private function parseClassTypeAliases(\ReflectionClass $reflection): array
    {
        $aliases = [];
        $doc = $reflection->getDocComment();
        if ($doc !== false) {
            if (preg_match_all('/@(?:phpstan|psalm)-type\s+([A-Za-z0-9_]+)\s*=?\s*(.+?)(?:\s+\*\/|\s*[\r\n]|$)/m', $doc, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $aliasName = trim($match[1]);
                    $definition = trim($match[2]);
                    $aliases[$aliasName] = $definition;
                }
            }
        }

        return $aliases;
    }

    private function extractDocType(string $docComment, string $tag): ?string
    {
        if (preg_match('/' . preg_quote($tag, '/') . '\s+(.+?)(?:\s+\$|\s+\*\/|\s*[\r\n]|$)/m', $docComment, $matches)) {
            $raw = trim($matches[1]);
            if (preg_match('/^([^\s<>{}]*(?:<[^>]+>|\{[^}]+\}|\[\])*)/i', $raw, $m)) {
                return trim($m[1]);
            }
            return $raw;
        }

        return null;
    }

    private function resolvePropertyName(\ReflectionClass $reflection, string $serializedName): ?string
    {
        if ($reflection->hasProperty($serializedName)) {
            return $serializedName;
        }

        $camel = lcfirst(str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $serializedName))));
        if ($reflection->hasProperty($camel)) {
            return $camel;
        }

        return null;
    }

    private function resolveGetterMethod(\ReflectionClass $reflection, string $serializedName): ?\ReflectionMethod
    {
        $studly = ucfirst(str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $serializedName))));

        foreach (['get' . $studly, 'is' . $studly, 'has' . $studly, $serializedName] as $methodName) {
            if ($reflection->hasMethod($methodName)) {
                $method = $reflection->getMethod($methodName);
                if ($method->isPublic() && $method->getNumberOfRequiredParameters() === 0) {
                    return $method;
                }
            }
        }

        return null;
    }

    /**
     * @return array{tsType: string, targetEntity: string}|null
     */
    private function resolveDoctrineRelation(\ReflectionProperty $property): ?array
    {
        foreach ($property->getAttributes() as $attribute) {
            $attrName = $attribute->getName();
            if (str_contains($attrName, 'ManyToMany') || str_contains($attrName, 'OneToMany')) {
                $args = $attribute->getArguments();
                $targetEntity = $args['targetEntity'] ?? $args[0] ?? null;
                if ($targetEntity !== null) {
                    $shortName = $this->typeConverter->getShortClassName($targetEntity);
                    return [
                        'tsType' => sprintf('Array<%s>', $shortName),
                        'targetEntity' => $targetEntity,
                    ];
                }
            } elseif (str_contains($attrName, 'ManyToOne') || str_contains($attrName, 'OneToOne')) {
                $args = $attribute->getArguments();
                $targetEntity = $args['targetEntity'] ?? $args[0] ?? null;
                if ($targetEntity !== null) {
                    $shortName = $this->typeConverter->getShortClassName($targetEntity);
                    return [
                        'tsType' => $shortName,
                        'targetEntity' => $targetEntity,
                    ];
                }
            }
        }

        return null;
    }
}
