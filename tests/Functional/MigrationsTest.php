<?php

declare(strict_types = 1);

use Doctrine\Migrations\Generator\Exception\NoChangesDetected;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Filesystem;

it('integrates with doctrine:migrations:diff', function (): void {
    $migrations = $this->app->migrationsDirectory();
    new Filesystem()->remove($migrations);
    new Filesystem()->mkdir($migrations);

    foreach (['indexed_search_word', 'indexed_search_filter', 'indexed_search_document'] as $table) {
        $this->app->entityManager()->getConnection()->executeStatement("DROP TABLE {$table}");
    }

    $diff = $this->app->command('doctrine:migrations:diff');

    expect($diff->execute([]))->toBe(Command::SUCCESS);

    $files = glob("{$migrations}/*.php");
    $generated = implode('', array_map(file_get_contents(...), $files === false ? [] : $files));

    expect($generated)
        ->toContain('indexed_search_document')
        ->toContain('indexed_search_word')
        ->toContain('indexed_search_filter');

    // apply the change directly, the generated migration class can't be loaded in this process
    $entityManager = $this->app->entityManager();
    $schemaTool = new SchemaTool($entityManager);

    foreach ($schemaTool->getUpdateSchemaSql($entityManager->getMetadataFactory()->getAllMetadata()) as $statement) {
        $entityManager->getConnection()->executeStatement($statement);
    }

    expect(fn() => $this->app->command('doctrine:migrations:diff')->execute([]))->toThrow(NoChangesDetected::class);
});
