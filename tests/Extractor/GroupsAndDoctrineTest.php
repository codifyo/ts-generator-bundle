<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Tests\Extractor;

use Codifyo\TsGeneratorBundle\Converter\PhpToTypeScriptTypeConverter;
use Codifyo\TsGeneratorBundle\Extractor\JmsSerializerMetadataExtractor;
use Codifyo\TsGeneratorBundle\Extractor\MetadataExtractorInterface;
use Codifyo\TsGeneratorBundle\Extractor\SymfonySerializerMetadataExtractor;
use Codifyo\TsGeneratorBundle\Extractor\SymfonySerializerRuntimeExtractor;
use Codifyo\TsGeneratorBundle\Model\PropertyDefinition;
use Codifyo\TsGeneratorBundle\Model\TypeConfig;
use Codifyo\TsGeneratorBundle\Model\TypeDefinition;
use Codifyo\TsGeneratorBundle\Tests\Fixtures\DummyArticle;
use Codifyo\TsGeneratorBundle\Tests\Fixtures\DummyDoctrineComment;
use Codifyo\TsGeneratorBundle\Tests\Fixtures\DummyProfile;
use Codifyo\TsGeneratorBundle\Tests\Fixtures\DummyRole;
use Codifyo\TsGeneratorBundle\Tests\Fixtures\DummyTag;
use Codifyo\TsGeneratorBundle\Tests\Fixtures\DummyUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class GroupsAndDoctrineTest extends TestCase
{
    /**
     * @return array<string, PropertyDefinition>
     */
    private function propertiesByName(TypeDefinition $definition): array
    {
        $map = [];
        foreach ($definition->getProperties() as $property) {
            $map[$property->getName()] = $property;
        }

        return $map;
    }

    public function testStaticModeDoesNotFallBackToAllPropertiesWhenNoGroupMatches(): void
    {
        $extractor = new SymfonySerializerMetadataExtractor(new ClassMetadataFactory(new AttributeLoader()), null, new PhpToTypeScriptTypeConverter());

        $this->assertSame([], $extractor->extract(new TypeConfig(DummyUser::class, ['unknown_group']))->getProperties());
        $this->assertArrayNotHasKey('secret', $this->propertiesByName($extractor->extract(new TypeConfig(DummyArticle::class, ['serializer_group']))));
    }

    public function testRuntimeModeDoesNotFallBackToAllPropertiesWhenNoGroupMatches(): void
    {
        $factory = new ClassMetadataFactory(new AttributeLoader());
        $extractor = new SymfonySerializerRuntimeExtractor(new Serializer([new ObjectNormalizer($factory)]), null, new PhpToTypeScriptTypeConverter(), $factory);

        $this->assertSame([], $extractor->extract(new TypeConfig(DummyUser::class, ['unknown_group']))->getProperties());
    }

    public function testRuntimeNormalizationFailuresAreLogged(): void
    {
        $normalizer = $this->createMock(NormalizerInterface::class);
        $normalizer->method('normalize')->willThrowException(new \RuntimeException('database unavailable'));
        $logger = new class extends AbstractLogger {
            /** @var list<string> */
            public array $messages = [];

            /**
             * Untyped $message: compatible with psr/log 1, 2 and 3.
             *
             * @param string|\Stringable   $message
             * @param array<string, mixed> $context
             */
            public function log($level, $message, array $context = []): void
            {
                $this->messages[] = $level . ': ' . strtr((string) $message, ['{class}' => $context['class'], '{message}' => $context['message']]);
            }
        };

        $extractor = new SymfonySerializerRuntimeExtractor($normalizer, null, new PhpToTypeScriptTypeConverter(), null, $logger);
        $extractor->extract(new TypeConfig(DummyUser::class));

        $this->assertCount(1, $logger->messages);
        $this->assertStringContainsString('warning: Runtime normalization of "' . DummyUser::class . '" failed', $logger->messages[0]);
        $this->assertStringContainsString('database unavailable', $logger->messages[0]);
    }

    /**
     * @return iterable<string, array{MetadataExtractorInterface}>
     */
    public static function provideExtractors(): iterable
    {
        $converter = new PhpToTypeScriptTypeConverter();
        yield 'static' => [new SymfonySerializerMetadataExtractor(new ClassMetadataFactory(new AttributeLoader()), null, $converter)];
        yield 'runtime' => [new SymfonySerializerRuntimeExtractor(null, null, $converter)];
        yield 'jms' => [new JmsSerializerMetadataExtractor(null, $converter)];
    }

    #[DataProvider('provideExtractors')]
    public function testMissingClassIsReportedByEveryExtractor(MetadataExtractorInterface $extractor): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Class "App\DoesNotExist" does not exist.');

        $extractor->extract(new TypeConfig('App\DoesNotExist'));
    }

    public function testSnakeCaseNamesAreMappedBackToCamelCaseProperties(): void
    {
        $factory = new ClassMetadataFactory(new AttributeLoader());
        $normalizer = new ObjectNormalizer($factory, new CamelCaseToSnakeCaseNameConverter());
        $extractor = new SymfonySerializerRuntimeExtractor(new Serializer([$normalizer]), null, new PhpToTypeScriptTypeConverter(), $factory);

        $props = $this->propertiesByName($extractor->extract(new TypeConfig(DummyProfile::class, ['profile'])));

        // "main_tag" has no getter: only the "mainTag" property tells its type
        $this->assertSame('DummyTag', $props['main_tag']->getTsType());
        $this->assertTrue($props['main_tag']->isNullable());
        $this->assertSame(DummyTag::class, $props['main_tag']->getReferencedClass());
    }

    public function testUntypedPropertiesAreDescribedByTheirIsHasCanAccessor(): void
    {
        $extractor = new SymfonySerializerMetadataExtractor(new ClassMetadataFactory(new AttributeLoader()), null, new PhpToTypeScriptTypeConverter());

        $types = array_map(
            static fn (PropertyDefinition $p): string => $p->getTsType(),
            $this->propertiesByName($extractor->extract(new TypeConfig(DummyProfile::class, ['profile'])))
        );

        $this->assertSame(['mainTag' => 'DummyTag', 'active' => 'boolean', 'children' => 'boolean', 'edit' => 'boolean'], $types);
    }

    public function testDoctrineRelationsTypesAndNullability(): void
    {
        $extractor = new SymfonySerializerMetadataExtractor(new ClassMetadataFactory(new AttributeLoader()), null, new PhpToTypeScriptTypeConverter());
        $props = $this->propertiesByName($extractor->extract(new TypeConfig(DummyDoctrineComment::class, ['serializer_group'])));

        // ManyToOne without JoinColumn: Doctrine defaults to a nullable column
        $this->assertSame('DummyUser', $props['author']->getTsType());
        $this->assertTrue($props['author']->isNullable());
        $this->assertSame(DummyUser::class, $props['author']->getReferencedClass());

        // JoinColumn(nullable: false)
        $this->assertSame('DummyArticle', $props['article']->getTsType());
        $this->assertFalse($props['article']->isNullable());

        // JoinColumn without "nullable": Doctrine's default (nullable) applies
        $this->assertSame('DummyUser', $props['editor']->getTsType());
        $this->assertTrue($props['editor']->isNullable());

        // OneToOne without targetEntity: inferred from the non-nullable native type
        $this->assertSame('DummyRole', $props['role']->getTsType());
        $this->assertFalse($props['role']->isNullable());
        $this->assertSame(DummyRole::class, $props['role']->getReferencedClass());

        $this->assertSame('Array<DummyTag>', $props['tags']->getTsType());
        $this->assertFalse($props['tags']->isNullable());
        $this->assertSame([DummyTag::class], $props['tags']->getReferencedClasses());
    }
}
