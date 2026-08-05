<?php

namespace Codifyo\TsGeneratorBundle\DependencyInjection;

use Codifyo\TsGeneratorBundle\Command\GenerateTypeScriptCommand;
use Codifyo\TsGeneratorBundle\Converter\PhpToTypeScriptTypeConverter;
use Codifyo\TsGeneratorBundle\Extractor\ChainMetadataExtractor;
use Codifyo\TsGeneratorBundle\Extractor\JmsSerializerMetadataExtractor;
use Codifyo\TsGeneratorBundle\Extractor\SymfonySerializerMetadataExtractor;
use Codifyo\TsGeneratorBundle\Extractor\SymfonySerializerRuntimeExtractor;
use Codifyo\TsGeneratorBundle\Generator\TypeScriptGenerator;
use Codifyo\TsGeneratorBundle\Writer\FileWriter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

class CodifyoTsGeneratorExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('codifyo_ts_generator.projects', $config['projects'] ?? []);
        $container->setParameter('kernel.project_dir', $container->hasParameter('kernel.project_dir') ? $container->getParameter('kernel.project_dir') : getcwd());

        // Converter
        $converterDef = new Definition(PhpToTypeScriptTypeConverter::class);
        $container->setDefinition(PhpToTypeScriptTypeConverter::class, $converterDef);

        // Extractors
        $staticExtractorDef = new Definition(SymfonySerializerMetadataExtractor::class, [
            new Reference('serializer.mapping.class_metadata_factory'),
            new Reference('property_info'),
            new Reference(PhpToTypeScriptTypeConverter::class),
        ]);
        $container->setDefinition(SymfonySerializerMetadataExtractor::class, $staticExtractorDef);

        $runtimeExtractorDef = new Definition(SymfonySerializerRuntimeExtractor::class, [
            new Reference('serializer'),
            new Reference('property_info'),
            new Reference(PhpToTypeScriptTypeConverter::class),
        ]);
        $container->setDefinition(SymfonySerializerRuntimeExtractor::class, $runtimeExtractorDef);

        $jmsExtractorDef = new Definition(JmsSerializerMetadataExtractor::class, [
            $container->has('jms_serializer.metadata_factory') ? new Reference('jms_serializer.metadata_factory') : null,
            new Reference(PhpToTypeScriptTypeConverter::class),
        ]);
        $container->setDefinition(JmsSerializerMetadataExtractor::class, $jmsExtractorDef);

        // Chain Extractor
        $chainExtractorDef = new Definition(ChainMetadataExtractor::class, [
            [
                'runtime' => new Reference(SymfonySerializerRuntimeExtractor::class),
                'static' => new Reference(SymfonySerializerMetadataExtractor::class),
                'jms' => new Reference(JmsSerializerMetadataExtractor::class),
            ],
        ]);
        $container->setDefinition(ChainMetadataExtractor::class, $chainExtractorDef);

        // Writer
        $writerDef = new Definition(FileWriter::class, [
            new Reference('filesystem'),
            '%kernel.project_dir%',
        ]);
        $container->setDefinition(FileWriter::class, $writerDef);

        // Generator
        $generatorDef = new Definition(TypeScriptGenerator::class, [
            new Reference(ChainMetadataExtractor::class),
            new Reference(FileWriter::class),
            '%codifyo_ts_generator.projects%',
        ]);
        $container->setDefinition(TypeScriptGenerator::class, $generatorDef);

        // Command
        $commandDef = new Definition(GenerateTypeScriptCommand::class, [
            new Reference(TypeScriptGenerator::class),
        ]);
        $commandDef->addTag('console.command', ['command' => 'codifyo:ts-generator:generate']);
        $container->setDefinition(GenerateTypeScriptCommand::class, $commandDef);
    }
}
