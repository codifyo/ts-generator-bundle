<?php

namespace Codifyo\TsGeneratorBundle\Tests\DependencyInjection;

use Codifyo\TsGeneratorBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
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
}
