<?php

namespace Codifyo\TsGeneratorBundle\Extractor;

use Codifyo\TsGeneratorBundle\Converter\TypeConverterInterface;
use Codifyo\TsGeneratorBundle\Model\PropertyDefinition;
use Codifyo\TsGeneratorBundle\Model\TypeConfig;
use Codifyo\TsGeneratorBundle\Model\TypeDefinition;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class SymfonySerializerRuntimeExtractor implements MetadataExtractorInterface
{
    public function __construct(
        private ?NormalizerInterface $normalizer,
        private ?PropertyInfoExtractorInterface $propertyInfoExtractor,
        private TypeConverterInterface $typeConverter
    ) {
    }

    public function extract(TypeConfig $typeConfig): TypeDefinition
    {
        $className = $typeConfig->getClass();
        $targetGroups = $typeConfig->getGroups();
        $typeDefinition = new TypeDefinition($typeConfig->getName(), $className, [], [], $typeConfig->getKind());

        if (!class_exists($className) || $this->normalizer === null) {
            return $typeDefinition;
        }

        $context = [];
        if (!empty($targetGroups)) {
            $context['groups'] = $targetGroups;
        }

        $reflection = new \ReflectionClass($className);
        try {
            $instance = $reflection->newInstanceWithoutConstructor();
        } catch (\Throwable) {
            return $typeDefinition;
        }

        try {
            $normalized = $this->normalizer->normalize($instance, null, $context);
        } catch (\Throwable) {
            return $typeDefinition;
        }

        if (!is_array($normalized)) {
            return $typeDefinition;
        }

        foreach ($normalized as $serializedName => $normalizedValue) {
            $tsTypes = [];
            $isNullable = false;
            $referencedClass = null;

            $propertyCandidate = $this->resolvePropertyName($reflection, $serializedName);

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
                $getterMethod = $this->resolveGetterMethod($reflection, $serializedName);
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
                    if ($docComment !== false && preg_match('/@return\s+([^\s]+)/', $docComment, $matches)) {
                        $docTypeStr = trim($matches[1]);
                        $docTypes = explode('|', $docTypeStr);
                        foreach ($docTypes as $dt) {
                            $dt = trim($dt);
                            if (strtolower($dt) === 'null') {
                                $isNullable = true;
                                continue;
                            }
                            $convertedDocType = $this->typeConverter->convertType($dt);
                            $tsTypes[] = $convertedDocType;

                            // Extract inner referenced class if array<Class> or Collection<Class> or Class[]
                            if (preg_match('/(?:array|collection|iterable)?<*(?:[^,>]+,\s*)?([^\s>\[\]]+)/i', $dt, $refMatches)) {
                                $candidateRef = trim($refMatches[1], '<>[]');
                                if (!class_exists($candidateRef)) {
                                    $nsCandidate = $reflection->getNamespaceName() . '\\' . $candidateRef;
                                    if (class_exists($nsCandidate)) {
                                        $candidateRef = $nsCandidate;
                                    }
                                }
                                if (class_exists($candidateRef) && !is_a($candidateRef, \DateTimeInterface::class, true)) {
                                    $referencedClass = $candidateRef;
                                }
                            }
                        }
                    }
                }
            }

            // 3. Check Runtime Normalized Value Type
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

            if ($normalizedValue === null) {
                $isNullable = true;
            }

            $uniqueTsTypes = array_values(array_unique($tsTypes));

            // If we have specific typed arrays like Array<DummyTag>, filter out redundant 'any[]' or 'any'
            $hasSpecificType = false;
            foreach ($uniqueTsTypes as $t) {
                if ($t !== 'any' && $t !== 'any[]') {
                    $hasSpecificType = true;
                    break;
                }
            }

            if ($hasSpecificType) {
                $uniqueTsTypes = array_values(array_filter($uniqueTsTypes, fn($t) => $t !== 'any' && $t !== 'any[]'));
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
