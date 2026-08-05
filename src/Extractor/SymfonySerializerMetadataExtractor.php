<?php

namespace Codifyo\TsGeneratorBundle\Extractor;

use Codifyo\TsGeneratorBundle\Converter\TypeConverterInterface;
use Codifyo\TsGeneratorBundle\Model\PropertyDefinition;
use Codifyo\TsGeneratorBundle\Model\TypeConfig;
use Codifyo\TsGeneratorBundle\Model\TypeDefinition;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\Type;
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
            } else {
                // Fallback to Reflection if PropertyInfo returns nothing
                $refProp = new \ReflectionProperty($className, $propertyName);
                $refType = $refProp->getType();

                if ($refType !== null) {
                    $isNullable = $refType->allowsNull();
                    if ($refType instanceof \ReflectionNamedType) {
                        $tsTypes[] = $this->typeConverter->convertType($refType->getName());
                        if (!$refType->isBuiltin()) {
                            $colClass = $refType->getName();
                            if (!is_a($colClass, \DateTimeInterface::class, true)) {
                                $referencedClass = $colClass;
                            }
                        }
                    }
                } else {
                    $tsTypes[] = 'any';
                }
            }

            $uniqueTsTypes = array_unique($tsTypes);
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

                // If target groups specified, filter by groups overlap
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

        // Fallback or complete with Reflection properties if classMetadataFactory had no attributes
        if (empty($result)) {
            $reflection = new \ReflectionClass($className);
            foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED | \ReflectionProperty::IS_PRIVATE) as $property) {
                $result[$property->getName()] = $property->getName();
            }
        }

        return $result;
    }
}
