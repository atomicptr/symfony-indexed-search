<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\ValueObject;

/**
 * The checked searchable setup of one entity class
 *
 * @internal
 */
final readonly class SearchableClass
{
    /**
     * @param class-string $class
     * @param array<string, list<string>> $classFilters Fixed filters from #[Searchable(filters: ...)], name => values
     * @param list<TextMapping> $texts
     * @param list<FilterMapping> $filters
     * @param Member|null $documents The #[SearchableDocuments] method if there is one, replaces texts and filters
     * @param Member|null $condition The #[SearchableWhen] member if there is one, false means nothing is indexed
     */
    public function __construct(
        public string $class,
        public string $idField,
        public array $classFilters,
        public array $texts,
        public array $filters,
        public ?Member $date,
        public ?Member $documents,
        public ?Member $condition,
    ) {
    }
}
