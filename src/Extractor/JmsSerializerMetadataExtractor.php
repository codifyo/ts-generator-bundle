<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Extractor;

use Codifyo\TsGeneratorBundle\Converter\TypeConverterInterface;
use Codifyo\TsGeneratorBundle\Helper\TypeReferenceCollector;
use Codifyo\TsGeneratorBundle\Model\PropertyDefinition;
use Codifyo\TsGeneratorBundle\Model\TypeConfig;
use Codifyo\TsGeneratorBundle\Model\TypeDefinition;

final class JmsSerializerMetadataExtractor implements MetadataExtractorInterface
{
    /**
     * @param object|null $jmsMetadataFactory JMS \Metadata\MetadataFactoryInterface instance (null when JMS is not installed)
     */
    public function __construct(
        private ?object $jmsMetadataFactory,
        private TypeConverterInterface $typeConverter,
    ) {
    }

    public function extract(TypeConfig $typeConfig): TypeDefinition
    {
        $className = $typeConfig->getClass();
        $targetGroups = $typeConfig->getGroups();
        $typeDefinition = new TypeDefinition($typeConfig->getName(), $className, [], [], $typeConfig->getKind());

        if (!class_exists($className)) {
            throw new \InvalidArgumentException(\sprintf('Class "%s" does not exist.', $className));
        }

        if ($this->jmsMetadataFactory === null || !method_exists($this->jmsMetadataFactory, 'getMetadataForClass')) {
            return $typeDefinition;
        }

        $classMetadata = $this->jmsMetadataFactory->getMetadataForClass($className);
        if ($classMetadata !== null && method_exists($classMetadata, 'getOutsideClassMetadata')) {
            $classMetadata = $classMetadata->getOutsideClassMetadata();
        }
        if ($classMetadata === null || !isset($classMetadata->propertyMetadata)) {
            return $typeDefinition;
        }

        foreach ($classMetadata->propertyMetadata as $propertyName => $propertyMetadata) {
            if (!empty($propertyMetadata->excluded)) {
                continue;
            }

            if ($targetGroups !== []) {
                $propGroups = $propertyMetadata->groups ?? ['Default'];
                if (array_intersect($targetGroups, $propGroups) === []) {
                    continue;
                }
            }

            $rawType = isset($propertyMetadata->type) && \is_array($propertyMetadata->type) ? $this->stringifyJmsType($propertyMetadata->type) : null;
            $tsType = $rawType !== null ? $this->typeConverter->convertType($rawType) : 'any';
            $referencedClasses = $rawType !== null ? TypeReferenceCollector::fromString($rawType) : [];

            // JMS skips null values by default (serialize_null: false), so every property may be absent or null
            $typeDefinition->addProperty(new PropertyDefinition(
                $propertyMetadata->serializedName ?? (string) $propertyName,
                $tsType,
                true,
                $referencedClasses
            ));

            foreach ($referencedClasses as $referencedClass) {
                $typeDefinition->addImport($this->typeConverter->getShortClassName($referencedClass), $referencedClass);
            }
        }

        return $typeDefinition;
    }

    /**
     * Turns JMS type arrays (['name' => 'array', 'params' => [['name' => 'App\Tag', 'params' => []]]])
     * into a PHPDoc-like string ("array<App\Tag>") understood by the converter.
     *
     * @param array{name?: string, params?: array<mixed>} $type
     */
    private function stringifyJmsType(array $type): ?string
    {
        $name = $type['name'] ?? null;
        if (!\is_string($name) || $name === '') {
            return null;
        }

        $params = [];
        foreach ($type['params'] ?? [] as $param) {
            if (\is_array($param) && ($paramString = $this->stringifyJmsType($param)) !== null) {
                $params[] = $paramString;
            }
        }

        // JMS collection types ("ArrayCollection<Foo>") are plain arrays once serialized
        if ($params !== [] && (strtolower($name) === 'array' || str_contains($name, 'Collection'))) {
            return \sprintf('array<%s>', implode(', ', $params));
        }

        return $name;
    }
}
