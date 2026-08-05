<?php

namespace Codifyo\TsGeneratorBundle\Tests\Fixtures;

use Symfony\Component\Serializer\Attribute\Groups;

class DummyTag
{
    #[Groups(['serializer_group'])]
    private int $id = 1;

    #[Groups(['serializer_group'])]
    private string $label = 'php';

    public function getId(): int
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }
}
