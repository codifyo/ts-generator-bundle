<?php

namespace Codifyo\TsGeneratorBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('codifyo_ts_generator');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->beforeNormalization()
                ->always(function ($v) {
                    if (!is_array($v)) {
                        return $v;
                    }

                    $projects = isset($v['projects']) && is_array($v['projects']) ? $v['projects'] : $v;
                    $normalizedProjects = [];

                    foreach ($projects as $projectName => $projectData) {
                        if ($projectName === 'projects') {
                            continue;
                        }

                        if (is_array($projectData) && array_is_list($projectData)) {
                            $merged = [];
                            foreach ($projectData as $item) {
                                if (is_array($item)) {
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
}
