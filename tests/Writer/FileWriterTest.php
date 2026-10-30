<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Tests\Writer;

use Codifyo\TsGeneratorBundle\Writer\FileWriter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class FileWriterTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/ts_writer_test_' . uniqid();
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    public function testRelativeDirIsResolvedFromProjectDir(): void
    {
        $writer = new FileWriter(new Filesystem(), $this->tempDir);

        $path = $writer->writeFile('assets/ts/types/', 'User.ts', 'export interface User {}');

        $this->assertSame($this->tempDir . \DIRECTORY_SEPARATOR . 'assets/ts/types' . \DIRECTORY_SEPARATOR . 'User.ts', $path);
        $this->assertStringEqualsFile($path, 'export interface User {}');
    }

    public function testAbsoluteDirIsUsedAsIs(): void
    {
        $writer = new FileWriter(new Filesystem(), '/unused/project/dir');
        $absoluteDir = $this->tempDir . \DIRECTORY_SEPARATOR . 'out';

        $path = $writer->writeFile($absoluteDir, 'Role.ts', 'content');

        $this->assertSame($absoluteDir . \DIRECTORY_SEPARATOR . 'Role.ts', $path);
        $this->assertFileExists($path);
    }

    public function testExistingFileIsOverwritten(): void
    {
        $writer = new FileWriter(null, $this->tempDir);

        $writer->writeFile('out', 'Tag.ts', 'v1');
        $path = $writer->writeFile('out', 'Tag.ts', 'v2');

        $this->assertStringEqualsFile($path, 'v2');
    }
}
