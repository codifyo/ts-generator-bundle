<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Tests\Extractor;

use Codifyo\TsGeneratorBundle\Converter\PhpToTypeScriptTypeConverter;
use Codifyo\TsGeneratorBundle\Extractor\SymfonySerializerMetadataExtractor;
use Codifyo\TsGeneratorBundle\Model\TypeConfig;
use Codifyo\TsGeneratorBundle\Tests\Fixtures\DummyRole;
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

        // Without serializer metadata there are no groups: every property, typed from reflection
        $described = [];
        foreach ($definition->getProperties() as $property) {
            $described[$property->getName()] = [$property->getTsType(), $property->isNullable()];
        }

        $this->assertSame([
            'id' => ['number', false],
            'username' => ['string', false],
            'email' => ['string', true],
            'role' => ['DummyRole', true],
        ], $described);
        $this->assertSame(['DummyRole' => DummyRole::class], $definition->getImports());
    }
}
