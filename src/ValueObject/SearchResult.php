<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\ValueObject;

/**
 * One page of search results
 *
 * @template-covariant T of object
 *
 * @api
 */
final readonly class SearchResult
{
    /**
     * @param list<SearchHit<T>> $hits
     * @param int|null $total How many documents matched, null with count: false
     * @param bool $totalIsExact False if counting stopped at max_count, there are more than $total then
     */
    public function __construct(
        public array $hits,
        public int $page,
        public int $perPage,
        public ?int $total,
        public bool $hasNextPage,
        public bool $totalIsExact = true,
    ) {
    }

    public function totalPages(): ?int
    {
        return $this->total === null ? null : (int) ceil($this->total / $this->perPage);
    }
}
