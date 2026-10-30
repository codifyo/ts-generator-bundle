<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Tests\Extractor;

use Codifyo\TsGeneratorBundle\Converter\PhpToTypeScriptTypeConverter;
use Codifyo\TsGeneratorBundle\Extractor\ChainMetadataExtractor;
use Codifyo\TsGeneratorBundle\Extractor\MetadataExtractorInterface;
use Codifyo\TsGeneratorBundle\Extractor\SymfonySerializerMetadataExtractor;
use Codifyo\TsGeneratorBundle\Model\TypeConfig;
use Codifyo\TsGeneratorBundle\Model\TypeDefinition;
use Codifyo\TsGeneratorBundle\Tests\Fixtures\DummyUser;
use PHPUnit\Framework\TestCase;

class ChainMetadataExtractorTest extends TestCase
{
    public function testAutoFallbackFromRuntimeToStatic(): void
    {
        $converter = new PhpToTypeScriptTypeConverter();
        $staticExtractor = new SymfonySerializerMetadataExtractor(null, null, $converter);

        // Mock runtime extractor to simulate failure / empty result
        $failingRuntimeExtractor = $this->createMock(MetadataExtractorInterface::class);
        $failingRuntimeExtractor->method('extract')
            ->willReturn(new TypeDefinition('DummyUser', DummyUser::class));

        $chainExtractor = new ChainMetadataExtractor([
            'runtime' => $failingRuntimeExtractor,
            'static' => $staticExtractor,
        ]);

        $typeConfig = new TypeConfig(DummyUser::class, ['serializer_group']);
        $definition = $chainExtractor->extract($typeConfig, 'auto');

        $this->assertEquals('DummyUser', $definition->getName());
        $props = $definition->getProperties();
        $this->assertNotEmpty($props);

        $propNames = array_map(static fn ($p) => $p->getName(), $props);
        $this->assertContains('id', $propNames);
        $this->assertContains('username', $propNames);
    }

    public function testTypeModeTakesPrecedenceOverFallbackMode(): void
    {
        $runtime = $this->createMock(MetadataExtractorInterface::class);
        $runtime->expects($this->never())->method('extract');
        $jms = $this->createMock(MetadataExtractorInterface::class);
        $jms->expects($this->once())->method('extract')->willReturn(new TypeDefinition('DummyUser', DummyUser::class));

        $chainExtractor = new ChainMetadataExtractor(['runtime' => $runtime, 'jms' => $jms]);
        $chainExtractor->extract(new TypeConfig(DummyUser::class, [], null, 'interface', false, 'jms'), 'runtime');
    }
}
