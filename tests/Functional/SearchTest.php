<?php

declare(strict_types = 1);

use Atomicptr\Symfony\IndexedSearch\Enum\SearchSort;
use Atomicptr\Symfony\IndexedSearch\Exception\InvalidArgumentException;
use Atomicptr\Symfony\IndexedSearch\Tests\Fixtures\Entity\Category;
use Atomicptr\Symfony\IndexedSearch\Tests\Fixtures\Entity\Product;
use Atomicptr\Symfony\IndexedSearch\Tests\Fixtures\Entity\Recipe;

/**
 * Stores products and returns their ids by name
 *
 * @return array<string, string>
 */
function products(object $test, Product ...$products): array
{
    $test->app->store(...$products);

    return array_combine(
        array_map(static fn(Product $p): string => $p->name, $products),
        array_map(static fn(Product $p): string => (string) $p->id, $products),
    );
}

describe('matching', function (): void {
    it('requires every term to match as word prefix in any text', function (): void {
        $ids = products(
            $this,
            new Product('Red shoe', summary: 'leather'),
            new Product('Blue shoe', summary: 'red laces'),
            new Product('Red shirt'),
        );

        expect(ids($this->app->search()->search('red sho')->hits))->toEqualCanonicalizing([
            $ids['Red shoe'],
            $ids['Blue shoe'],
        ]);
    });

    it('is case insensitive and treats punctuation as separators', function (): void {
        $ids = products($this, new Product('Red shoe', sku: 'S1-SHO-0042'));

        expect(ids($this->app->search()->search('RED" -shoe*')->hits))
            ->toBe([$ids['Red shoe']])
            ->and(ids($this->app->search()->search('S1-SHO-0042')->hits))
            ->toBe([$ids['Red shoe']])
            ->and($this->app->search()->search('"red" OR -boot')->hits)
            ->toBe([]);
    });

    it('does not find filter names or values as text', function (): void {
        products($this, new Product('Shoe', category: Category::Shirts, tenantId: 1));

        expect($this->app->search()->search('tenant')->hits)
            ->toBe([])
            ->and($this->app->search()->search('shirts')->hits)
            ->toBe([]);
    });

    it('returns an empty result for queries without usable terms', function (string $query): void {
        products($this, new Product('Red shoe'));

        $result = $this->app->search()->search($query);

        expect($result->hits)->toBe([])->and($result->total)->toBe(0);
    })->with(['', '   ', '!!!', 'a']);
});

describe('ranking', function (): void {
    it('ranks higher weighted text first', function (): void {
        $ids = products(
            $this,
            new Product('Low', body: 'boots'),
            new Product('Normal', summary: 'boots'),
            new Product('Boots'),
            new Product('High', sku: 'boots'),
        );

        expect(ids($this->app->search()->search('boots')->hits))->toBe([
            $ids['Boots'],
            $ids['High'],
            $ids['Normal'],
            $ids['Low'],
        ]);
    });

    it('sorts by date with undated documents as the oldest', function (): void {
        $ids = products(
            $this,
            new Product('Shoe old', publishedAt: new DateTimeImmutable('2020-01-01')),
            new Product('Shoe undated'),
            new Product('Shoe new', publishedAt: new DateTimeImmutable('2024-01-01')),
        );

        expect(ids($this->app->search()->search('shoe', sort: SearchSort::Newest)->hits))
            ->toBe([$ids['Shoe new'], $ids['Shoe old'], $ids['Shoe undated']])
            ->and(ids($this->app->search()->search('shoe', sort: SearchSort::Oldest)->hits))
            ->toBe([$ids['Shoe undated'], $ids['Shoe old'], $ids['Shoe new']]);
    });

    it('lets recency outweigh a small relevance advantage', function (): void {
        $ids = products(
            $this,
            new Product('Shoe', publishedAt: new DateTimeImmutable('-5 years')),
            new Product('Sandal', sku: 'shoe', publishedAt: new DateTimeImmutable('-1 day')),
        );

        expect(ids($this->app->search()->search('shoe')->hits))
            ->toBe([$ids['Shoe'], $ids['Sandal']])
            ->and(ids($this->app->search()->search('shoe', sort: SearchSort::RelevanceWithRecency)->hits))
            ->toBe([$ids['Sandal'], $ids['Shoe']]);
    });

    it('decays relevance smoothly, to 2/3 at half the half-life', function (): void {
        products($this, new Product('Shoe', publishedAt: new DateTimeImmutable('-15 days')));

        expect($this->app->search()->search('shoe', sort: SearchSort::RelevanceWithRecency)->hits[0]->score)
            ->toEqualWithDelta(10 / 1.5, 0.01);
    });
});

