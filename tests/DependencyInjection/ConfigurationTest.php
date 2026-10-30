<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Tests\DependencyInjection;

use Codifyo\TsGeneratorBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    public function testProcessConfigurationWithDictStructure(): void
    {
        $processor = new Processor();
        $configuration = new Configuration();

        $config = $processor->processConfiguration($configuration, [
            'codifyo_ts_generator' => [
                'projects' => [
                    'projet1' => [
                        'dir' => 'public/ts/types',
                        'types' => [
                            ['class' => 'App\Entity\User', 'groups' => ['user_read']],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertArrayHasKey('projects', $config);
        $this->assertArrayHasKey('projet1', $config['projects']);
        $this->assertEquals('public/ts/types', $config['projects']['projet1']['dir']);
        $this->assertEquals('App\Entity\User', $config['projects']['projet1']['types'][0]['class']);
        $this->assertEquals(['user_read'], $config['projects']['projet1']['types'][0]['groups']);
    }

    public function testProcessConfigurationWithListStructure(): void
    {
        $processor = new Processor();
        $configuration = new Configuration();

        $config = $processor->processConfiguration($configuration, [
            'codifyo_ts_generator' => [
                'projet1' => [
                    ['dir' => 'public/ts/types'],
                    ['types' => [
                        ['class' => 'App\Entity\MyEntity', 'groups' => ['serializer_group']],
                    ]],
                ],
            ],
        ]);

        $this->assertArrayHasKey('projects', $config);
        $this->assertArrayHasKey('projet1', $config['projects']);
        $this->assertEquals('public/ts/types', $config['projects']['projet1']['dir']);
        $this->assertEquals('App\Entity\MyEntity', $config['projects']['projet1']['types'][0]['class']);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideValidSampleIds(): iterable
    {
        yield 'integer' => [42];
        yield 'string (uuid)' => ['0192f3a4-7b1c-7c3e-9a5d-2f4e6b8c0d1e'];
        yield 'composite key' => [['tenant' => 1, 'code' => 'FR']];
    }

    #[DataProvider('provideValidSampleIds')]
    public function testSampleIdIsAccepted(mixed $sampleId): void
    {
        $config = $this->processTypes([['class' => 'App\Entity\User', 'sample_id' => $sampleId]]);

        $this->assertSame($sampleId, $config['projects']['front']['types'][0]['sample_id']);
    }

    public function testSampleIdDefaultsToNull(): void
    {
        $this->assertNull($this->processTypes([['class' => 'App\Entity\User']])['projects']['front']['types'][0]['sample_id']);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideInvalidSampleIds(): iterable
    {
        yield 'empty string' => [''];
        yield 'list' => [[1, 2]];
        yield 'float' => [1.5];
        yield 'nested map' => [['tenant' => ['id' => 1]]];
    }

    #[DataProvider('provideInvalidSampleIds')]
    public function testInvalidSampleIdIsRejected(mixed $sampleId): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('"sample_id" must be');

        $this->processTypes([['class' => 'App\Entity\User', 'sample_id' => $sampleId]]);
    }

    /**
     * @param list<array<string, mixed>> $types
     *
     * @return array<string, mixed>
     */
    private function processTypes(array $types): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [
            'codifyo_ts_generator' => ['projects' => ['front' => ['dir' => 'assets/types', 'types' => $types]]],
        ]);
    }
}
