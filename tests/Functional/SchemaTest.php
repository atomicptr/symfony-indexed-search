<?php

declare(strict_types = 1);

use Doctrine\ORM\Tools\SchemaTool;

it('maps the index tables in the entity manager and keeps them in sync', function (): void {
    $entityManager = $this->app->entityManager();
    $schemaTool = new SchemaTool($entityManager);
    $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
    $schema = $schemaTool->getSchemaFromMetadata($metadata);

    expect($schema->hasTable('indexed_search_document'))
        ->toBeTrue()
        ->and($schema->hasTable('indexed_search_word'))
        ->toBeTrue()
        ->and($schema->hasTable('indexed_search_filter'))
        ->toBeTrue()
        ->and($schemaTool->getUpdateSchemaSql($metadata))
        ->toBe([]);
});
