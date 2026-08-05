<?php

namespace Codifyo\TsGeneratorBundle\Converter;

use Symfony\Component\PropertyInfo\Type;

class PhpToTypeScriptTypeConverter implements TypeConverterInterface
{
    /**
     * @param array<string, string> $typeAliases Map of AliasName => Definition (e.g. 'ClassB' => 'array{id: int}')
     */
    public function __construct(
        private array $typeAliases = []
    ) {
    }

    public function setTypeAliases(array $typeAliases): void
    {
        $this->typeAliases = $typeAliases;
    }

    public function convertType(mixed $type): string
    {
        if ($type === null) {
            return 'any';
        }

        if (is_string($type)) {
            return $this->convertStringPhpType($type);
        }

        if ($type instanceof Type) {
            return $this->convertPropertyInfoType($type);
        }

        return 'any';
    }

    public function convertStringPhpType(string $phpType): string
    {
        $phpType = trim(ltrim($phpType, '\\'));

        // Check if phpType matches a known PHPStan / Psalm type alias
        if (isset($this->typeAliases[$phpType])) {
            return $this->convertStringPhpType($this->typeAliases[$phpType]);
        }

        // Handle Array Shapes: array{id: int, name?: string} or {id: int, name: string}
        if (preg_match('/^(?:array)?\{(.+)\}$/is', $phpType, $matches)) {
            return $this->convertArrayShape($matches[1]);
        }

        // Handle Type[] syntax
        if (preg_match('/^([^\s\[\]]+)\[\]$/i', $phpType, $matches)) {
            $inner = $this->convertStringPhpType($matches[1]);
            return sprintf('Array<%s>', $inner);
        }

        // Handle array<Type> or Collection<Type> or Collection<Key, Type> syntax (allowing spaces inside <...>)
        if (preg_match('/^(?:array|collection|iterable|doctrine\\\\common\\\\collections\\\\collection)<(?:[^,>]+,\s*)?\s*([^>]+)\s*>$/i', $phpType, $matches)) {
            $inner = $this->convertStringPhpType($matches[1]);
            return sprintf('Array<%s>', $inner);
        }

        return match (strtolower($phpType)) {
            'int', 'integer', 'float', 'double' => 'number',
            'string' => 'string',
            'bool', 'boolean' => 'boolean',
            'datetime', 'datetimeimmutable', 'datetimeinterface' => 'string',
            'array', 'iterable' => 'any[]',
            'mixed' => 'any',
            'void' => 'void',
            default => $this->getShortClassName($phpType),
        };
    }

    private function convertArrayShape(string $shapeContent): string
    {
        $fields = [];
        $tokens = $this->splitShapeFields($shapeContent);

        foreach ($tokens as $token) {
            $token = trim($token);
            if (empty($token)) {
                continue;
            }

            if (preg_match('/^([a-zA-Z0-9_\-]+)(\\??)\\s*:\\s*(.+)$/s', $token, $matches)) {
                $fieldName = $matches[1];
                $isOptional = $matches[2] === '?';
                $fieldType = $this->convertStringPhpType(trim($matches[3]));

                if ($isOptional) {
                    $fields[] = sprintf('%s?: %s', $fieldName, $fieldType);
                } else {
                    $fields[] = sprintf('%s: %s', $fieldName, $fieldType);
                }
            }
        }

        if (empty($fields)) {
            return 'Record<string, any>';
        }

        return '{ ' . implode('; ', $fields) . ' }';
    }

    /**
     * @return string[]
     */
    private function splitShapeFields(string $content): array
    {
        $fields = [];
        $depth = 0;
        $current = '';

        for ($i = 0; $i < strlen($content); $i++) {
            $char = $content[$i];
            if ($char === '{' || $char === '<' || $char === '(') {
                $depth++;
                $current .= $char;
            } elseif ($char === '}' || $char === '>' || $char === ')') {
                $depth--;
                $current .= $char;
            } elseif ($char === ',' && $depth === 0) {
                $fields[] = $current;
                $current = '';
            } else {
                $current .= $char;
            }
        }

        if (trim($current) !== '') {
            $fields[] = $current;
        }

        return $fields;
    }

    private function convertPropertyInfoType(Type $type): string
    {
        $builtinType = $type->getBuiltinType();

        if ($type->isCollection()) {
            $valueTypes = $type->getCollectionValueTypes();
            if (!empty($valueTypes)) {
                $subType = $this->convertPropertyInfoType($valueTypes[0]);
                return sprintf('Array<%s>', $subType);
            }
            return 'any[]';
        }

        switch ($builtinType) {
            case Type::BUILTIN_TYPE_INT:
            case Type::BUILTIN_TYPE_FLOAT:
                return 'number';

            case Type::BUILTIN_TYPE_STRING:
                return 'string';

            case Type::BUILTIN_TYPE_BOOL:
                return 'boolean';

            case Type::BUILTIN_TYPE_ARRAY:
            case Type::BUILTIN_TYPE_ITERABLE:
                return 'any[]';

            case Type::BUILTIN_TYPE_OBJECT:
                $class = $type->getClassName();
                if ($class === null) {
                    return 'Record<string, any>';
                }
                if (is_a($class, \DateTimeInterface::class, true)) {
                    return 'string';
                }
                return $this->getShortClassName($class);

            case Type::BUILTIN_TYPE_RESOURCE:
            case Type::BUILTIN_TYPE_CALLABLE:
                return 'any';

            default:
                return 'any';
        }
    }

    public function getShortClassName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        return end($parts);
    }
}
