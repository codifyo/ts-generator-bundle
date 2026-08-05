<?php

namespace Codifyo\TsGeneratorBundle\Converter;

use Symfony\Component\PropertyInfo\Type;

class PhpToTypeScriptTypeConverter implements TypeConverterInterface
{
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

    private function convertStringPhpType(string $phpType): string
    {
        $phpType = trim(ltrim($phpType, '\\'));

        // Handle Type[] syntax
        if (preg_match('/^([^\s\[\]]+)\[\]$/i', $phpType, $matches)) {
            $inner = $this->convertStringPhpType($matches[1]);
            return sprintf('Array<%s>', $inner);
        }

        // Handle array<Type> or Collection<Type> or Collection<Key, Type> syntax
        if (preg_match('/^(?:array|collection|iterable|doctrine\\\\common\\\\collections\\\\collection)<(?:[^,>]+,\s*)?([^\s>]+)>$/i', $phpType, $matches)) {
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
