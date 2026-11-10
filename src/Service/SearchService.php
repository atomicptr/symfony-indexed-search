<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Service;

use Atomicptr\Symfony\IndexedSearch\Enum\SearchSort;
use Atomicptr\Symfony\IndexedSearch\Exception\InvalidArgumentException;
use Atomicptr\Symfony\IndexedSearch\Indexing\FilterNormalizer;
use Atomicptr\Symfony\IndexedSearch\Metadata\SearchableRegistry;
use Atomicptr\Symfony\IndexedSearch\Query\EntityHydrator;
use Atomicptr\Symfony\IndexedSearch\Query\QueryParser;
use Atomicptr\Symfony\IndexedSearch\Query\SearchQuery;
use Atomicptr\Symfony\IndexedSearch\ValueObject\SearchCriteria;
use Atomicptr\Symfony\IndexedSearch\ValueObject\SearchResult;
use DateTimeInterface;
use Psr\Clock\ClockInterface;
use Stringable;
use UnitEnum;

/**
 * Searches the index
 *
 * @api
 */
final readonly class SearchService
{
    public function __construct(
        private SearchableRegistry $registry,
        private QueryParser $parser,
        private SearchQuery $query,
        private EntityHydrator $hydrator,
        private ClockInterface $clock,
        private int $defaultPerPage,
        private int $maxPerPage,
        private ?int $maxCount,
    ) {
    }

    /**
     * Every word has to match the start of a word in the document and every filter has to match
     *
     * @param array<string, string|int|bool|Stringable|UnitEnum|list<string|int|bool|Stringable|UnitEnum>> $filters
     *     name => value(s). Values of the same name are OR, different names are AND, so
     *     ['tenant' => 42, 'lang' => ['de', 'en']] means tenant 42 in German or English
     * @param class-string<T>|list<class-string<T>>|null $class Only these entity classes, also types the hits
     * @param int $page Starts at 1
     * @param int|null $perPage Up to max_per_page, null uses default_per_page
     * @param bool $hydrate Load the entities too
     * @param bool $count Count all matches, turn it off for search-as-you-type
     *
     * @return ($class is null ? SearchResult<object> : SearchResult<T>)
     *
     * @throws InvalidArgumentException
     *
     * @template T of object
     */
    public function search(
        string $query,
        array $filters = [],
        string|array|null $class = null,
        ?DateTimeInterface $dateFrom = null,
        ?DateTimeInterface $dateTo = null,
        SearchSort $sort = SearchSort::Relevance,
        int $page = 1,
        ?int $perPage = null,
        bool $hydrate = false,
        bool $count = true,
    ): SearchResult {
        $perPage ??= $this->defaultPerPage;

        if ($page < 1 || $perPage < 1 || $perPage > $this->maxPerPage) {
            throw new InvalidArgumentException("Page must be >= 1 and perPage between 1 and {$this->maxPerPage}.");
        }

        $filters = $this->filters($filters);
        $terms = $this->parser->parse($query);

        if (count($terms) === 0) {
            return new SearchResult([], $page, $perPage, $count ? 0 : null, false);
        }

        $criteria = new SearchCriteria(
            terms: $terms,
            filters: $filters,
            classes: $this->classes($class),
            dateFrom: $dateFrom?->getTimestamp(),
            dateTo: $dateTo?->getTimestamp(),
            sort: $sort,
        );

        // fetch one extra row so we know if there's a next page without counting
        $fetched = $this->query->fetchPage(
            $criteria,
            $perPage + 1,
            ($page - 1) * $perPage,
            $this->clock->now()->getTimestamp(),
        );
        $hasNextPage = count($fetched) > $perPage;
        $hits = array_slice($fetched, 0, $perPage);

        // no need to count if this page already shows the last results
        $onLastPage = !$hasNextPage && (count($hits) > 0 || $page === 1);
        $total = match (true) {
            !$count => null,
            $onLastPage => (($page - 1) * $perPage) + count($hits),
            default => $this->query->count($criteria, $this->maxCount),
        };
        $capped = !$onLastPage && $total !== null && $this->maxCount !== null && $total > $this->maxCount;
        $shown = (($page - 1) * $perPage) + count($hits) + ($hasNextPage ? 1 : 0);

        return new SearchResult(
            $hydrate ? $this->hydrator->hydrate($hits) : $hits,
            $page,
            $perPage,
            $capped ? max($this->maxCount, $shown) : $total,
            $hasNextPage,
            totalIsExact: !$capped,
        );
    }

    /**
     * @param array<mixed> $filters
     *
     * @return array<string, non-empty-list<string>> Name => values
     */
    private function filters(array $filters): array
    {
        $normalized = [];

        foreach ($filters as $name => $values) {
            $values = is_array($values) ? $values : [$values];

            if (count($values) === 0) {
                throw new InvalidArgumentException("Filter \"{$name}\" requires at least one value.");
            }

            $name = FilterNormalizer::name((string) $name);
            $normalized[$name] = array_values(array_unique(array_map(
                static fn(mixed $value): string => FilterNormalizer::value($name, $value),
                $values,
            )));
        }

        return $normalized;
    }

    /**
     * @param string|list<string>|null $class
     *
     * @return list<class-string>|null
     */
    private function classes(string|array|null $class): ?array
    {
        if ($class === null) {
            return null;
        }

        return array_values(array_unique(array_map(
            fn(string $class): string => $this->registry->get($class)->class,
            is_string($class) ? [$class] : $class,
        )));
    }
}
