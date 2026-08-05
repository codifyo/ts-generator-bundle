<?php

namespace Codifyo\TsGeneratorBundle\Tests\Fixtures;

use Symfony\Component\Serializer\Attribute\Groups;

class DummyPostWithRelation
{
    #[Groups(['serializer_group'])]
    private int $id = 100;

    #[Groups(['serializer_group'])]
    private string $title = 'Symfony 7 Bundle';

    /**
     * @var array<DummyTag>
     */
    #[Groups(['serializer_group'])]
    private array $tags = [];

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return array<DummyTag>
     */
    public function getTags(): array
    {
        return $this->tags;
    }
}
