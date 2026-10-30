<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Helper;

/**
 * Infers a TypeScript type from a normalized value, used when nothing else describes a property
 * (custom normalizer fields, untyped getters). Values from a real sample give precise shapes:
 * ['count' => 3, 'labels' => ['a']] becomes "{ count: number; labels: Array<string> }".
 *
 * Lists of objects are merged into one shape: keys missing from some items become optional.
 */
final class ValueTypeInferrer
{
    private const MAX_DEPTH = 5;
    private const OPAQUE_TYPE = 'Record<string, any>';
    private const TS_IDENTIFIER_PATTERN = '/^[A-Za-z_$][A-Za-z0-9_$]*$/';

    public static function infer(mixed $value): string
    {
        return self::inferValue($value, 0);
    }

    private static function inferValue(mixed $value, int $depth): string
    {
        return match (true) {
            $value === null => 'any',
            \is_bool($value) => 'boolean',
            \is_int($value), \is_float($value) => 'number',
            \is_string($value) => 'string',
            \is_array($value) => self::inferArray($value, $depth),
            default => 'any',
        };
    }

    /**
     * @param array<array-key, mixed> $value
     */
    private static function inferArray(array $value, int $depth): string
    {
        if ($value === []) {
            return 'any[]';
        }

        if ($depth >= self::MAX_DEPTH) {
            return array_is_list($value) ? 'any[]' : self::OPAQUE_TYPE;
        }

        if (array_is_list($value)) {
            $itemType = self::inferUnion($value, $depth + 1);

            return $itemType === 'any' ? 'any[]' : \sprintf('Array<%s>', $itemType);
        }

        if (array_filter(array_keys($value), 'is_string') === []) {
            return \sprintf('Record<number, %s>', self::inferUnion(array_values($value), $depth + 1));
        }

        return self::inferShape([$value], $depth);
    }

    /**
     * Type of a set of values: objects are merged into one shape, other types are united.
     *
     * @param list<mixed> $values
     */
    private static function inferUnion(array $values, int $depth): string
    {
        $shapes = [];
        $types = [];
        $hasNull = false;

        foreach ($values as $value) {
            if ($value === null) {
                $hasNull = true;
            } elseif (\is_array($value) && $value !== [] && !array_is_list($value) && array_filter(array_keys($value), 'is_string') !== []) {
                $shapes[] = $value;
            } else {
                $types[] = self::inferValue($value, $depth);
            }
        }

        if ($shapes !== []) {
            $types[] = self::inferShape($shapes, $depth);
        }

        $types = array_values(array_unique($types));
        if (\count($types) > 1) {
            $types = array_values(array_filter($types, static fn (string $t): bool => $t !== 'any'));
        }

        if ($types === []) {
            return 'any';
        }

        if ($hasNull && !\in_array('any', $types, true)) {
            $types[] = 'null';
        }

        return implode(' | ', $types);
    }

    /**
     * @param non-empty-list<array<array-key, mixed>> $objects
     */
    private static function inferShape(array $objects, int $depth): string
    {
        if ($depth >= self::MAX_DEPTH) {
            return self::OPAQUE_TYPE;
        }

        /** @var array<array-key, list<mixed>> $valuesByKey */
        $valuesByKey = [];
        foreach ($objects as $object) {
            foreach ($object as $key => $fieldValue) {
                $valuesByKey[$key][] = $fieldValue;
            }
        }

        $fields = [];
        foreach ($valuesByKey as $key => $fieldValues) {
            $key = (string) $key;
            $name = preg_match(self::TS_IDENTIFIER_PATTERN, $key) ? $key : \sprintf("'%s'", addcslashes($key, "'\\"));
            $optional = \count($fieldValues) < \count($objects) ? '?' : '';
            $fields[] = \sprintf('%s%s: %s', $name, $optional, self::inferUnion($fieldValues, $depth + 1));
        }

        return '{ ' . implode('; ', $fields) . ' }';
    }
}
