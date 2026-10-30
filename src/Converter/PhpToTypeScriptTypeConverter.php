<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Converter;

use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\ArrayShapeType;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\EnumType;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\IntersectionType;
use Symfony\Component\TypeInfo\Type\NullableType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\TemplateType;
use Symfony\Component\TypeInfo\Type\UnionType;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * Stateless converter: type aliases are passed with each call so that a shared
 * service instance never leaks aliases from one class into another.
 */
final class PhpToTypeScriptTypeConverter implements TypeConverterInterface
{
    private const COLLECTION_PATTERN = '/^(?:array|list|non-empty-array|non-empty-list|collection|iterable|doctrine\\\\common\\\\collections\\\\collection)<(?:[a-z\-]+\s*,\s*)?(.+)>$/i';

    public function convertType(Type|string|null $type, array $typeAliases = []): string
    {
        if ($type === null) {
            return 'any';
        }

        if (\is_string($type)) {
            return $this->convertStringPhpType($type, $typeAliases);
        }

        return $this->convertTypeInfo($type);
    }

    /**
     * @param array<string, string> $typeAliases
     */
    public function convertStringPhpType(string $phpType, array $typeAliases = []): string
    {
        $phpType = trim(ltrim(trim($phpType), '\\'));

        if (isset($typeAliases[$phpType])) {
            $definition = $typeAliases[$phpType];
            unset($typeAliases[$phpType]); // guards against self-referencing aliases

            return $this->convertStringPhpType($definition, $typeAliases);
        }

        if (preg_match('/^\?(.+)$/', $phpType, $matches)) {
            return $this->convertStringPhpType($matches[1], $typeAliases);
        }

        $unionParts = $this->splitTopLevel($phpType, '|');
        if (\count($unionParts) > 1) {
            $converted = [];
            foreach ($unionParts as $part) {
                if (strtolower(trim($part)) === 'null') {
                    continue;
                }
                $converted[] = $this->convertStringPhpType($part, $typeAliases);
            }

            return $converted === [] ? 'null' : implode(' | ', array_unique($converted));
        }

        if (preg_match('/^(?:array)?\{(.*)\}$/is', $phpType, $matches)) {
            return $this->convertArrayShape($matches[1], $typeAliases);
        }

        if (preg_match('/^(.+)\[\]$/', $phpType, $matches)) {
            return \sprintf('Array<%s>', $this->convertStringPhpType($matches[1], $typeAliases));
        }

        if (preg_match(self::COLLECTION_PATTERN, $phpType, $matches)) {
            return \sprintf('Array<%s>', $this->convertStringPhpType($matches[1], $typeAliases));
        }

        if (enum_exists($phpType)) {
            return $this->convertEnum($phpType);
        }

        return match (strtolower($phpType)) {
            'int', 'integer', 'positive-int', 'negative-int', 'non-negative-int', 'float', 'double' => 'number',
            'string', 'non-empty-string', 'class-string' => 'string',
            'bool', 'boolean', 'true', 'false' => 'boolean',
            'datetime', 'datetimeimmutable', 'datetimeinterface' => 'string',
            'array', 'list', 'iterable' => 'any[]',
            'object' => 'Record<string, any>',
            'null' => 'null',
            'mixed' => 'any',
            'void' => 'void',
            'never' => 'never',
            default => $this->getShortClassName($phpType),
        };
    }

