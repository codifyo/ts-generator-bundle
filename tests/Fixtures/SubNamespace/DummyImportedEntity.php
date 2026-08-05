<?php

namespace Codifyo\TsGeneratorBundle\Tests\Fixtures\SubNamespace;

use Symfony\Component\Serializer\Attribute\Groups;

class DummyImportedEntity
{
    #[Groups(['serializer_group'])]
    private int $id = 99;

    #[Groups(['serializer_group'])]
    private string $name = 'Imported';

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
