<?php

namespace Codifyo\TsGeneratorBundle\Tests\Extractor;

use Codifyo\TsGeneratorBundle\Converter\PhpToTypeScriptTypeConverter;
use Codifyo\TsGeneratorBundle\Extractor\SymfonySerializerRuntimeExtractor;
use Codifyo\TsGeneratorBundle\Model\TypeConfig;
use Codifyo\TsGeneratorBundle\Tests\Fixtures\DummyClassWithPhpstanAlias;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class PhpstanTypeAliasAndGetterTest extends TestCase
{
    public function testCustomGetterWithCollectionGenericsAndPhpstanAlias(): void
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $reflectionExtractor = new ReflectionExtractor();
        $propertyInfo = new PropertyInfoExtractor([$reflectionExtractor], [$reflectionExtractor], [$reflectionExtractor], [$reflectionExtractor]);

        $normalizer = new ObjectNormalizer($classMetadataFactory, null, null, $propertyInfo);
        $serializer = new Serializer([$normalizer]);

        $converter = new PhpToTypeScriptTypeConverter();
        $extractor = new SymfonySerializerRuntimeExtractor($serializer, $propertyInfo, $converter, $classMetadataFactory);

        $typeConfig = new TypeConfig(DummyClassWithPhpstanAlias::class, ['serializer_group']);
        $definition = $extractor->extract($typeConfig);

        $props = $definition->getProperties();
        $propMap = [];
        foreach ($props as $p) {
            $propMap[$p->getName()] = $p;
        }

        $this->assertArrayHasKey('tags', $propMap);
        $this->assertArrayHasKey('customItems', $propMap);

        // Case 1: Collection<int, DummyTag> should resolve to Array<DummyTag>
        $this->assertEquals('Array<DummyTag>', $propMap['tags']->getTsType());
        $this->assertEquals('Codifyo\TsGeneratorBundle\Tests\Fixtures\DummyTag', $propMap['tags']->getReferencedClass());

        // Case 2: array<ClassB> where ClassB is array{id: int, name: string, active?: bool}
        $this->assertEquals('Array<{ id: number; name: string; active?: boolean }>', $propMap['customItems']->getTsType());
    }
}
