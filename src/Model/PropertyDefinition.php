<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Model;

final class PropertyDefinition
{
    /**
     * @var list<string>
     */
    private array $referencedClasses;

    /**
     * @param string|list<string>|null $referencedClasses FQCNs whose short name appears in $tsType
     */
    public function __construct(
        private string $name,
        private string $tsType,
        private bool $isNullable = false,
        string|array|null $referencedClasses = null,
    ) {
        $this->referencedClasses = array_values(array_unique(array_filter((array) $referencedClasses)));
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

    /**
     * First referenced class, kept for backward compatibility.
     */
    public function getReferencedClass(): ?string
    {
        return $this->referencedClasses[0] ?? null;
    }

    /**
     * @return list<string>
     */
    public function getReferencedClasses(): array
    {
        return $this->referencedClasses;
    }
}
