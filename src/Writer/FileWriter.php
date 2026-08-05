<?php

namespace Codifyo\TsGeneratorBundle\Writer;

use Symfony\Component\Filesystem\Filesystem;

class FileWriter
{
    public function __construct(
        private ?Filesystem $filesystem = null,
        private ?string $projectDir = null
    ) {
        $this->filesystem = $filesystem ?? new Filesystem();
        $this->projectDir = $projectDir ?? getcwd();
    }

    public function writeFile(string $outputDir, string $fileName, string $content): string
    {
        $targetDir = $this->resolvePath($outputDir);

        if (!$this->filesystem->exists($targetDir)) {
            $this->filesystem->mkdir($targetDir, 0755);
        }

        $filePath = $targetDir . DIRECTORY_SEPARATOR . $fileName;
        $this->filesystem->dumpFile($filePath, $content);

        return $filePath;
    }

    private function resolvePath(string $path): string
    {
        if ($this->isAbsolutePath($path)) {
            return rtrim($path, '/\\');
        }

        return rtrim($this->projectDir . DIRECTORY_SEPARATOR . ltrim($path, '/\\'), '/\\');
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || str_starts_with($path, '\\') || (strlen($path) > 1 && $path[1] === ':');
    }
}