describe('filters', function (): void {
    it('combines values of one filter with OR and different filters with AND', function (): void {
        $ids = products(
            $this,
            new Product('Shoe a', category: Category::Shoes, labels: ['sale']),
            new Product('Shoe b', category: Category::Shoes, labels: ['new']),
            new Product('Shoe c', category: Category::Shirts, labels: ['sale', 'new']),
            new Product('Shoe d', category: Category::Shoes, labels: [], published: false),
        );
        $search = $this->app->search();

        expect(ids($search->search('shoe', filters: ['label' => ['sale', 'new']])->hits))
            ->toEqualCanonicalizing([$ids['Shoe a'], $ids['Shoe b'], $ids['Shoe c']])
            ->and(ids($search->search('shoe', filters: [
                'label' => ['sale', 'new'],
                'category' => Category::Shoes,
            ])->hits))
            ->toEqualCanonicalizing([$ids['Shoe a'], $ids['Shoe b']])
            ->and(ids($search->search('shoe', filters: ['published' => false])->hits))
            ->toBe([$ids['Shoe d']])
            ->and($search->search('shoe', filters: ['label' => 'unknown'])->hits)
            ->toBe([]);
    });

    it('applies fixed class filters', function (): void {
        $ids = products($this, new Product('Shoe product'));
        $this->app->store(new Recipe('r1', 'Shoe cake'));
        $search = $this->app->search();

        expect(ids($search->search('shoe', filters: ['type' => 'product'])->hits))
            ->toBe([$ids['Shoe product']])
            ->and(ids($search->search('shoe', filters: ['type' => 'recipe'])->hits))
            ->toBe(['r1:en'])
            ->and($search->search('shoe', filters: ['type' => ['product', 'recipe']])->hits)
            ->toHaveCount(2)
            ->and(ids($search->search('shoe', filters: ['area' => 'shop'])->hits))
            ->toBe([$ids['Shoe product']])
            ->and(ids($search->search('shoe', class: Recipe::class)->hits))
            ->toBe(['r1:en']);
    });

    it('rejects invalid arguments', function (array $arguments): void {
        $this->app->search()->search('shoe', ...$arguments);
    })->with([
        'class' => [['class' => stdClass::class]],
        'filter name' => [['filters' => ['Bad Name' => 'x']]],
        'numeric filter name' => [['filters' => ['100' => 'x']]],
        'empty filter' => [['filters' => ['label' => []]]],
        'empty filter value' => [['filters' => ['label' => '']]],
        'unsupported filter value' => [['filters' => ['label' => 1.5]]],
        'page' => [['page' => 0]],
        'per page' => [['perPage' => 101]],
    ])->throws(InvalidArgumentException::class);
});

describe('variants', function (): void {
    it('indexes one document per variant with its own filters', function (): void {
        $this->app->store(
            new Recipe('r1', 'Apple pie', 'Apfelkuchen', tenantId: 1),
            new Recipe('r2', 'Apple juice', null, tenantId: 2),
        );

        expect(ids($this->app->search()->search('apfel')->hits))
            ->toBe(['r1:de'])
            ->and(ids($this->app->search()->search('apple', filters: ['lang' => 'en'])->hits))
            ->toEqualCanonicalizing(['r1:en', 'r2:en'])
            ->and(ids($this->app->search()->search('apple', filters: ['lang' => 'en', 'tenant' => 2])->hits))
            ->toBe(['r2:en']);
    });

    it('removes variants that are no longer produced', function (): void {
        $recipe = new Recipe('r1', 'Apple pie', 'Apfelkuchen');
        $this->app->store($recipe);

        $recipe->nameGerman = null;
        $this->app->store($recipe);

        expect($this->app->search()->search('apfel')->hits)
            ->toBe([])
            ->and(ids($this->app->search()->search('apple')->hits))
            ->toBe(['r1:en']);
    });
});

