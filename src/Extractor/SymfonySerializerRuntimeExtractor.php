<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Extractor;

use Codifyo\TsGeneratorBundle\Converter\TypeConverterInterface;
use Codifyo\TsGeneratorBundle\Model\TypeConfig;
use Codifyo\TsGeneratorBundle\Model\TypeDefinition;
use Codifyo\TsGeneratorBundle\Sample\SampleObjectProviderInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Runtime extraction: normalizes an instance through the real serializer, so custom normalizers
 * and name converters are taken into account.
 *
 * The instance is the real object identified by the type "sample_id" when one is configured
 * (loaded through a SampleObjectProviderInterface), otherwise an instance created WITHOUT its
 * constructor. The normalized values then serve as a last-resort type hint.
 *
 * Warning: this executes application code (getters, normalizers). Normalizers with side
 * effects (database, HTTP...) should use the "static" mode.
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
        private ?SampleObjectProviderInterface $sampleObjectProvider = null,
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

        $normalized = $this->normalizeInstance($reflection, $typeConfig);

        // Accessors carrying #[Groups] that the instance could not normalize
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
     *
     * @return array<array-key, mixed>
     */
    private function normalizeInstance(\ReflectionClass $reflection, TypeConfig $typeConfig): array
    {
        if ($this->normalizer === null) {
            return [];
        }

        $instance = $this->loadSample($reflection->getName(), $typeConfig->getSampleId());
        if ($instance === null) {
            if (!$reflection->isInstantiable()) {
                return [];
            }
            $instance = $reflection->newInstanceWithoutConstructor();
        }

        $groups = $typeConfig->getGroups();

        try {
            $normalized = $this->normalizer->normalize($instance, null, $groups !== [] ? ['groups' => $groups] : []);
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

    /**
     * A missing sample (deleted row, empty database in CI) is not fatal: generation falls back
     * to a blank instance. A provider unable to load the class at all is a configuration error.
     *
     * @param class-string                              $className
     * @param int|string|array<string, int|string>|null $sampleId
     */
    private function loadSample(string $className, int|string|array|null $sampleId): ?object
    {
        if ($sampleId === null) {
            return null;
        }

        if ($this->sampleObjectProvider === null) {
            throw new \LogicException(\sprintf('Type "%s" defines a "sample_id" but no "%s" is available.', $className, SampleObjectProviderInterface::class));
        }

        $context = ['class' => $className, 'id' => json_encode($sampleId)];

        try {
            $sample = $this->sampleObjectProvider->find($className, $sampleId);
        } catch (\LogicException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->warning('Loading the sample {id} of "{class}" failed, using a blank instance: {message}', $context + [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return null;
        }

        if ($sample === null) {
            $this->logger->warning('Sample {id} of "{class}" not found, using a blank instance.', $context);

            return null;
        }

        if (!$sample instanceof $className) {
            throw new \LogicException(\sprintf('The sample provider returned a "%s" instead of a "%s".', get_debug_type($sample), $className));
        }

        return $sample;
    }
}
