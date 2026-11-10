<?php

declare(strict_types = 1);

use Atomicptr\Symfony\IndexedSearch\Tests\Fixtures\Entity\Product;
use Symfony\Component\Console\Command\Command;

it('indexes everything', function (): void {
    $this->app->entityManager()->persist(new Product('Shoe'));
    $this->app->entityManager()->flush();

    $tester = $this->app->command('indexed-search:index');

    expect($tester->execute(['--batch-size' => '10']))
        ->toBe(Command::SUCCESS)
        ->and($tester->getDisplay())
        ->toContain('Indexed 1 entities.')
        ->and($this->app->search()->search('shoe')->hits)
        ->toHaveCount(1);
});

it('rejects an invalid batch size', function (): void {
    expect($this->app->command('indexed-search:index')->execute(['--batch-size' => '0']))->toBe(Command::INVALID);
});

it('removes documents of classes that are no longer searchable', function (): void {
    $connection = $this->app->entityManager()->getConnection();
    $connection->insert('indexed_search_document', [
        'entity_class' => 'App\\Removed',
        'entity_id' => '1',
        'variant' => '',
        'sort_date' => PHP_INT_MIN,
        'content_hash' => '',
        'indexed_at' => 0,
    ]);

    $this->app->command('indexed-search:index')->execute([]);

    expect((int) $connection->fetchOne('SELECT COUNT(*) FROM indexed_search_document'))->toBe(0);
});
