<?php

namespace Codifyo\TsGeneratorBundle\Tests\Fixtures;

use Symfony\Component\Serializer\Annotation\Groups;

class DummyUser
{
    #[Groups(['serializer_group', 'other_group'])]
    private int $id;

    #[Groups(['serializer_group'])]
    private string $username;

    #[Groups(['other_group'])]
    private ?string $email = null;

    #[Groups(['serializer_group'])]
    private ?DummyRole $role = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getRole(): ?DummyRole
    {
        return $this->role;
    }
}
