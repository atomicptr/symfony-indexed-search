<?php

declare(strict_types = 1);

use Atomicptr\Symfony\IndexedSearch\Enum\Weight;
use Atomicptr\Symfony\IndexedSearch\Exception\InvalidMappingException;
use Atomicptr\Symfony\IndexedSearch\Metadata\MetadataLoader;
use Atomicptr\Symfony\IndexedSearch\Tests\Fixtures\Entity\Product;
use Atomicptr\Symfony\IndexedSearch\Tests\Fixtures\Entity\Recipe;
use Atomicptr\Symfony\IndexedSearch\Tests\Fixtures\Entity\Unsearchable;
use Atomicptr\Symfony\IndexedSearch\Tests\Fixtures\Mapping;
use Atomicptr\Symfony\IndexedSearch\ValueObject\SearchableClass;
use Atomicptr\Symfony\IndexedSearch\ValueObject\FilterMapping;
use Atomicptr\Symfony\IndexedSearch\ValueObject\TextMapping;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;

/**
 * @param class-string $class
 */
function loadMapping(string $class): ?SearchableClass
{
    $metadata = new ClassMetadata($class);
    $metadata->initializeReflection(new RuntimeReflectionService());
    new AttributeDriver([])->loadMetadataForClass($class, $metadata);
    $metadata->wakeupReflection(new RuntimeReflectionService());

    return new MetadataLoader()->load($metadata);
}

it('ignores entities without #[Searchable]', function (): void {
    expect(loadMapping(Unsearchable::class))->toBeNull();
});

it('reads texts, filters, date and class filters', function (): void {
    $mapping = loadMapping(Product::class);

    expect($mapping?->classFilters)
        ->toBe(['type' => ['product'], 'area' => ['global', 'shop']])
        ->and($mapping?->idField)
        ->toBe('id')
        ->and(array_map(static fn(TextMapping $t): array => [$t->member->name, $t->weight], $mapping->texts ?? []))
        ->toEqualCanonicalizing([
            ['name',    Weight::Highest],
            ['sku',     Weight::High],
            ['summary', Weight::Normal],
            ['body',    Weight::Low],
        ])
        ->and(array_map(
            static fn(FilterMapping $t): string => "{$t->name}={$t->member->name}",
            $mapping->filters ?? [],
        ))
        ->toEqualCanonicalizing(['category=category', 'tenant=tenantId', 'published=published', 'label=getLabels'])
        ->and($mapping?->date?->name)
        ->toBe('publishedAt')
        ->and($mapping?->documents)
        ->toBeNull();
});

it('reads a documents method', function (): void {
    $mapping = loadMapping(Recipe::class);

    expect($mapping?->classFilters)
        ->toBe(['type' => ['recipe']])
        ->and($mapping?->documents?->name)
        ->toBe('searchDocuments')
        ->and($mapping?->texts)
        ->toBe([]);
});

it('supports inheritance, private members, repeated filters and duplicate class filter values', function (): void {
    $mapping = loadMapping(Mapping\ValidChild::class);

    expect($mapping?->class)
        ->toBe(Mapping\ValidChild::class)
        ->and($mapping?->classFilters)
        ->toBe(['area' => ['one', 'two']])
        ->and($mapping?->texts[0]->member->declaringClass)
        ->toBe(Mapping\Valid::class)
        ->and(array_map(static fn(FilterMapping $t): string => $t->name, $mapping->filters ?? []))
        ->toBe(['a', 'b']);
});

it('rejects invalid mappings', function (string $class, string $message): void {
    expect(static fn() => loadMapping($class))->toThrow(InvalidMappingException::class, $message);
})->with([
    'nothing indexed' => [Mapping\NothingIndexed::class, 'nothing to index'],
    'invalid filter name' => [Mapping\InvalidFilterName::class, 'Invalid filter name "Lang Code"'],
    'documents and attributes' => [Mapping\DocumentsAndText::class, 'combines #[SearchableDocuments]'],
    'method with parameter' => [Mapping\MethodWithParameter::class, 'without required parameters'],
    'composite identifier' => [Mapping\CompositeId::class, 'composite identifier'],
]);
