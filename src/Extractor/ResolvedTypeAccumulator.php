<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Extractor;

/**
 * @internal mutable state collected while resolving a single property type
 */
final class ResolvedTypeAccumulator
{
    /** @var list<string> */
    public array $types = [];

    /** @var list<string> */
    public array $references = [];

    public bool $nullable = false;
}
