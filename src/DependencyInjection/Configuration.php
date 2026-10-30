<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('codifyo_ts_generator');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->beforeNormalization()
                ->always(static function ($v) {
                    if (!\is_array($v)) {
                        return $v;
                    }

                    $projects = isset($v['projects']) && \is_array($v['projects']) ? $v['projects'] : $v;
                    $normalizedProjects = [];

                    foreach ($projects as $projectName => $projectData) {
                        if ($projectName === 'projects') {
                            continue;
                        }

                        if (\is_array($projectData) && array_is_list($projectData)) {
                            $merged = [];
                            foreach ($projectData as $item) {
                                if (\is_array($item)) {
                                    $merged = array_merge($merged, $item);
                                }
                            }
                            $projectData = $merged;
                        }

                        $normalizedProjects[$projectName] = $projectData;
                    }

                    return ['projects' => $normalizedProjects];
                })
            ->end()
            ->children()
                ->arrayNode('projects')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('dir')->isRequired()->cannotBeEmpty()->end()
                            ->enumNode('mode')->values(['auto', 'runtime', 'static', 'jms'])->defaultValue('auto')->end()
                            ->arrayNode('types')
                                ->isRequired()
                                ->requiresAtLeastOneElement()
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('class')->isRequired()->cannotBeEmpty()->end()
                                        ->scalarNode('name')->defaultNull()->end()
                                        ->enumNode('kind')->values(['interface', 'class', 'type'])->defaultValue('interface')->end()
                                        ->enumNode('mode')->values(['auto', 'runtime', 'static', 'jms'])->defaultNull()->end()
                                        ->booleanNode('auto_generate_deps')->defaultFalse()->end()
                                        ->variableNode('sample_id')
                                            ->info('Identifier of a real object to normalize in runtime mode (scalar, or map for composite keys)')
                                            ->defaultNull()
                                            ->validate()
                                                ->ifTrue(static fn (mixed $v): bool => !self::isValidSampleId($v))
                                                ->thenInvalid('"sample_id" must be an integer, a string or a map of field => integer|string (composite key), %s given.')
                                            ->end()
                                        ->end()
                                        ->arrayNode('groups')
                                            ->prototype('scalar')->end()
                                            ->defaultValue([])
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }

    private static function isValidSampleId(mixed $value): bool
    {
        if ($value === null || \is_int($value) || (\is_string($value) && $value !== '')) {
            return true;
        }

        if (!\is_array($value) || $value === [] || array_is_list($value)) {
            return false;
        }

        foreach ($value as $field) {
            if (!\is_int($field) && !\is_string($field)) {
                return false;
            }
        }

        return true;
    }
}
