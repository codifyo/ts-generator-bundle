<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Extractor;

use Codifyo\TsGeneratorBundle\Converter\TypeConverterInterface;
use Codifyo\TsGeneratorBundle\Helper\ClassResolver;
use Codifyo\TsGeneratorBundle\Helper\TypeReferenceCollector;
use Codifyo\TsGeneratorBundle\Model\PropertyDefinition;
use Codifyo\TsGeneratorBundle\Model\TypeDefinition;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolverInterface;

/**
 * Shared type resolution for the Symfony Serializer based extractors.
 *
 * Sources are tried in order, stopping at the first one giving a specific type:
 * Doctrine relation attributes, PropertyInfo, property (native type + "@var"),
 * getter (return type + "@return"), and finally the runtime normalized value.
 */
abstract class AbstractSerializerMetadataExtractor implements MetadataExtractorInterface
{
    /** TS types too vague to stop the resolution chain */
    private const GENERIC_TS_TYPES = ['any', 'any[]', 'Collection', 'null'];

    private const DOCTRINE_TO_MANY = ['Doctrine\ORM\Mapping\OneToMany', 'Doctrine\ORM\Mapping\ManyToMany'];
    private const DOCTRINE_TO_ONE = ['Doctrine\ORM\Mapping\ManyToOne', 'Doctrine\ORM\Mapping\OneToOne'];
    private const DOCTRINE_JOIN_COLUMN = 'Doctrine\ORM\Mapping\JoinColumn';

    private TypeResolverInterface $typeResolver;

    public function __construct(
        protected ?PropertyInfoExtractorInterface $propertyInfoExtractor,
        protected TypeConverterInterface $typeConverter,
    ) {
        $this->typeResolver = TypeResolver::create();
    }

    /**
     * @return \ReflectionClass<object>
     */
    protected function reflectClass(string $className): \ReflectionClass
    {
        if (!class_exists($className)) {
            throw new \InvalidArgumentException(\sprintf('Class "%s" does not exist.', $className));
        }

        return new \ReflectionClass($className);
    }

    /**
     * @param \ReflectionClass<object> $reflection
     * @param array<string, string>    $typeAliases
     */
    protected function addProperty(
        TypeDefinition $typeDefinition,
        \ReflectionClass $reflection,
        string $serializedName,
        ?string $propertyName,
        array $typeAliases,
        mixed $runtimeValue = null,
    ): void {
        $acc = new ResolvedTypeAccumulator();
        $property = $propertyName !== null && $reflection->hasProperty($propertyName) ? $reflection->getProperty($propertyName) : null;

        if ($property !== null) {
            $this->resolveDoctrineRelation($property, $acc);
        }

        if (!$this->hasSpecificType($acc) && $propertyName !== null) {
            $this->resolveFromPropertyInfo($reflection->getName(), $propertyName, $acc);
        }

        if (!$this->hasSpecificType($acc) && $property !== null) {
            $this->resolveFromReflection($property, $acc);
            $this->resolveFromDocBlock($property->getDocComment(), '@var', $reflection, $typeAliases, $acc);
        }

        if (!$this->hasSpecificType($acc)) {
            $getter = $this->resolveGetterMethod($reflection, $propertyName ?? $serializedName)
                ?? $this->resolveGetterMethod($reflection, $serializedName);
            if ($getter !== null) {
                $this->resolveFromReflection($getter, $acc);
                $this->resolveFromDocBlock($getter->getDocComment(), '@return', $reflection, $typeAliases, $acc);
            }
        }

        if ($acc->types === [] && $runtimeValue !== null) {
            $acc->types[] = match (true) {
                \is_int($runtimeValue), \is_float($runtimeValue) => 'number',
                \is_string($runtimeValue) => 'string',
                \is_bool($runtimeValue) => 'boolean',
                \is_array($runtimeValue) => array_is_list($runtimeValue) ? 'any[]' : 'Record<string, any>',
                default => 'any',
            };
        }

        $typeDefinition->addProperty(new PropertyDefinition($serializedName, $this->finalizeTsType($acc), $acc->nullable, $acc->references));

        foreach ($acc->references as $referencedClass) {
            $typeDefinition->addImport($this->typeConverter->getShortClassName($referencedClass), $referencedClass);
        }
    }

