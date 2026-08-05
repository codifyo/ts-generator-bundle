<?php

namespace Codifyo\TsGeneratorBundle\Tests\Extractor;

use Codifyo\TsGeneratorBundle\Converter\PhpToTypeScriptTypeConverter;
use Codifyo\TsGeneratorBundle\Extractor\SymfonySerializerMetadataExtractor;
use Codifyo\TsGeneratorBundle\Model\TypeConfig;
use Codifyo\TsGeneratorBundle\Tests\Fixtures\DummyUser;
use PHPUnit\Framework\TestCase;

class SymfonySerializerMetadataExtractorTest extends TestCase
{
    public function testExtractWithReflectionFallback(): void
    {
        $converter = new PhpToTypeScriptTypeConverter();
        $extractor = new SymfonySerializerMetadataExtractor(null, null, $converter);

        $typeConfig = new TypeConfig(DummyUser::class, ['serializer_group']);
        $definition = $extractor->extract($typeConfig);

        $this->assertEquals('DummyUser', $definition->getName());
        $this->assertEquals(DummyUser::class, $definition->getClassName());

        $props = $definition->getProperties();
        $this->assertCount(4, $props);

        $propNames = array_map(fn($p) => $p->getName(), $props);
        $this->assertContains('id', $propNames);
        $this->assertContains('username', $propNames);
        $this->assertContains('email', $propNames);
        $this->assertContains('role', $propNames);
    }
}
