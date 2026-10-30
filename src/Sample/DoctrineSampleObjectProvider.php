<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Sample;

use Doctrine\Persistence\ManagerRegistry;

/**
 * Loads samples with the Doctrine object manager in charge of the class.
 */
final class DoctrineSampleObjectProvider implements SampleObjectProviderInterface
{
    /**
     * @param ManagerRegistry|null $registry null when DoctrineBundle is not installed
     */
    public function __construct(
        private ?object $registry,
    ) {
    }

    public function find(string $className, int|string|array $id): ?object
    {
        if (!$this->registry instanceof ManagerRegistry) {
            throw new \LogicException(\sprintf('Cannot load the sample of "%s": "sample_id" requires DoctrineBundle, or a custom "%s" service.', $className, SampleObjectProviderInterface::class));
        }

        $manager = $this->registry->getManagerForClass($className);
        if ($manager === null) {
            throw new \LogicException(\sprintf('Cannot load the sample of "%s": the class is not managed by Doctrine.', $className));
        }

        return $manager->find($className, $id);
    }
}
