<?php

namespace Codifyo\TsGeneratorBundle\Tests\Fixtures;

use Codifyo\TsGeneratorBundle\Tests\Fixtures\SubNamespace\DummyImportedEntity;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Serializer\Attribute\Groups;

class DummyEntityWithUseImport
{
    /**
     * @var Collection<int, DummyImportedEntity>
     */
    #[Groups(['serializer_group'])]
    private Collection $importedItems;

    public function __construct()
    {
        $this->importedItems = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * @return Collection<int, DummyImportedEntity>
     */
    public function getImportedItems(): Collection
    {
        return $this->importedItems;
    }
}
