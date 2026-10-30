<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Helper;

final class ClassResolver
{
    /**
     * @var array<string, array<string, string>> Parsed use statements, keyed by file path
     */
    private static array $useMapCache = [];

    /**
     * Resolves a class name (short or FQCN) to its full FQCN using Reflection and file use statements.
     *
     * @param \ReflectionClass<*> $reflection
     */
    public static function resolveFqcn(string $className, \ReflectionClass $reflection): ?string
    {
        $className = trim(ltrim($className, '\\'));

        if ($className === '') {
            return null;
        }

        // 1. Direct FQCN match
        if (self::exists($className)) {
            return $className;
        }

        // 2. Same namespace match
        $sameNsCandidate = $reflection->getNamespaceName() . '\\' . $className;
        if (self::exists($sameNsCandidate)) {
            return $sameNsCandidate;
        }

        // 3. Parse use statements from file (also handles "Alias\Sub" relative to an imported namespace)
        $fileName = $reflection->getFileName();
        if ($fileName !== false && file_exists($fileName)) {
            $useMap = self::$useMapCache[$fileName] ??= self::parseUseStatements($fileName);
            $parts = explode('\\', $className, 2);
            if (isset($useMap[$parts[0]])) {
                $importedFqcn = $useMap[$parts[0]] . (isset($parts[1]) ? '\\' . $parts[1] : '');
                if (self::exists($importedFqcn)) {
                    return $importedFqcn;
                }
            }
        }

        return null;
    }

    /**
     * Parses use statements from a PHP file.
     *
     * @return array<string, string> Map of ShortName/Alias => FQCN
     */
    public static function parseUseStatements(string $filePath): array
    {
        $contents = file_get_contents($filePath);
        if ($contents === false) {
            return [];
        }

        $useMap = [];

        // Match "use Foo\Bar;", "use Foo\Bar as Baz;" and grouped "use Foo\{Bar, Baz as Qux};"
        if (preg_match_all('/^use\s+([^;]+);/m', $contents, $matches)) {
            foreach ($matches[1] as $statement) {
                $statement = trim($statement);
                // Ignore function or const use statements
                if (str_starts_with($statement, 'function ') || str_starts_with($statement, 'const ')) {
                    continue;
                }

                $entries = [$statement];
                if (preg_match('/^([^{]+)\{([^}]+)\}$/', $statement, $groupMatches)) {
                    $prefix = rtrim(trim($groupMatches[1]), '\\');
                    $entries = [];
                    foreach (explode(',', $groupMatches[2]) as $entry) {
                        if (trim($entry) !== '') {
                            $entries[] = $prefix . '\\' . trim($entry);
                        }
                    }
                }

                foreach ($entries as $entry) {
                    if (preg_match('/^(\S+)\s+as\s+(\S+)$/i', trim($entry), $asMatches)) {
                        $useMap[trim($asMatches[2])] = trim(ltrim($asMatches[1], '\\'));
                    } else {
                        $fqcn = trim(ltrim(trim($entry), '\\'));
                        $parts = explode('\\', $fqcn);
                        $useMap[end($parts)] = $fqcn;
                    }
                }
            }
        }

        return $useMap;
    }

    private static function exists(string $class): bool
    {
        return class_exists($class) || interface_exists($class) || trait_exists($class) || enum_exists($class);
    }
}
