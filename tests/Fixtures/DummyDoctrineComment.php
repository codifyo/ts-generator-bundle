<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Tests\Fixtures;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

class DummyDoctrineComment
{
    #[Groups(['serializer_group'])]
    #[ORM\ManyToOne(targetEntity: DummyUser::class)]
    private ?DummyUser $author = null;

    #[Groups(['serializer_group'])]
    #[ORM\ManyToOne(targetEntity: DummyArticle::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?DummyArticle $article = null;

    #[Groups(['serializer_group'])]
    #[ORM\OneToOne]
    private DummyRole $role;

    /**
     * @var Collection<int, DummyTag>
     */
    #[Groups(['serializer_group'])]
    #[ORM\OneToMany(targetEntity: DummyTag::class, mappedBy: 'comment')]
    private Collection $tags;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
    }

    public function getAuthor(): ?DummyUser
    {
        return $this->author;
    }

    public function getArticle(): ?DummyArticle
    {
        return $this->article;
    }

    public function getRole(): DummyRole
    {
        return $this->role;
    }

    /**
     * @return Collection<int, DummyTag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }
}
