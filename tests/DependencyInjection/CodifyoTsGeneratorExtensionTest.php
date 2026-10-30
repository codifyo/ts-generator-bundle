<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Tests\DependencyInjection;

use Codifyo\TsGeneratorBundle\DependencyInjection\CodifyoTsGeneratorExtension;
use Codifyo\TsGeneratorBundle\Extractor\JmsSerializerMetadataExtractor;
use Codifyo\TsGeneratorBundle\Generator\TypeScriptGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Compiles a real container, the way the kernel does, so that wiring issues
 * (services of other bundles, command registration) are caught.
 */
class CodifyoTsGeneratorExtensionTest extends TestCase
{
    private function compile(bool $withJms): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->registerExtension(new CodifyoTsGeneratorExtension());
        $container->loadFromExtension('codifyo_ts_generator', [
            'projects' => [
                'front' => ['dir' => 'assets/types', 'types' => [['class' => 'App\Entity\User']]],
            ],
        ]);

        // Registered by another bundle, i.e. NOT visible while our extension loads
        if ($withJms) {
            $container->setDefinition('jms_serializer.metadata_factory', new Definition(\ArrayObject::class));
        }

        $container->addCompilerPass(new AddConsoleCommandPass());
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                $container->getDefinition(JmsSerializerMetadataExtractor::class)->setPublic(true);
                $container->getAlias(TypeScriptGeneratorInterface::class)->setPublic(true);
            }
        }, PassConfig::TYPE_BEFORE_OPTIMIZATION);

        $container->compile();

        return $container;
    }

    public function testJmsMetadataFactoryIsInjectedWhenRegisteredByAnotherBundle(): void
    {
        $extractor = $this->compile(true)->get(JmsSerializerMetadataExtractor::class);

        $this->assertInstanceOf(\ArrayObject::class, (new \ReflectionProperty($extractor, 'jmsMetadataFactory'))->getValue($extractor));
    }

    public function testJmsMetadataFactoryIsNullWithoutJms(): void
    {
        $extractor = $this->compile(false)->get(JmsSerializerMetadataExtractor::class);

        $this->assertNull((new \ReflectionProperty($extractor, 'jmsMetadataFactory'))->getValue($extractor));
    }

    public function testContainerCompilesWithoutOptionalServicesAndRegistersTheCommand(): void
    {
        $container = $this->compile(false);

        $this->assertInstanceOf(TypeScriptGeneratorInterface::class, $container->get(TypeScriptGeneratorInterface::class));
        $this->assertTrue($container->get('console.command_loader')->has('codifyo:ts-generator:generate'));
        $this->assertSame(sys_get_temp_dir(), $container->getParameter('kernel.project_dir'));
    }
}
