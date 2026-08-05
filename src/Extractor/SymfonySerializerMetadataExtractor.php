<?php

namespace Codifyo\TsGeneratorBundle\Extractor;

use Codifyo\TsGeneratorBundle\Converter\PhpToTypeScriptTypeConverter;
use Codifyo\TsGeneratorBundle\Converter\TypeConverterInterface;
use Codifyo\TsGeneratorBundle\Helper\ClassResolver;
use Codifyo\TsGeneratorBundle\Model\PropertyDefinition;
use Codifyo\TsGeneratorBundle\Model\TypeConfig;
use Codifyo\TsGeneratorBundle\Model\TypeDefinition;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\Serializer\Mapping\AttributeMetadataInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;

class SymfonySerializerMetadataExtractor implements MetadataExtractorInterface
{
    public function __construct(
        private ?ClassMetadataFactoryInterface $classMetadataFactory,
        private ?PropertyInfoExtractorInterface $propertyInfoExtractor,
        private TypeConverterInterface $typeConverter
    ) {
    }

    public function extract(TypeConfig $typeConfig): TypeDefinition
    {
        $className = $typeConfig->getClass();
        $targetGroups = $typeConfig->getGroups();
        $typeDefinition = new TypeDefinition($typeConfig->getName(), $className, [], [], $typeConfig->getKind());

        if (!class_exists($className)) {
            throw new \InvalidArgumentException(sprintf('Class "%s" does not exist.', $className));
        }

        $reflection = new \ReflectionClass($className);
        $typeAliases = $this->parseClassTypeAliases($reflection);
        if ($this->typeConverter instanceof PhpToTypeScriptTypeConverter) {
            $this->typeConverter->setTypeAliases($typeAliases);
        }

        $definition = $this->doExtract($typeConfig, $reflection, $targetGroups);

        if (empty($definition->getProperties()) && !empty($targetGroups)) {
            $definition = $this->doExtract($typeConfig, $reflection, []);
        }

        return $definition;
    }

    private function doExtract(TypeConfig $typeConfig, \ReflectionClass $reflection, array $targetGroups): TypeDefinition
    {
        $className = $typeConfig->getClass();
        $typeDefinition = new TypeDefinition($typeConfig->getName(), $className, [], [], $typeConfig->getKind());

        $propertiesToProcess = $this->getAttributesToProcess($className, $targetGroups);

        foreach ($propertiesToProcess as $propertyName => $serializedName) {
            $types = $this->propertyInfoExtractor?->getTypes($className, $propertyName);
            $isNullable = false;
            $tsTypes = [];
            $referencedClass = null;

            if ($types !== null && !empty($types)) {
                foreach ($types as $type) {
                    if ($type->isNullable()) {
                        $isNullable = true;
                    }

                    $converted = $this->typeConverter->convertType($type);
                    $tsTypes[] = $converted;

                    $objClassName = $type->getClassName();
                    if ($objClassName !== null && !is_a($objClassName, \DateTimeInterface::class, true)) {
                        $referencedClass = ClassResolver::resolveFqcn($objClassName, $reflection);
                    } elseif ($type->isCollection()) {
                        foreach ($type->getCollectionValueTypes() as $valType) {
                            $colClass = $valType->getClassName();
                            if ($colClass !== null && !is_a($colClass, \DateTimeInterface::class, true)) {
                                $referencedClass = ClassResolver::resolveFqcn($colClass, $reflection);
                            }
                        }
                    }
                }
            }

            // Check Reflection Property / Getter / PHPDoc
            if (empty($tsTypes) || $tsTypes === ['any[]'] || $tsTypes === ['any']) {
                $refProp = $reflection->hasProperty($propertyName) ? $reflection->getProperty($propertyName) : null;
                $getterMethod = $this->resolveGetterMethod($reflection, $propertyName);

                if ($refProp !== null) {
                    $refType = $refProp->getType();
                    if ($refType !== null) {
                        $isNullable = $refType->allowsNull();
                        if ($refType instanceof \ReflectionNamedType) {
                            $tsTypes[] = $this->typeConverter->convertType($refType->getName());
                            if (!$refType->isBuiltin()) {
                                $colClass = $refType->getName();
                                if (!is_a($colClass, \DateTimeInterface::class, true)) {
                                    $referencedClass = ClassResolver::resolveFqcn($colClass, $reflection);
                                }
                            }
                        }
                    }

                    // Check PHPDoc @var on property
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

                                $extractedRef = $this->extractReferencedClass($dt, $reflection);
                                if ($extractedRef !== null) {
                                    $referencedClass = $extractedRef;
                                }
                            }
                        }
                    }
                }

                if ($getterMethod !== null) {
                    $returnType = $getterMethod->getReturnType();
                    if ($returnType !== null) {
                        if ($returnType->allowsNull()) {
                            $isNullable = true;
                        }
                        if ($returnType instanceof \ReflectionNamedType) {
                            $tsTypes[] = $this->typeConverter->convertType($returnType->getName());
                            if (!$returnType->isBuiltin()) {
                                $colClass = $returnType->getName();
                                if (!is_a($colClass, \DateTimeInterface::class, true)) {
                                    $referencedClass = ClassResolver::resolveFqcn($colClass, $reflection);
                                }
                            }
                        }
                    }

                    // Check PHPDoc @return on getter
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

                                $extractedRef = $this->extractReferencedClass($dt, $reflection);
                                if ($extractedRef !== null) {
                                    $referencedClass = $extractedRef;
                                }
                            }
                        }
                    }
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
                $serializedName,
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

    private function extractReferencedClass(string $typeStr, \ReflectionClass $reflection): ?string
    {
        $typeStr = trim(ltrim($typeStr, '\\'));

        if (preg_match('/^(?:array|collection|iterable|doctrine\\\\common\\\\collections\\\\collection)<(?:[^,>]+,\s*)?\s*([^>]+)\s*>$/i', $typeStr, $matches)) {
            return $this->extractReferencedClass($matches[1], $reflection);
        }

        if (preg_match('/^([^\s\[\]]+)\[\]$/i', $typeStr, $matches)) {
            return $this->extractReferencedClass($matches[1], $reflection);
        }

        $candidate = $typeStr;
        if (in_array(strtolower($candidate), ['int', 'integer', 'float', 'double', 'string', 'bool', 'boolean', 'array', 'iterable', 'mixed', 'void', 'null', 'object', 'any'], true)) {
            return null;
        }

        return ClassResolver::resolveFqcn($candidate, $reflection);
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
     * @param string[] $targetGroups
     * @return array<string, string> Map of propertyName => serializedName
     */
    private function getAttributesToProcess(string $className, array $targetGroups): array
    {
        $result = [];

        if ($this->classMetadataFactory !== null && $this->classMetadataFactory->hasMetadataFor($className)) {
            $classMetadata = $this->classMetadataFactory->getMetadataFor($className);
            /** @var AttributeMetadataInterface $attributeMetadata */
            foreach ($classMetadata->getAttributesMetadata() as $attributeMetadata) {
                $propGroups = $attributeMetadata->getGroups();

                if (!empty($targetGroups)) {
                    if (empty(array_intersect($targetGroups, $propGroups))) {
                        continue;
                    }
                }

                $propertyName = $attributeMetadata->getName();
                $serializedName = $attributeMetadata->getSerializedName() ?? $propertyName;
                $result[$propertyName] = $serializedName;
            }
        }

        if (empty($result)) {
            $reflection = new \ReflectionClass($className);
            foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED | \ReflectionProperty::IS_PRIVATE) as $property) {
                $result[$property->getName()] = $property->getName();
            }
        }

        return $result;
    }
}
