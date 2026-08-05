<?php

namespace Codifyo\TsGeneratorBundle\Extractor;

use Codifyo\TsGeneratorBundle\Converter\TypeConverterInterface;
use Codifyo\TsGeneratorBundle\Model\PropertyDefinition;
use Codifyo\TsGeneratorBundle\Model\TypeConfig;
use Codifyo\TsGeneratorBundle\Model\TypeDefinition;

class JmsSerializerMetadataExtractor implements MetadataExtractorInterface
{
    /**
     * @param mixed|null $jmsMetadataFactory JMS MetadataFactoryInterface instance
     */
    public function __construct(
        private mixed $jmsMetadataFactory,
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

        if ($this->jmsMetadataFactory === null) {
            return $typeDefinition;
        }

        $classMetadata = $this->jmsMetadataFactory->getMetadataForClass($className);
        if ($classMetadata === null) {
            return $typeDefinition;
        }

        foreach ($classMetadata->propertyMetadata as $propertyName => $propertyMetadata) {
            // Check groups
            if (!empty($targetGroups)) {
                $propGroups = $propertyMetadata->groups ?? [];
                if (empty(array_intersect($targetGroups, $propGroups))) {
                    continue;
                }
            }

            $serializedName = $propertyMetadata->serializedName ?? $propertyName;
            $typeStr = 'any';
            $referencedClass = null;
            $isNullable = true;

            if (isset($propertyMetadata->type['name'])) {
                $rawType = $propertyMetadata->type['name'];
                $typeStr = $this->typeConverter->convertType($rawType);

                if (class_exists($rawType) && !is_a($rawType, \DateTimeInterface::class, true)) {
                    $referencedClass = $rawType;
                }
            }

            $propDef = new PropertyDefinition(
                $serializedName,
                $typeStr,
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
}
