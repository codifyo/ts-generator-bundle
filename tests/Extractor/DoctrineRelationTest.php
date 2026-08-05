<?php

namespace Codifyo\TsGeneratorBundle\Tests\Extractor;

use Codifyo\TsGeneratorBundle\Converter\PhpToTypeScriptTypeConverter;
use Codifyo\TsGeneratorBundle\Extractor\SymfonySerializerRuntimeExtractor;
use Codifyo\TsGeneratorBundle\Model\TypeConfig;
use Codifyo\TsGeneratorBundle\Tests\Fixtures\DummyPostWithRelation;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class DoctrineRelationTest extends TestCase
{
    public function testDoctrineRelationCollectionInterpretation(): void
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $normalizer = new ObjectNormalizer($classMetadataFactory);
        $serializer = new Serializer([$normalizer]);

        $converter = new PhpToTypeScriptTypeConverter();
        $extractor = new SymfonySerializerRuntimeExtractor($serializer, null, $converter);

        $typeConfig = new TypeConfig(DummyPostWithRelation::class, ['serializer_group']);
        $definition = $extractor->extract($typeConfig);

        $props = $definition->getProperties();

        $tagsProp = null;
        foreach ($props as $p) {
            if ($p->getName() === 'tags') {
                $tagsProp = $p;
                break;
            }
        }

        $this->assertNotNull($tagsProp);
        $this->assertEquals('Array<DummyTag>', $tagsProp->getTsType());
        $this->assertEquals('Codifyo\TsGeneratorBundle\Tests\Fixtures\DummyTag', $tagsProp->getReferencedClass());
    }
}
