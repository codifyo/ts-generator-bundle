<?php

namespace Codifyo\TsGeneratorBundle\Tests\Fixtures;

use Symfony\Component\Serializer\Annotation\Groups;

class DummyRole
{
    #[Groups(['serializer_group'])]
    private int $id;

    #[Groups(['serializer_group'])]
    private string $name;

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
