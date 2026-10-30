<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Generator;

interface TypeScriptGeneratorInterface
{
    /**
     * Generates TS files for specified project or all projects if null.
     *
     * @return array<string, string[]> Map of projectName => array of generated file paths
     */
    public function generate(?string $projectName = null): array;
}
