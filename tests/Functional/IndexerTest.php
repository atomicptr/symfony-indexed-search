<?php

declare(strict_types = 1);

use Atomicptr\Symfony\IndexedSearch\Exception\InvalidArgumentException;
use Atomicptr\Symfony\IndexedSearch\Tests\Fixtures\Entity\Product;
use Atomicptr\Symfony\IndexedSearch\Tests\Fixtures\Entity\Recipe;
use Atomicptr\Symfony\IndexedSearch\Tests\Fixtures\Entity\Unsearchable;

function documentCount(object $test): int
{
    return (int) $test->app->entityManager()->getConnection()->fetchOne('SELECT COUNT(*) FROM indexed_search_document');
}

it('updates documents in place', function (): void {
    $product = new Product('Shoe', labels: ['sale']);
    $this->app->store($product);
    $documentId = $this->app->entityManager()->getConnection()->fetchOne('SELECT id FROM indexed_search_document');

    $product->name = 'Boot';
    $product->labels = ['new'];
    $this->app->store($product);

    expect($this->app->search()->search('shoe')->hits)
        ->toBe([])
        ->and($this->app->search()->search('boot', filters: ['label' => 'new'])->hits)
        ->toHaveCount(1)
        ->and($this->app->search()->search('boot', filters: ['label' => 'sale'])->hits)
        ->toBe([])
        ->and($this->app->entityManager()->getConnection()->fetchOne('SELECT id FROM indexed_search_document'))
        ->toBe($documentId)
        ->and(documentCount($this))
        ->toBe(1);
});

it('keeps ids apart that only differ in case or accents', function (): void {
    $this->app->indexer()->index([new Recipe('ab', 'Apple'), new Recipe('cafe', 'Cake')]);
    $this->app->indexer()->index([new Recipe('AB', 'Banana'), new Recipe('café', 'Cookie')]);
    $search = $this->app->search();

    expect(array_map(static fn(string $query): array => ids($search->search($query)->hits), [
        'apple',
        'banana',
        'cake',
        'cookie',
    ]))->toBe([['ab:en'], ['AB:en'], ['cafe:en'], ['café:en']]);
});

it('removes entities without text', function (): void {
    $product = new Product('Shoe');
    $this->app->store($product);

    $product->name = '   ';
    $this->app->store($product);

    expect(documentCount($this))->toBe(0);
});

it('removes entities explicitly', function (): void {
    $product = new Product('Shoe');
    $this->app->store($product, new Product('Other shoe'), new Recipe('r1', 'Shoe cake', 'Schuhkuchen'));

    $this->app->indexer()->removeEntity($product);
    $this->app->indexer()->remove(Recipe::class, ['r1']);

    expect(documentCount($this))->toBe(1);
});

it('indexes by id and removes ids of deleted entities', function (): void {
    $kept = new Product('Shoe kept');
    $deleted = new Product('Shoe deleted');
    $this->app->store($kept, $deleted);
    $deletedId = $deleted->id;

    $connection = $this->app->entityManager()->getConnection();
    $connection->executeStatement("UPDATE product SET name = 'Shoe renamed' WHERE id = ?", [$kept->id]);
    $connection->executeStatement('DELETE FROM product WHERE id = ?', [$deletedId]);
    $this->app->entityManager()->clear();

    $this->app->indexer()->indexByIds(Product::class, [$kept->id, $deletedId]);

    expect(ids($this->app->search()->search('renamed')->hits))
        ->toBe([(string) $kept->id])
        ->and(documentCount($this))
        ->toBe(1);
});

it('reindexes in batches and prunes stale documents', function (): void {
    $products = array_map(static fn(int $i): Product => new Product("Shoe {$i}"), range(1, 5));
    $this->app->store(...$products);

    $connection = $this->app->entityManager()->getConnection();
    $connection->executeStatement('DELETE FROM product WHERE id = ?', [$products[0]->id]);
    $connection->executeStatement(
        "INSERT INTO product (name, category, labels, published) VALUES ('Shoe unindexed', 'shoes', '[]', true)",
    );
    $this->app->entityManager()->clear();

    $batches = [];
    $processed = $this->app->indexer()->reindex(batchSize: 2, onProgress: static function (
        string $class,
        int $count,
    ) use (&$batches): void {
        $batches[] = $count;
    });

    expect($processed)
        ->toBe(5)
        ->and($batches)
        ->toBe([2, 2, 1])
        ->and($this->app->search()->search('shoe', perPage: 10)->total)
        ->toBe(5)
        ->and($this->app->search()->search('unindexed')->hits)
        ->toHaveCount(1);
});

it('rejects entities that are not searchable', function (): void {
    $this->app->indexer()->index([new Unsearchable()]);
})->throws(InvalidArgumentException::class, 'is not a searchable entity');

it('indexes Doctrine proxies', function (): void {
    $product = new Product('Shoe');
    $this->app->store($product);
    $this->app->entityManager()->clear();

    $this->app->indexer()->index([$this->app->entityManager()->getReference(Product::class, $product->id)]);

    expect($this->app->search()->search('shoe')->hits)->toHaveCount(1);
});
