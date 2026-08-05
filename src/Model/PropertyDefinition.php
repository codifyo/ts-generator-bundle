<?php

namespace Codifyo\TsGeneratorBundle\Model;

class PropertyDefinition
{
    public function __construct(
        private string $name,
        private string $tsType,
        private bool $isNullable = false,
        private ?string $referencedClass = null
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTsType(): string
    {
        return $this->tsType;
    }

    public function isNullable(): bool
    {
        return $this->isNullable;
    }

    public function getReferencedClass(): ?string
    {
        return $this->referencedClass;
    }
}