describe('pagination', function (): void {
    beforeEach(function (): void {
        $this->ids = products($this, ...array_map(
            static fn(int $i): Product => new Product("Shoe {$i}", publishedAt: new DateTimeImmutable("2020-01-{$i}")),
            range(1, 5),
        ));
    });

    it('pages with stable ordering and totals', function (): void {
        $search = $this->app->search();
        $pages = array_map(
            static fn(int $page) => $search->search('shoe', sort: SearchSort::Newest, page: $page, perPage: 2),
            [1, 2, 3, 4],
        );
        $ids = $this->ids;

        expect(array_map(static fn($page): array => ids($page->hits), $pages))
            ->toBe([[$ids['Shoe 5'], $ids['Shoe 4']], [$ids['Shoe 3'], $ids['Shoe 2']], [$ids['Shoe 1']], []])
            ->and(array_map(static fn($page): ?int => $page->total, $pages))
            ->toBe([5, 5, 5, 5])
            ->and(array_map(static fn($page): bool => $page->hasNextPage, $pages))
            ->toBe([true, true, false, false])
            ->and($pages[0]->totalPages())
            ->toBe(3);
    });

    it('skips counting when disabled', function (): void {
        $result = $this->app->search()->search('shoe', perPage: 2, count: false);

        expect($result->total)->toBeNull()->and($result->hasNextPage)->toBeTrue();
    });

    it('does not repeat hits across pages with equal scores', function (): void {
        $first = ids($this->app->search()->search('shoe', perPage: 3)->hits);
        $second = ids($this->app->search()->search('shoe', page: 2, perPage: 3)->hits);

        expect([...$first, ...$second])->toEqualCanonicalizing(array_values($this->ids));
    });

    it('restricts by date range, undated documents are in none', function (): void {
        products($this, new Product('Shoe undated'));
        $search = $this->app->search();

        expect(ids($search->search(
            'shoe',
            dateFrom: new DateTimeImmutable('2020-01-02'),
            dateTo: new DateTimeImmutable('2020-01-03'),
        )->hits))
            ->toEqualCanonicalizing([$this->ids['Shoe 2'], $this->ids['Shoe 3']])
            ->and(ids($search->search('shoe', dateTo: new DateTimeImmutable('2020-01-02'))->hits))
            ->toEqualCanonicalizing([$this->ids['Shoe 1'], $this->ids['Shoe 2']]);
    });

    it('stops counting at max_count but knows the total on the last page', function (): void {
        $this->app->shutdown();
        $this->app = bootApp(['max_count' => 3]);
        products($this, ...array_map(static fn(int $i): Product => new Product("Shoe {$i}"), range(1, 5)));
        $first = $this->app->search()->search('shoe', perPage: 2);
        $last = $this->app->search()->search('shoe', page: 3, perPage: 2);

        expect([$first->total, $first->totalIsExact, $last->total, $last->totalIsExact])->toBe([3, false, 5, true]);
    });
});

describe('hydration', function (): void {
    it('loads entities in result order', function (): void {
        $shoe = new Product('Shoe');
        $this->app->store($shoe, new Recipe('r1', 'Shoe cake', 'Schuhkuchen'));
        $this->app->entityManager()->clear();

        $plain = $this->app->search()->search('shoe')->hits;
        $hits = $this->app->search()->search('shoe', hydrate: true)->hits;
        $byClass = array_column(array_map(static fn($hit): array => [$hit->entityClass, $hit], $hits), 1, 0);

        expect(ids($hits))
            ->toBe(ids($plain))
            ->and(array_map(static fn($hit): string => $hit->entity::class, $hits))
            ->toBe(array_map(static fn($hit): string => $hit->entityClass, $hits))
            ->and($byClass[Product::class]->entityId)
            ->toBe((string) $shoe->id)
            ->and($byClass[Recipe::class]->variant)
            ->toBe('en');
    });

    it('drops hits whose entity no longer exists', function (): void {
        $shoe = new Product('Shoe');
        $this->app->store($shoe, new Product('Shoe 2'));
        $this->app->entityManager()->getConnection()->executeStatement('DELETE FROM product WHERE id = ?', [$shoe->id]);

        expect($this->app->search()->search('shoe', hydrate: true)->hits)
            ->toHaveCount(1)
            ->and($this->app->search()->search('shoe')->hits)
            ->toHaveCount(2);
    });
});
