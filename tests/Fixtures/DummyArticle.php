<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Tests\Fixtures;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

class DummyArticle
{
    #[Groups(['serializer_group'])]
    private int $id = 1;

    #[Groups(['serializer_group'])]
    private ?DummyTag $mainTag = null;

    /**
     * @var array<DummyTagGroup>
     */
    #[Groups(['serializer_group'])]
    private array $tagGroups = [];

    /**
     * @var DummyTagGroup|DummyTag|null
     */
    #[Groups(['serializer_group'])]
    private $featured;

    #[Groups(['serializer_group'])]
    private ?DummyUser $author = null;

    #[Groups(['serializer_group'])]
    #[SerializedName('published-at')]
    private ?\DateTimeImmutable $publishedAt = null;

    #[Groups(['internal'])]
    private string $secret = 'hidden';

    public function getId(): int
    {
        return $this->id;
    }

    public function getMainTag(): ?DummyTag
    {
        return $this->mainTag;
    }

    /**
     * @return array<DummyTagGroup>
     */
    public function getTagGroups(): array
    {
        return $this->tagGroups;
    }

    public function getAuthor(): ?DummyUser
    {
        return $this->author;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }
}
