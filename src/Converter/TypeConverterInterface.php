<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Converter;

use Symfony\Component\TypeInfo\Type;

interface TypeConverterInterface
{
    /**
     * Converts a Symfony TypeInfo Type or a PHP / PHPDoc type string to a TypeScript type.
     *
     * Nullability is not rendered here: callers decide how to express it.
     *
     * @param array<string, string> $typeAliases PHPStan / Psalm aliases in scope (AliasName => definition)
     */
    public function convertType(Type|string|null $type, array $typeAliases = []): string;

    public function getShortClassName(string $fqcn): string;
}
