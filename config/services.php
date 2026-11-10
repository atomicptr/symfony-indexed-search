<?php

declare(strict_types = 1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Atomicptr\Symfony\IndexedSearch\Command\IndexCommand;
use Atomicptr\Symfony\IndexedSearch\Indexing\DocumentExtractor;
use Atomicptr\Symfony\IndexedSearch\Metadata\MetadataLoader;
use Atomicptr\Symfony\IndexedSearch\Metadata\SearchableRegistry;
use Atomicptr\Symfony\IndexedSearch\Query\EntityHydrator;
use Atomicptr\Symfony\IndexedSearch\Query\QueryParser;
use Atomicptr\Symfony\IndexedSearch\Query\SearchQuery;
use Atomicptr\Symfony\IndexedSearch\Service\SearchIndexer;
use Atomicptr\Symfony\IndexedSearch\Service\SearchService;
use Atomicptr\Symfony\IndexedSearch\Service\DocumentStore;
use Symfony\Component\Clock\Clock;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('.indexed_search.clock', Clock::class);

    $services->set('.indexed_search.metadata_loader', MetadataLoader::class);

    $services->set('.indexed_search.registry', SearchableRegistry::class)
        ->args([service('doctrine.orm.entity_manager'), service('.indexed_search.metadata_loader')]);

    $services->set('.indexed_search.extractor', DocumentExtractor::class);

    $services->set('.indexed_search.store', DocumentStore::class)
        ->args([service('database_connection')]);

    $services->set('.indexed_search.query_parser', QueryParser::class)
        ->args([abstract_arg('min term length'), abstract_arg('max terms')]);

    $services->set('.indexed_search.search_query', SearchQuery::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            abstract_arg('recency half-life in days'),
        ]);

    $services->set('.indexed_search.hydrator', EntityHydrator::class)
        ->args([service('doctrine.orm.entity_manager'), service('.indexed_search.registry')]);

    $services
        ->set('indexed_search.search', SearchService::class)
        ->args([
            service('.indexed_search.registry'),
            service('.indexed_search.query_parser'),
            service('.indexed_search.search_query'),
            service('.indexed_search.hydrator'),
            service('.indexed_search.clock'),
            abstract_arg('default per page'),
            abstract_arg('max per page'),
            abstract_arg('max count'),
        ])
        ->public();
    $services->alias(SearchService::class, 'indexed_search.search')->public();

    $services
        ->set('indexed_search.indexer', SearchIndexer::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service('.indexed_search.registry'),
            service('.indexed_search.extractor'),
            service('.indexed_search.store'),
            service('.indexed_search.clock'),
        ])
        ->public();
    $services->alias(SearchIndexer::class, 'indexed_search.indexer')->public();

    $services
        ->set('.indexed_search.command.index', IndexCommand::class)
        ->args([service('indexed_search.indexer')])
        ->tag('console.command');
};
