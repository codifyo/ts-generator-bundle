<?php

declare(strict_types=1);

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
        $propertyInfo = new PropertyInfoExtractor([$reflectionExtractor], [$reflectionExtractor], [], [$reflectionExtractor]);

        $normalizer = new ObjectNormalizer($classMetadataFactory, null, null, $propertyInfo);
        $serializer = new Serializer([$normalizer]);

        $converter = new PhpToTypeScriptTypeConverter();
        $extractor = new SymfonySerializerRuntimeExtractor($serializer, $propertyInfo, $converter);

        $typeConfig = new TypeConfig(DummyUserWithCustomGetter::class, ['serializer_group']);
        $definition = $extractor->extract($typeConfig);

        $this->assertEquals('DummyUserWithCustomGetter', $definition->getName());
        $types = [];
        foreach ($definition->getProperties() as $property) {
            $types[$property->getName()] = $property->getTsType();
        }

        // "fullName" exists only as a getter carrying #[Groups]
        $this->assertSame(['id' => 'number', 'firstName' => 'string', 'lastName' => 'string', 'fullName' => 'string'], $types);
    }
}
