<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Tests\Fixtures;

use Codifyo\TsGeneratorBundle\Tests\Fixtures\SubNamespace as Sub;
use Codifyo\TsGeneratorBundle\Tests\Fixtures\SubNamespace\{DummyImportedEntity, DummyTag as OtherTag};

use function sprintf;

/**
 * Exercises grouped, aliased and function use statements for ClassResolver.
 */
class DummyEntityWithGroupedImports
{
    /** @var DummyImportedEntity|null */
    public $imported;

    /** @var OtherTag|null */
    public $aliased;

    /** @var Sub\DummyTag|null */
    public $relative;

    public function describe(): string
    {
        return sprintf('%s', self::class);
    }
}
