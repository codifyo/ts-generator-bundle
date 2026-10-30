<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Tests\Fixtures;

use Symfony\Component\Serializer\Attribute\Groups;

class DummyTagGroup
{
    #[Groups(['serializer_group'])]
    private int $id = 1;

    #[Groups(['serializer_group'])]
    private string $title = 'group';

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }
}