    public function getShortClassName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts);
    }

    private function convertTypeInfo(Type $type): string
    {
        if ($type instanceof NullableType) {
            return $this->convertTypeInfo($type->getWrappedType());
        }

        if ($type instanceof UnionType) {
            $parts = [];
            foreach ($type->getTypes() as $subType) {
                if ($subType instanceof BuiltinType && $subType->getTypeIdentifier() === TypeIdentifier::NULL) {
                    continue;
                }
                $parts[] = $this->convertTypeInfo($subType);
            }

            return $parts === [] ? 'null' : implode(' | ', array_unique($parts));
        }

        if ($type instanceof IntersectionType) {
            return implode(' & ', array_unique(array_map(fn (Type $t) => $this->convertTypeInfo($t), $type->getTypes())));
        }

        if ($type instanceof ArrayShapeType) {
            $fields = [];
            foreach ($type->getShape() as $key => $field) {
                $fields[] = \sprintf('%s%s: %s', $key, $field['optional'] ? '?' : '', $this->convertTypeInfo($field['type']));
            }

            return $fields === [] ? 'Record<string, any>' : '{ ' . implode('; ', $fields) . ' }';
        }

        if ($type instanceof CollectionType) {
            $valueType = $this->convertTypeInfo($type->getCollectionValueType());
            $keyType = $type->getCollectionKeyType();

            if (!$type->isList() && $keyType instanceof BuiltinType && $keyType->getTypeIdentifier() === TypeIdentifier::STRING) {
                return \sprintf('Record<string, %s>', $valueType);
            }

            return $valueType === 'any' ? 'any[]' : \sprintf('Array<%s>', $valueType);
        }

        if ($type instanceof GenericType) {
            $wrapped = $type->getWrappedType();
            $variableTypes = $type->getVariableTypes();
            if ($wrapped instanceof ObjectType && is_a($wrapped->getClassName(), \Traversable::class, true) && $variableTypes !== []) {
                return \sprintf('Array<%s>', $this->convertTypeInfo(end($variableTypes)));
            }

            return $this->convertTypeInfo($wrapped);
        }

        if ($type instanceof TemplateType) {
            return $this->convertTypeInfo($type->getBound());
        }

        if ($type instanceof EnumType) {
            return $this->convertEnum($type->getClassName());
        }

        if ($type instanceof ObjectType) {
            $class = $type->getClassName();
            if (is_a($class, \DateTimeInterface::class, true)) {
                return 'string';
            }
            if (is_a($class, \Traversable::class, true)) {
                return 'any[]';
            }

            return $this->getShortClassName($class);
        }

        if ($type instanceof BuiltinType) {
            return match ($type->getTypeIdentifier()) {
                TypeIdentifier::INT, TypeIdentifier::FLOAT => 'number',
                TypeIdentifier::STRING => 'string',
                TypeIdentifier::BOOL, TypeIdentifier::TRUE, TypeIdentifier::FALSE => 'boolean',
                TypeIdentifier::ARRAY, TypeIdentifier::ITERABLE => 'any[]',
                TypeIdentifier::OBJECT => 'Record<string, any>',
                TypeIdentifier::NULL => 'null',
                TypeIdentifier::VOID => 'void',
                TypeIdentifier::NEVER => 'never',
                default => 'any',
            };
        }

        return 'any';
    }

    /**
     * Backed enums serialize to their backing value; pure enums are rendered as a string.
     */
    private function convertEnum(string $enumClass): string
    {
        if (!is_subclass_of($enumClass, \BackedEnum::class)) {
            return 'string';
        }

        $backingType = (new \ReflectionEnum($enumClass))->getBackingType();

        return $backingType !== null && $backingType->getName() === 'int' ? 'number' : 'string';
    }

    /**
     * @param array<string, string> $typeAliases
     */
    private function convertArrayShape(string $shapeContent, array $typeAliases): string
    {
        $fields = [];

        foreach ($this->splitTopLevel($shapeContent, ',') as $token) {
            $token = trim($token);
            if ($token === '' || $token === '...') {
                continue;
            }

            if (preg_match('/^([a-zA-Z0-9_\-]+)(\??)\s*:\s*(.+)$/s', $token, $matches)) {
                $fieldType = $this->convertStringPhpType(trim($matches[3]), $typeAliases);
                $fields[] = \sprintf('%s%s: %s', $matches[1], $matches[2] === '?' ? '?' : '', $fieldType);
            }
        }

        if ($fields === []) {
            return 'Record<string, any>';
        }

        return '{ ' . implode('; ', $fields) . ' }';
    }

    /**
     * Splits $content on $separator, ignoring separators nested in <>, {} or ().
     *
     * @return string[]
     */
    private function splitTopLevel(string $content, string $separator): array
    {
        $parts = [];
        $depth = 0;
        $current = '';
        $length = \strlen($content);

        for ($i = 0; $i < $length; ++$i) {
            $char = $content[$i];
            if ($char === '{' || $char === '<' || $char === '(') {
                ++$depth;
            } elseif ($char === '}' || $char === '>' || $char === ')') {
                --$depth;
            } elseif ($char === $separator && $depth === 0) {
                $parts[] = $current;
                $current = '';
                continue;
            }
            $current .= $char;
        }

        if (trim($current) !== '') {
            $parts[] = $current;
        }

        return $parts;
    }
}
