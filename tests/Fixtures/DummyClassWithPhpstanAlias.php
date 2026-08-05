<?php

namespace Codifyo\TsGeneratorBundle\Tests\Fixtures;

use Doctrine\Common\Collections\Collection;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * @phpstan-type ClassB array{id: int, name: string, active?: bool}
 */
class DummyClassWithPhpstanAlias
{
    /**
     * Custom getter returning Collection<int, DummyTag>
     *
     * @return Collection<int, DummyTag>
     */
    #[Groups(['serializer_group'])]
    public function getTags(): Collection
    {
        return new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Custom getter returning array<ClassB>
     *
     * @return array<ClassB>
     */
    #[Groups(['serializer_group'])]
    public function getCustomItems(): array
    {
        return [
            ['id' => 1, 'name' => 'Item 1', 'active' => true],
        ];
    }
}
