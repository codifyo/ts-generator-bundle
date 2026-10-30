<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Helper;

use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\ArrayShapeType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\CompositeTypeInterface;
use Symfony\Component\TypeInfo\Type\EnumType;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\WrappingTypeInterface;

/**
 * Finds the classes referenced by a type, i.e. the ones that must become a TypeScript
 * type of their own (imported or inlined). Scalars, dates, enums and iterables are skipped
 * because the converter already renders them as primitives / arrays.
 */
final class TypeReferenceCollector
{
    private const NON_CLASS_KEYWORDS = [
        'int', 'integer', 'positive-int', 'negative-int', 'non-negative-int', 'float', 'double', 'string',
        'non-empty-string', 'class-string', 'bool', 'boolean', 'true', 'false', 'array', 'list',
        'non-empty-array', 'non-empty-list', 'iterable', 'mixed', 'void', 'never', 'null', 'object',
        'any', 'array-key', 'scalar', 'callable', 'resource', 'self', 'static',
    ];

    /**
     * @return list<string>
     */
    public static function fromTypeInfo(Type $type): array
    {
        $classes = [];

        if ($type instanceof ArrayShapeType) {
            foreach ($type->getShape() as $field) {
                $classes = [...$classes, ...self::fromTypeInfo($field['type'])];
            }
        } elseif ($type instanceof CollectionType) {
            $classes = self::fromTypeInfo($type->getCollectionValueType());
        } elseif ($type instanceof GenericType) {
            $wrapped = $type->getWrappedType();
            if ($wrapped instanceof ObjectType && is_a($wrapped->getClassName(), \Traversable::class, true)) {
                $variableTypes = $type->getVariableTypes();
                $classes = $variableTypes === [] ? [] : self::fromTypeInfo(end($variableTypes));
            } else {
                $classes = self::fromTypeInfo($wrapped);
            }
        } elseif ($type instanceof CompositeTypeInterface) {
            foreach ($type->getTypes() as $subType) {
                $classes = [...$classes, ...self::fromTypeInfo($subType)];
            }
        } elseif ($type instanceof WrappingTypeInterface) {
            $classes = self::fromTypeInfo($type->getWrappedType());
        } elseif ($type instanceof ObjectType && !$type instanceof EnumType && self::isReferenceable($type->getClassName())) {
            $classes = [$type->getClassName()];
        }

        return array_values(array_unique($classes));
    }

    /**
     * @param \ReflectionClass<*>|null $context
     * @param array<string, string> $typeAliases
     *
     * @return list<string>
     */
    public static function fromString(string $type, ?\ReflectionClass $context = null, array $typeAliases = []): array
    {
        $classes = [];

        // Identifiers that are not array-shape keys ("id: int" / "name?: string")
        preg_match_all('/(?<![\w\\\\$-])\\\\?([A-Za-z_]\w*(?:\\\\[A-Za-z_]\w*)*)(?![\w\\\\-])(?!\s*\??\s*:)/', $type, $matches);

        foreach (array_unique($matches[1]) as $token) {
            if (\in_array(strtolower($token), self::NON_CLASS_KEYWORDS, true)) {
                continue;
            }

            if (isset($typeAliases[$token])) {
                $definition = $typeAliases[$token];
                unset($typeAliases[$token]);
                $classes = [...$classes, ...self::fromString($definition, $context, $typeAliases)];
                continue;
            }

            $fqcn = $context !== null
                ? ClassResolver::resolveFqcn($token, $context)
                : (class_exists($token) || interface_exists($token) ? $token : null);

            if ($fqcn !== null && self::isReferenceable($fqcn)) {
                $classes[] = $fqcn;
            }
        }

        return array_values(array_unique($classes));
    }

    public static function isReferenceable(string $class): bool
    {
        return (class_exists($class) || interface_exists($class))
            && !enum_exists($class)
            && !is_a($class, \DateTimeInterface::class, true)
            && !is_a($class, \Traversable::class, true);
    }
}
