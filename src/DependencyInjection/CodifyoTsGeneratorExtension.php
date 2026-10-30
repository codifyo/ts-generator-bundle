<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\DependencyInjection;

use Codifyo\TsGeneratorBundle\Command\GenerateTypeScriptCommand;
use Codifyo\TsGeneratorBundle\Converter\PhpToTypeScriptTypeConverter;
use Codifyo\TsGeneratorBundle\Converter\TypeConverterInterface;
use Codifyo\TsGeneratorBundle\Extractor\ChainMetadataExtractor;
use Codifyo\TsGeneratorBundle\Extractor\JmsSerializerMetadataExtractor;
use Codifyo\TsGeneratorBundle\Extractor\SymfonySerializerMetadataExtractor;
use Codifyo\TsGeneratorBundle\Extractor\SymfonySerializerRuntimeExtractor;
use Codifyo\TsGeneratorBundle\Generator\TypeScriptGenerator;
use Codifyo\TsGeneratorBundle\Generator\TypeScriptGeneratorInterface;
use Codifyo\TsGeneratorBundle\Writer\FileWriter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

final class CodifyoTsGeneratorExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('codifyo_ts_generator.projects', $config['projects'] ?? []);

        // Services of other bundles are not visible while extensions load: optional ones are
        // referenced with NULL_ON_INVALID_REFERENCE and resolved once the container compiles.
        $optional = static fn (string $id): Reference => new Reference($id, ContainerInterface::NULL_ON_INVALID_REFERENCE);

        $container->setDefinition(PhpToTypeScriptTypeConverter::class, new Definition(PhpToTypeScriptTypeConverter::class));
        $container->setAlias(TypeConverterInterface::class, PhpToTypeScriptTypeConverter::class);

        $container->setDefinition(SymfonySerializerMetadataExtractor::class, new Definition(SymfonySerializerMetadataExtractor::class, [
            $optional('serializer.mapping.class_metadata_factory'),
            $optional('property_info'),
            new Reference(TypeConverterInterface::class),
        ]));

        $container->setDefinition(SymfonySerializerRuntimeExtractor::class, new Definition(SymfonySerializerRuntimeExtractor::class, [
            $optional('serializer'),
            $optional('property_info'),
            new Reference(TypeConverterInterface::class),
            $optional('serializer.mapping.class_metadata_factory'),
            $optional('logger'),
        ]));

        $container->setDefinition(JmsSerializerMetadataExtractor::class, new Definition(JmsSerializerMetadataExtractor::class, [
            $optional('jms_serializer.metadata_factory'),
            new Reference(TypeConverterInterface::class),
        ]));

        $container->setDefinition(ChainMetadataExtractor::class, new Definition(ChainMetadataExtractor::class, [
            [
                'runtime' => new Reference(SymfonySerializerRuntimeExtractor::class),
                'static' => new Reference(SymfonySerializerMetadataExtractor::class),
                'jms' => new Reference(JmsSerializerMetadataExtractor::class),
            ],
        ]));

        $container->setDefinition(FileWriter::class, new Definition(FileWriter::class, [
            new Reference('filesystem', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            '%kernel.project_dir%',
        ]));

        $container->setDefinition(TypeScriptGenerator::class, new Definition(TypeScriptGenerator::class, [
            new Reference(ChainMetadataExtractor::class),
            new Reference(FileWriter::class),
            '%codifyo_ts_generator.projects%',
        ]));
        $container->setAlias(TypeScriptGeneratorInterface::class, TypeScriptGenerator::class);

        // The command name comes from its #[AsCommand] attribute
        $container->setDefinition(GenerateTypeScriptCommand::class, (new Definition(GenerateTypeScriptCommand::class, [
            new Reference(TypeScriptGeneratorInterface::class),
        ]))->addTag('console.command'));
    }
}
