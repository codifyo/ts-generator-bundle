<?php

namespace Codifyo\TsGeneratorBundle\Model;

class ProjectConfig
{
    /**
     * @param TypeConfig[] $types
     */
    public function __construct(
        private string $name,
        private string $dir,
        private array $types = [],
        private string $mode = 'auto'
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDir(): string
    {
        return $this->dir;
    }

    /**
     * @return TypeConfig[]
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    public function getMode(): string
    {
        $normalized = strtolower($this->mode);

        return in_array($normalized, ['auto', 'runtime', 'static', 'jms'], true) ? $normalized : 'auto';
    }
}
