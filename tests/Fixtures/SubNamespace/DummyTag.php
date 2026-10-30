<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Tests\Fixtures\SubNamespace;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Same short name as Fixtures\DummyTag, used to test TS name collisions.
 */
class DummyTag
{
    #[Groups(['serializer_group'])]
    private string $code = 'x';

    public function getCode(): string
    {
        return $this->code;
    }
}