    /**
     * @param \ReflectionClass<object> $reflection
     *
     * @return array<string, string>
     */
    protected function parseClassTypeAliases(\ReflectionClass $reflection): array
    {
        $aliases = [];
        $doc = $reflection->getDocComment();
        if ($doc !== false && preg_match_all('/@(?:phpstan|psalm)-type\s+([A-Za-z0-9_]+)\s*=?\s*(.+?)(?:\s+\*\/|\s*[\r\n]|$)/m', $doc, $matches, \PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $aliases[trim($match[1])] = trim($match[2]);
            }
        }

        return $aliases;
    }

    /**
     * @param \ReflectionClass<object> $reflection
     */
    protected function resolvePropertyName(\ReflectionClass $reflection, string $serializedName): ?string
    {
        if ($reflection->hasProperty($serializedName)) {
            return $serializedName;
        }

        $camel = lcfirst(str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $serializedName))));

        return $reflection->hasProperty($camel) ? $camel : null;
    }

    /**
     * @param \ReflectionClass<object> $reflection
     */
    protected function resolveGetterMethod(\ReflectionClass $reflection, string $name): ?\ReflectionMethod
    {
        $studly = ucfirst(str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $name))));

        foreach (['get' . $studly, 'is' . $studly, 'has' . $studly, 'can' . $studly, $name] as $methodName) {
            if ($reflection->hasMethod($methodName)) {
                $method = $reflection->getMethod($methodName);
                if ($method->isPublic() && !$method->isStatic() && $method->getNumberOfRequiredParameters() === 0) {
                    return $method;
                }
            }
        }

        return null;
    }

    private function resolveDoctrineRelation(\ReflectionProperty $property, ResolvedTypeAccumulator $acc): void
    {
        foreach ($property->getAttributes() as $attribute) {
            $attributeName = $attribute->getName();
            $isToMany = \in_array($attributeName, self::DOCTRINE_TO_MANY, true);
            $isToOne = \in_array($attributeName, self::DOCTRINE_TO_ONE, true);
            if (!$isToMany && !$isToOne) {
                continue;
            }

            $args = $attribute->getArguments();
            // Positional order differs between ORM 2 and 3 for OneToMany: try every candidate
            $candidates = [$args['targetEntity'] ?? null, $args[0] ?? null, $args[1] ?? null];
            $nativeType = $property->getType();
            if ($isToOne && $nativeType instanceof \ReflectionNamedType && !$nativeType->isBuiltin()) {
                $candidates[] = $nativeType->getName();
            }

            $target = null;
            foreach ($candidates as $candidate) {
                if (\is_string($candidate) && ($target = ClassResolver::resolveFqcn($candidate, $property->getDeclaringClass())) !== null) {
                    break;
                }
            }

            if ($target === null) {
                return;
            }

            $shortName = $this->typeConverter->getShortClassName($target);
            $acc->types[] = $isToMany ? \sprintf('Array<%s>', $shortName) : $shortName;
            $acc->references[] = $target;

            if ($isToOne && $this->isToOneRelationNullable($property, $args)) {
                $acc->nullable = true;
            }

            return;
        }
    }

    /**
     * A to-one relation is nullable unless the PHP type forbids null or the join column is NOT NULL
     * (Doctrine's JoinColumn defaults to nullable: true). Inverse sides are always nullable.
     *
     * @param array<int|string, mixed> $relationArgs
     */
    private function isToOneRelationNullable(\ReflectionProperty $property, array $relationArgs): bool
    {
        $nativeType = $property->getType();
        if ($nativeType !== null && !$nativeType->allowsNull()) {
            return false;
        }

        if (isset($relationArgs['mappedBy'])) {
            return true;
        }

        foreach ($property->getAttributes(self::DOCTRINE_JOIN_COLUMN) as $joinColumn) {
            $joinArgs = $joinColumn->getArguments();

            return (bool) ($joinArgs['nullable'] ?? $joinArgs[3] ?? true);
        }

        return true;
    }

    private function resolveFromPropertyInfo(string $className, string $propertyName, ResolvedTypeAccumulator $acc): void
    {
        if ($this->propertyInfoExtractor === null) {
            return;
        }

        $type = $this->propertyInfoExtractor->getType($className, $propertyName);
        if ($type instanceof Type) {
            $this->addTypeInfo($type, $acc);
        }
    }

    private function resolveFromReflection(\ReflectionProperty|\ReflectionMethod $subject, ResolvedTypeAccumulator $acc): void
    {
        $hasType = $subject instanceof \ReflectionProperty ? $subject->hasType() : $subject->hasReturnType();
        if (!$hasType) {
            return;
        }

        try {
            $this->addTypeInfo($this->typeResolver->resolve($subject), $acc);
        } catch (\Throwable) {
            // Unsupported native type: other sources will be tried
        }
    }

    private function addTypeInfo(Type $type, ResolvedTypeAccumulator $acc): void
    {
        if ($type->isNullable()) {
            $acc->nullable = true;
        }

        $acc->types[] = $this->typeConverter->convertType($type);
        array_push($acc->references, ...TypeReferenceCollector::fromTypeInfo($type));
    }

    /**
     * @param \ReflectionClass<object> $reflection
     * @param array<string, string>    $typeAliases
     */
    private function resolveFromDocBlock(string|false $docComment, string $tag, \ReflectionClass $reflection, array $typeAliases, ResolvedTypeAccumulator $acc): void
    {
        if ($docComment === false) {
            return;
        }

        $docType = $this->extractDocType($docComment, $tag);
        if ($docType === null || $docType === '') {
            return;
        }

        if (str_starts_with($docType, '?') || preg_match('/(?:^|\|)\s*null\s*(?:\||$)/i', $docType)) {
            $acc->nullable = true;
        }

        $acc->types[] = $this->typeConverter->convertType($docType, $typeAliases);
        array_push($acc->references, ...TypeReferenceCollector::fromString($docType, $reflection, $typeAliases));
    }

    /**
     * Returns the type following $tag, keeping generics / shapes (which may contain spaces) intact.
     */
    private function extractDocType(string $docComment, string $tag): ?string
    {
        if (!preg_match('/' . preg_quote($tag, '/') . '\s+(.*)$/m', $docComment, $matches)) {
            return null;
        }

        $raw = preg_replace('/\s*\*\/\s*$/', '', $matches[1]) ?? $matches[1];
        $depth = 0;
        $type = '';
        $length = \strlen($raw);

        for ($i = 0; $i < $length; ++$i) {
            $char = $raw[$i];
            if ($char === '<' || $char === '{' || $char === '(') {
                ++$depth;
            } elseif ($char === '>' || $char === '}' || $char === ')') {
                --$depth;
            } elseif ($depth === 0 && ctype_space($char)) {
                break;
            }
            $type .= $char;
        }

        return trim($type);
    }

    private function hasSpecificType(ResolvedTypeAccumulator $acc): bool
    {
        foreach ($acc->types as $type) {
            if (!\in_array($type, self::GENERIC_TS_TYPES, true)) {
                return true;
            }
        }

        return false;
    }

    private function finalizeTsType(ResolvedTypeAccumulator $acc): string
    {
        $types = array_values(array_unique($acc->types));
        $specific = array_values(array_filter($types, static fn (string $t) => !\in_array($t, self::GENERIC_TS_TYPES, true)));

        if ($specific !== []) {
            $types = $specific;
        } else {
            $types = array_values(array_filter($types, static fn (string $t) => $t !== 'null' && $t !== 'Collection'));
        }

        return $types === [] ? 'any' : implode(' | ', $types);
    }
}
