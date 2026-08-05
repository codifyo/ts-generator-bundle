<?php

namespace Codifyo\TsGeneratorBundle\Model;

class TypeDefinition
{
    /**
     * @param PropertyDefinition[] $properties
     * @param array<string, string> $imports Map of importAlias => targetClassName/FileName
     */
    public function __construct(
        private string $name,
        private string $className,
        private array $properties = [],
        private array $imports = [],
        private string $kind = 'interface'
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    /**
     * @return PropertyDefinition[]
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    public function addProperty(PropertyDefinition $property): void
    {
        $this->properties[] = $property;
    }

    /**
     * @return array<string, string>
     */
    public function getImports(): array
    {
        return $this->imports;
    }

    public function addImport(string $alias, string $targetClass): void
    {
        $this->imports[$alias] = $targetClass;
    }
}
