<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Extractor;

use Codifyo\TsGeneratorBundle\Converter\TypeConverterInterface;
use Codifyo\TsGeneratorBundle\Model\TypeConfig;
use Codifyo\TsGeneratorBundle\Model\TypeDefinition;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;

/**
 * Static extraction: reads the serializer metadata (groups, serialized names, ignore)
 * and resolves each attribute type without instantiating the class.
 */
final class SymfonySerializerMetadataExtractor extends AbstractSerializerMetadataExtractor
{
    public function __construct(
        private ?ClassMetadataFactoryInterface $classMetadataFactory,
        ?PropertyInfoExtractorInterface $propertyInfoExtractor,
        TypeConverterInterface $typeConverter,
    ) {
        parent::__construct($propertyInfoExtractor, $typeConverter);
    }

    public function extract(TypeConfig $typeConfig): TypeDefinition
    {
        $className = $typeConfig->getClass();
        $reflection = $this->reflectClass($className);
        $typeAliases = $this->parseClassTypeAliases($reflection);
        $typeDefinition = new TypeDefinition($typeConfig->getName(), $className, [], [], $typeConfig->getKind());

        foreach ($this->getAttributesToProcess($reflection, $typeConfig->getGroups()) as $propertyName => $serializedName) {
            $this->addProperty($typeDefinition, $reflection, $serializedName, $propertyName, $typeAliases);
        }

        return $typeDefinition;
    }

    /**
     * Groups are honored strictly: when no attribute matches, the type is empty (exactly what the
     * serializer would output) rather than exposing every property of the class.
     *
     * @param \ReflectionClass<object> $reflection
     * @param string[]                 $targetGroups
     *
     * @return array<string, string> Map of propertyName => serializedName
     */
    private function getAttributesToProcess(\ReflectionClass $reflection, array $targetGroups): array
    {
        $result = [];

        // Without serializer metadata there is no group information: fall back to the class properties
        if ($this->classMetadataFactory === null) {
            foreach ($reflection->getProperties() as $property) {
                if (!$property->isStatic()) {
                    $result[$property->getName()] = $property->getName();
                }
            }

            return $result;
        }

        if (!$this->classMetadataFactory->hasMetadataFor($reflection->getName())) {
            return $result;
        }

        foreach ($this->classMetadataFactory->getMetadataFor($reflection->getName())->getAttributesMetadata() as $attributeMetadata) {
            if ($attributeMetadata->isIgnored()) {
                continue;
            }

            if ($targetGroups !== [] && array_intersect($targetGroups, $attributeMetadata->getGroups()) === []) {
                continue;
            }

            $propertyName = $attributeMetadata->getName();
            $result[$propertyName] = $attributeMetadata->getSerializedName() ?? $propertyName;
        }

        return $result;
    }
}
