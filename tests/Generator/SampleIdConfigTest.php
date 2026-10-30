<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Tests\Generator;

use Codifyo\TsGeneratorBundle\Extractor\MetadataExtractorInterface;
use Codifyo\TsGeneratorBundle\Generator\TypeScriptGenerator;
use Codifyo\TsGeneratorBundle\Model\TypeConfig;
use Codifyo\TsGeneratorBundle\Model\TypeDefinition;
use Codifyo\TsGeneratorBundle\Tests\Fixtures\DummyReport;
use Codifyo\TsGeneratorBundle\Tests\Fixtures\DummyTag;
use Codifyo\TsGeneratorBundle\Writer\FileWriter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class SampleIdConfigTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/ts_sample_config_test_' . uniqid();
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    public function testSampleIdReachesTheExtractorButIsNotInheritedByDependencies(): void
    {
        /** @var list<TypeConfig> $received */
        $received = [];
        $extractor = $this->createMock(MetadataExtractorInterface::class);
        $extractor->method('extract')->willReturnCallback(static function (TypeConfig $typeConfig) use (&$received): TypeDefinition {
            $received[] = $typeConfig;
            $definition = new TypeDefinition($typeConfig->getName(), $typeConfig->getClass());
            if ($typeConfig->getClass() === DummyReport::class) {
                $definition->addProperty(new \Codifyo\TsGeneratorBundle\Model\PropertyDefinition('tag', 'DummyTag', false, DummyTag::class));
            }

            return $definition;
        });

        $generator = new TypeScriptGenerator($extractor, new FileWriter(null, $this->tempDir), [
            'front' => ['dir' => $this->tempDir, 'types' => [
                ['class' => DummyReport::class, 'sample_id' => ['tenant' => 1, 'code' => 'A'], 'auto_generate_deps' => true],
            ]],
        ]);
        $generator->generate();

        $this->assertSame(['tenant' => 1, 'code' => 'A'], $received[0]->getSampleId());
        // The identifier belongs to DummyReport: it means nothing for the discovered DummyTag
        $this->assertSame(DummyTag::class, $received[1]->getClass());
        $this->assertNull($received[1]->getSampleId());
    }

    public function testSampleIdIsRejectedOutsideRuntimeModes(): void
    {
        $generator = new TypeScriptGenerator($this->createMock(MetadataExtractorInterface::class), new FileWriter(null, $this->tempDir), [
            'front' => ['dir' => $this->tempDir, 'mode' => 'static', 'types' => [
                ['class' => DummyReport::class, 'sample_id' => 7],
            ]],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('defines a "sample_id" but uses the "static" mode');

        $generator->generate();
    }
}
