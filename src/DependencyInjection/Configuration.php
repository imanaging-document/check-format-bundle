<?php


namespace Imanaging\CheckFormatBundle\DependencyInjection;


use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
  public function getConfigTreeBuilder()
  {
    $treeBuilder = new TreeBuilder();
    $rootNode = $treeBuilder->root('imanaging_check_format');
    $rootNode
      ->children()
        ->booleanNode('bar')->defaultTrue()->info('Test parameter')->end()
      ->end()
    ;

    return $treeBuilder;
  }
}