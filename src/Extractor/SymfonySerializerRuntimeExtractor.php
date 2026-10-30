<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Extractor;

use Codifyo\TsGeneratorBundle\Converter\TypeConverterInterface;
use Codifyo\TsGeneratorBundle\Model\TypeConfig;
use Codifyo\TsGeneratorBundle\Model\TypeDefinition;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Runtime extraction: normalizes an instance created WITHOUT its constructor through the real
 * serializer, so custom normalizers and name converters are taken into account.
 *
 * Warning: this executes application code (getters, normalizers) on a partially initialized
 * object. Normalizers with side effects (database, HTTP...) should use the "static" mode.
 */
final class SymfonySerializerRuntimeExtractor extends AbstractSerializerMetadataExtractor
{
    private LoggerInterface $logger;

    public function __construct(
        private ?NormalizerInterface $normalizer,
        ?PropertyInfoExtractorInterface $propertyInfoExtractor,
        TypeConverterInterface $typeConverter,
        private ?ClassMetadataFactoryInterface $classMetadataFactory = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($propertyInfoExtractor, $typeConverter);
        $this->logger = $logger ?? new NullLogger();
    }

    public function extract(TypeConfig $typeConfig): TypeDefinition
    {
        $className = $typeConfig->getClass();
        $reflection = $this->reflectClass($className);
        $typeAliases = $this->parseClassTypeAliases($reflection);
        $targetGroups = $typeConfig->getGroups();
        $typeDefinition = new TypeDefinition($typeConfig->getName(), $className, [], [], $typeConfig->getKind());

        $normalized = $this->normalizeBlankInstance($reflection, $targetGroups);

        // Accessors carrying #[Groups] that the blank instance could not normalize
        if ($this->classMetadataFactory !== null && $this->classMetadataFactory->hasMetadataFor($className)) {
            foreach ($this->classMetadataFactory->getMetadataFor($className)->getAttributesMetadata() as $attributeMetadata) {
                if ($attributeMetadata->isIgnored()) {
                    continue;
                }
                if ($targetGroups !== [] && array_intersect($targetGroups, $attributeMetadata->getGroups()) === []) {
                    continue;
                }
                $serializedName = $attributeMetadata->getSerializedName() ?? $attributeMetadata->getName();
                if (!\array_key_exists($serializedName, $normalized)) {
                    $normalized[$serializedName] = null;
                }
            }
        }

        foreach ($normalized as $serializedName => $normalizedValue) {
            $serializedName = (string) $serializedName;
            $this->addProperty(
                $typeDefinition,
                $reflection,
                $serializedName,
                $this->resolvePropertyName($reflection, $serializedName),
                $typeAliases,
                $normalizedValue
            );
        }

        return $typeDefinition;
    }

    /**
     * @param \ReflectionClass<object> $reflection
     * @param string[]                 $targetGroups
     *
     * @return array<array-key, mixed>
     */
    private function normalizeBlankInstance(\ReflectionClass $reflection, array $targetGroups): array
    {
        if ($this->normalizer === null || !$reflection->isInstantiable()) {
            return [];
        }

        $context = $targetGroups !== [] ? ['groups' => $targetGroups] : [];

        try {
            $normalized = $this->normalizer->normalize($reflection->newInstanceWithoutConstructor(), null, $context);
        } catch (\Throwable $e) {
            $this->logger->warning('Runtime normalization of "{class}" failed, falling back to metadata: {message}', [
                'class' => $reflection->getName(),
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return [];
        }

        return \is_array($normalized) ? $normalized : [];
    }
}
