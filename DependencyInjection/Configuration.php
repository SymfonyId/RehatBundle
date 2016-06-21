<?php

/*
 * This file is part of the RehatBundle package.
 *
 * (c) Muhammad Surya Ihsanuddin <surya.kejawen@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfonian\Indonesia\RehatBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @author Muhammad Surya Ihsanuddin <surya.kejawen@gmail.com>
 */
class Configuration implements ConfigurationInterface
{
    /**
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('symfonian_indonesia_rehat');

        $rootNode
            ->children()
                ->booleanNode('max_depth_check')->defaultValue(true)->end()
                ->scalarNode('date_format')->defaultValue('d-m-Y')->end()
                ->scalarNode('limit')->defaultValue(17)->end()
                ->scalarNode('prural')->defaultValue('s')->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
