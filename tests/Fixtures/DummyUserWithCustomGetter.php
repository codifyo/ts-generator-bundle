<?php

namespace Codifyo\TsGeneratorBundle\Tests\Fixtures;

use Symfony\Component\Serializer\Attribute\Groups;

class DummyUserWithCustomGetter
{
    #[Groups(['serializer_group'])]
    private int $id = 42;

    #[Groups(['serializer_group'])]
    private string $firstName = 'John';

    #[Groups(['serializer_group'])]
    private string $lastName = 'Doe';

    public function getId(): int
    {
        return $this->id;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    /**
     * Custom dynamic getter
     *
     * @return string
     */
    #[Groups(['serializer_group'])]
    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }
}
