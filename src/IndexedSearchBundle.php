<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch;

use Override;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Search for Doctrine entities with an index in plain database tables
 *
 * @api
 */
final class IndexedSearchBundle extends AbstractBundle
{
    /**
     * Maps the bundle's entities (the index tables) in the default entity manager, so migrations create them
     */
    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'IndexedSearchBundle' => [
                        'type' => 'attribute',
                        'dir' => __DIR__ . '/Entity',
                        'prefix' => 'Atomicptr\\Symfony\\IndexedSearch\\Entity',
                        'is_bundle' => false,
                    ],
                ],
            ],
        ]);
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        // @mago-format-ignore-start
        $definition->rootNode()->children()
            ->integerNode('min_term_length')->info('Shorter query terms are ignored.')->defaultValue(3)->min(1)->end()
            ->integerNode('max_terms')->info('Additional query terms are ignored.')->defaultValue(10)->min(1)->end()
            ->integerNode('default_per_page')->defaultValue(20)->min(1)->end()
            ->integerNode('max_per_page')->defaultValue(100)->min(1)->end()
            ->integerNode('max_count')
                ->info('Stop counting matches after this many, SearchResult::$totalIsExact tells if it did. Null counts all.')
                ->defaultValue(1000)
                ->min(1)
            ->end()
            ->integerNode('recency_half_life_days')
                ->info('Age at which SearchSort::RelevanceWithRecency halves the relevance score.')
                ->defaultValue(30)
                ->min(1)
            ->end()
        ->end();
        // @mago-format-ignore-end
    }

    /**
     * @param array<mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /**
         * Guaranteed by configure()
         *
         * @var array{
         *     min_term_length: int,
         *     max_terms: int,
         *     default_per_page: int,
         *     max_per_page: int,
         *     max_count: int|null,
         *     recency_half_life_days: int,
         * } $config
         */
        $container->import('../config/services.php');

        $services = $container->services();
        $services->get('.indexed_search.query_parser')->args([$config['min_term_length'], $config['max_terms']]);
        $services->get('.indexed_search.search_query')->arg(1, $config['recency_half_life_days']);
        $services
            ->get('indexed_search.search')
            ->arg(5, $config['default_per_page'])
            ->arg(6, $config['max_per_page'])
            ->arg(7, $config['max_count']);
    }
}
