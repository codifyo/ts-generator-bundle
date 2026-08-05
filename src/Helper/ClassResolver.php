<?php

namespace Codifyo\TsGeneratorBundle\Helper;

class ClassResolver
{
    /**
     * Resolves a class name (short or FQCN) to its full FQCN using Reflection and file use statements.
     */
    public static function resolveFqcn(string $className, \ReflectionClass $reflection): ?string
    {
        $className = trim(ltrim($className, '\\'));

        if (empty($className)) {
            return null;
        }

        // 1. Direct FQCN match
        if (class_exists($className) || interface_exists($className) || trait_exists($className)) {
            return $className;
        }

        // 2. Same namespace match
        $sameNsCandidate = $reflection->getNamespaceName() . '\\' . $className;
        if (class_exists($sameNsCandidate) || interface_exists($sameNsCandidate) || trait_exists($sameNsCandidate)) {
            return $sameNsCandidate;
        }

        // 3. Parse use statements from file
        $fileName = $reflection->getFileName();
        if ($fileName !== false && file_exists($fileName)) {
            $useMap = self::parseUseStatements($fileName);
            if (isset($useMap[$className])) {
                $importedFqcn = $useMap[$className];
                if (class_exists($importedFqcn) || interface_exists($importedFqcn) || trait_exists($importedFqcn)) {
                    return $importedFqcn;
                }
            }
        }

        return null;
    }

    /**
     * Parses use statements from a PHP file.
     * @return array<string, string> Map of ShortName/Alias => FQCN
     */
    public static function parseUseStatements(string $filePath): array
    {
        $contents = file_get_contents($filePath);
        if ($contents === false) {
            return [];
        }

        $useMap = [];

        // Match use statements: use Foo\Bar\MyClass; or use Foo\Bar\MyClass as CustomAlias;
        if (preg_match_all('/^use\s+([^;{]+);/m', $contents, $matches)) {
            foreach ($matches[1] as $statement) {
                $statement = trim($statement);
                // Ignore function or const use statements
                if (str_starts_with($statement, 'function ') || str_starts_with($statement, 'const ')) {
                    continue;
                }

                if (preg_match('/^([^\s]+)\s+as\s+([^\s]+)$/i', $statement, $asMatches)) {
                    $fqcn = trim(ltrim($asMatches[1], '\\'));
                    $alias = trim($asMatches[2]);
                    $useMap[$alias] = $fqcn;
                } else {
                    $fqcn = trim(ltrim($statement, '\\'));
                    $parts = explode('\\', $fqcn);
                    $shortName = end($parts);
                    $useMap[$shortName] = $fqcn;
                }
            }
        }

        return $useMap;
    }
}
