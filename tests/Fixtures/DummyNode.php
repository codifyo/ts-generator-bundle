<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Tests\Fixtures;

use Symfony\Component\Serializer\Attribute\Groups;

class DummyNode
{
    #[Groups(['serializer_group'])]
    private string $name = 'node';

    #[Groups(['serializer_group'])]
    private ?DummyNode $parent = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }
}
