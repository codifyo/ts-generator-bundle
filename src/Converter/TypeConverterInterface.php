<?php

namespace Codifyo\TsGeneratorBundle\Converter;

use Symfony\Component\PropertyInfo\Type;

interface TypeConverterInterface
{
    /**
     * Converts a Symfony PropertyInfo Type or string PHP type to a TypeScript type name.
     *
     * @param Type|string|null $type
     * @return string
     */
    public function convertType(mixed $type): string;
}
