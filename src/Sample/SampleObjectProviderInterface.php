<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Sample;

/**
 * Loads the real object normalized in runtime mode when a type defines a "sample_id".
 *
 * Decorate or replace the service aliased to this interface to load samples from
 * another source than Doctrine (API client, fixtures, factory...).
 */
interface SampleObjectProviderInterface
{
    /**
     * @param class-string                         $className
     * @param int|string|array<string, int|string> $id
     *
     * @return object|null the object, or null when no object matches $id
     *
     * @throws \LogicException when samples cannot be loaded for $className at all
     */
    public function find(string $className, int|string|array $id): ?object;
}
