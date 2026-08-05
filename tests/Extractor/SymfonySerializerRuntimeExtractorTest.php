<?php

namespace Codifyo\TsGeneratorBundle\Tests\Extractor;

use Codifyo\TsGeneratorBundle\Converter\PhpToTypeScriptTypeConverter;
use Codifyo\TsGeneratorBundle\Extractor\SymfonySerializerRuntimeExtractor;
use Codifyo\TsGeneratorBundle\Model\TypeConfig;
use Codifyo\TsGeneratorBundle\Tests\Fixtures\DummyUserWithCustomGetter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class SymfonySerializerRuntimeExtractorTest extends TestCase
{
    public function testExtractRuntimeWithCustomGetter(): void
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $reflectionExtractor = new ReflectionExtractor();
        $propertyInfo = new PropertyInfoExtractor([$reflectionExtractor], [$reflectionExtractor], [$reflectionExtractor], [$reflectionExtractor]);

        $normalizer = new ObjectNormalizer($classMetadataFactory, null, null, $propertyInfo);
        $serializer = new Serializer([$normalizer]);

        $converter = new PhpToTypeScriptTypeConverter();
        $extractor = new SymfonySerializerRuntimeExtractor($serializer, $propertyInfo, $converter);

        $typeConfig = new TypeConfig(DummyUserWithCustomGetter::class, ['serializer_group']);
        $definition = $extractor->extract($typeConfig);

        $this->assertEquals('DummyUserWithCustomGetter', $definition->getName());
        $props = $definition->getProperties();

        $propNames = array_map(fn($p) => $p->getName(), $props);
        $this->assertContains('id', $propNames);
        $this->assertContains('firstName', $propNames);
        $this->assertContains('lastName', $propNames);
        $this->assertContains('fullName', $propNames);

        foreach ($props as $p) {
            if ($p->getName() === 'fullName') {
                $this->assertEquals('string', $p->getTsType());
            }
        }
    }
}
