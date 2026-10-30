<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Tests\Extractor;

use Codifyo\TsGeneratorBundle\Converter\PhpToTypeScriptTypeConverter;
use Codifyo\TsGeneratorBundle\Extractor\SymfonySerializerRuntimeExtractor;
use Codifyo\TsGeneratorBundle\Model\TypeConfig;
use Codifyo\TsGeneratorBundle\Model\TypeDefinition;
use Codifyo\TsGeneratorBundle\Sample\SampleObjectProviderInterface;
use Codifyo\TsGeneratorBundle\Tests\Fixtures\DummyReport;
use Codifyo\TsGeneratorBundle\Tests\Fixtures\DummyTag;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class RuntimeSampleTest extends TestCase
{
    private RecordingLogger $logger;

    protected function setUp(): void
    {
        $this->logger = new RecordingLogger();
    }

    private function createExtractor(?SampleObjectProviderInterface $provider): SymfonySerializerRuntimeExtractor
    {
        $factory = new ClassMetadataFactory(new AttributeLoader());
        $reflectionExtractor = new ReflectionExtractor();
        $propertyInfo = new PropertyInfoExtractor([$reflectionExtractor], [$reflectionExtractor]);

        return new SymfonySerializerRuntimeExtractor(
            new Serializer([new ObjectNormalizer($factory, null, null, $propertyInfo)]),
            $propertyInfo,
            new PhpToTypeScriptTypeConverter(),
            $factory,
            $this->logger,
            $provider
        );
    }

    /**
     * @param object|null $result the sample to return, or a \Throwable to throw
     */
    private function createProvider(?object $result): FakeSampleObjectProvider
    {
        return new FakeSampleObjectProvider($result);
    }

    /**
     * @return array<string, string>
     */
    private function tsTypes(TypeDefinition $definition): array
    {
        $types = [];
        foreach ($definition->getProperties() as $property) {
            $types[$property->getName()] = $property->getTsType();
        }

        return $types;
    }

    private function createSample(): DummyReport
    {
        return new DummyReport(
            7,
            ['count' => 3, 'labels' => ['a', 'b'], 'ratio' => 0.5],
            [
                ['date' => '2026-01-01', 'value' => 1],
                ['date' => '2026-01-02', 'value' => 2, 'note' => 'late'],
            ]
        );
    }

    public function testSampleValuesDescribeUntypedProperties(): void
    {
        $provider = $this->createProvider($this->createSample());
        $definition = $this->createExtractor($provider)->extract(new TypeConfig(DummyReport::class, ['report'], null, 'interface', false, 'runtime', 7));

        $this->assertSame([[DummyReport::class, 7]], $provider->calls);
        $this->assertSame([
            'id' => 'number',
            'stats' => '{ count: number; labels: Array<string>; ratio: number }',
            'history' => 'Array<{ date: string; value: number; note?: string }>',
        ], $this->tsTypes($definition));
    }

    public function testWithoutSampleTheSamePropertiesStayVague(): void
    {
        $definition = $this->createExtractor(null)->extract(new TypeConfig(DummyReport::class, ['report']));

        $this->assertEquals(['id' => 'number', 'stats' => 'any[]', 'history' => 'any[]'], $this->tsTypes($definition));
    }

    public function testMissingSampleFallsBackToABlankInstance(): void
    {
        $definition = $this->createExtractor($this->createProvider(null))->extract(new TypeConfig(DummyReport::class, ['report'], null, 'interface', false, 'runtime', 404));

        $this->assertSame('any[]', $this->tsTypes($definition)['stats']);
        $this->assertSame(['Sample 404 of "' . DummyReport::class . '" not found, using a blank instance.'], $this->logger->messages);
    }

    public function testSampleLoadingFailureFallsBackToABlankInstance(): void
    {
        $provider = $this->createProvider(new \RuntimeException('Connection refused'));
        $definition = $this->createExtractor($provider)->extract(new TypeConfig(DummyReport::class, ['report'], null, 'interface', false, 'runtime', ['tenant' => 1, 'code' => 'A']));

        $this->assertSame('any[]', $this->tsTypes($definition)['stats']);
        $this->assertCount(1, $this->logger->messages);
        $this->assertStringContainsString('Loading the sample {"tenant":1,"code":"A"} of "' . DummyReport::class . '" failed', $this->logger->messages[0]);
        $this->assertStringContainsString('Connection refused', $this->logger->messages[0]);
    }

    public function testSampleWithoutProviderIsAConfigurationError(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('defines a "sample_id" but no');

        $this->createExtractor(null)->extract(new TypeConfig(DummyReport::class, [], null, 'interface', false, 'runtime', 1));
    }

    public function testProviderConfigurationErrorsAreNotSwallowed(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('not managed by Doctrine');

        $provider = $this->createProvider(new \LogicException('not managed by Doctrine'));
        $this->createExtractor($provider)->extract(new TypeConfig(DummyReport::class, [], null, 'interface', false, 'runtime', 1));
    }

    public function testSampleOfAnotherClassIsRejected(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('returned a "' . DummyTag::class . '" instead of a "' . DummyReport::class . '"');

        $this->createExtractor($this->createProvider(new DummyTag()))->extract(new TypeConfig(DummyReport::class, [], null, 'interface', false, 'runtime', 1));
    }
}

final class RecordingLogger extends AbstractLogger
{
    /** @var list<string> */
    public array $messages = [];

    /**
     * @param string|\Stringable   $message
     * @param array<string, mixed> $context
     */
    public function log($level, $message, array $context = []): void
    {
        $replacements = [];
        foreach ($context as $key => $value) {
            if (\is_scalar($value)) {
                $replacements['{' . $key . '}'] = (string) $value;
            }
        }
        $this->messages[] = strtr((string) $message, $replacements);
    }
}

final class FakeSampleObjectProvider implements SampleObjectProviderInterface
{
    /** @var list<array{string, int|string|array<string, int|string>}> */
    public array $calls = [];

    /**
     * @param object|null $result the sample to return, or a \Throwable to throw
     */
    public function __construct(private ?object $result)
    {
    }

    public function find(string $className, int|string|array $id): ?object
    {
        $this->calls[] = [$className, $id];
        if ($this->result instanceof \Throwable) {
            throw $this->result;
        }

        return $this->result;
    }
}
