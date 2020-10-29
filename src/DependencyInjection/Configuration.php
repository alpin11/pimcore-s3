<?php


namespace PimcoreS3Bundle\DependencyInjection;



use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * @inheritDoc
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('pimcore_s3');
        $root = $treeBuilder->getRootNode();

        $root
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('bucket_name')->cannotBeEmpty()->end()
                ->scalarNode('region')->cannotBeEmpty()->end()
                ->arrayNode('credentials')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('access_key_id')->cannotBeEmpty()->end()
                        ->scalarNode('secret_access_key')->cannotBeEmpty()->end()
                    ->end()
                ->end()
                ->arrayNode('cloudfront')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->scalarNode('base_url')->cannotBeEmpty()->end()
                        ->scalarNode('origin_path')->defaultNull()->end()
                        ->scalarNode('id')->defaultNull()->end()
                        ->arrayNode('client')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('version')->defaultValue('latest')->cannotBeEmpty()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
