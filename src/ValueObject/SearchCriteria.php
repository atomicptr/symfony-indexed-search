<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\ValueObject;

use Atomicptr\Symfony\IndexedSearch\Enum\SearchSort;

/**
 * Search parameters after they've been checked
 *
 * @internal
 */
final readonly class SearchCriteria
{
    /**
     * @param non-empty-list<non-empty-string> $terms Every one has to match the start of a word
     * @param array<string, non-empty-list<string>> $filters Name => values, values are OR, names are AND
     * @param list<class-string>|null $classes
     */
    public function __construct(
        public array $terms,
        public array $filters,
        public ?array $classes,
        public ?int $dateFrom,
        public ?int $dateTo,
        public SearchSort $sort,
    ) {
    }
}
