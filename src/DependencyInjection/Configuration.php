<?php


namespace Imanaging\CheckFormatBundle\DependencyInjection;


use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
  public function getConfigTreeBuilder() : TreeBuilder
  {
    $treeBuilder = new TreeBuilder('imanaging_check_format');
    $rootNode = $treeBuilder->getRootNode();
    $rootNode
      ->children()
        ->variableNode('project_dir')->defaultValue('%kernel.project_dir%')->end()
      ->end()
    ;

    return $treeBuilder;
  }
}
