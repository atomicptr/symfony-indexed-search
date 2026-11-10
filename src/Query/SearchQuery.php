<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Query;

use Atomicptr\Symfony\IndexedSearch\Entity\SearchDocument;
use Atomicptr\Symfony\IndexedSearch\Entity\SearchFilter;
use Atomicptr\Symfony\IndexedSearch\Entity\SearchWord;
use Atomicptr\Symfony\IndexedSearch\Enum\SearchSort;
use Atomicptr\Symfony\IndexedSearch\ValueObject\SearchCriteria;
use Atomicptr\Symfony\IndexedSearch\ValueObject\SearchHit;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use SortDirection;

/**
 * Builds and runs the search queries
 *
 * @internal
 */
final readonly class SearchQuery
{
    private const int SECONDS_PER_DAY = 86_400;

    /** Sorts after every UTF-8 string, a word starts with a prefix if it's >= prefix and < prefix + this */
    private const string AFTER_UTF8 = "\xFF";

    public function __construct(
        private EntityManagerInterface $entityManager,
        private int $recencyHalfLifeDays,
    ) {
    }

    /**
     * @return list<SearchHit<object>>
     */
    public function fetchPage(SearchCriteria $criteria, int $limit, int $offset, int $now): array
    {
        $scores = match ($criteria->sort) {
            SearchSort::Newest, SearchSort::Oldest => $this->byDate($criteria, $limit, $offset),
            SearchSort::Relevance, SearchSort::RelevanceWithRecency => $this->byRelevance(
                $criteria,
                $limit,
                $offset,
                $now,
            ),
        };
        $documents = $this->documents(array_keys($scores));
        $hits = [];

        foreach ($scores as $id => $score) {
            // gone if it was removed between both queries
            $document = $documents[$id] ?? null;

            if ($document === null) {
                continue;
            }

            /** @var class-string $class only searchable entity classes get indexed */
            $class = $document->entityClass;
            $hits[] = new SearchHit(
                entityClass: $class,
                entityId: $document->entityId,
                variant: $document->variant,
                date: (int) $document->sortDate === SearchDocument::UNDATED
                    ? null
                    : new DateTimeImmutable("@{$document->sortDate}"),
                score: $score,
            );
        }

        return $hits;
    }

    /**
     * @param int|null $limit Stop counting after this many, the result is then limit + 1
     */
    public function count(SearchCriteria $criteria, ?int $limit): int
    {
        $joined = $this->hasDocumentConditions($criteria);
        $key = $joined ? 'd.id' : 'w.documentId';
        $query = $this->matching($criteria, $joined);

        if ($limit !== null) {
            return count(
                $query
                    ->select("DISTINCT {$key}")
                    ->setMaxResults($limit + 1)
                    ->getQuery()
                    ->getSingleColumnResult(),
            );
        }

        $count = $query->select("COUNT(DISTINCT {$key})")->getQuery()->getSingleScalarResult();

        return is_numeric($count) ? (int) $count : 0;
    }

    /**
     * Sums the scores of the matching words per document, the document table only joins in when needed
     *
     * @return array<int|string, float> Document id => score, in result order
     */
    private function byRelevance(SearchCriteria $criteria, int $limit, int $offset, int $now): array
    {
        $recency = $criteria->sort === SearchSort::RelevanceWithRecency;
        $joined = $recency || $this->hasDocumentConditions($criteria);
        $key = $joined ? 'd.id' : 'w.documentId';
        $query = $this
            ->matching($criteria, $joined)
            ->select("{$key} AS document")
            ->groupBy($key)
            ->orderBy('score', SortDirection::Descending)
            ->addOrderBy('document', SortDirection::Ascending)
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        if ($recency) {
            $query
                ->addSelect(
                    'SUM(w.score) / (1.0 + (CASE WHEN d.sortDate > :now THEN 0 WHEN d.sortDate = :undated THEN :now ELSE :now - d.sortDate END) * 1.0 / :half_life) AS score',
                )
                ->setParameter('now', $now)
                ->setParameter('undated', SearchDocument::UNDATED)
                ->setParameter('half_life', (float) ($this->recencyHalfLifeDays * self::SECONDS_PER_DAY));
        } else {
            $query->addSelect('SUM(w.score) AS score');
        }

        /** @var list<array{document: int|string, score: float|string}> $rows */
        $rows = $query->getQuery()->getArrayResult();

        return array_map(floatval(...), array_column($rows, 'score', 'document'));
    }

    /**
     * Walks the documents along the date index and checks each, so it stops as soon as the page is full instead of
     * looking at every match. Undated documents have the lowest date, so they're the oldest. No scores, they don't
     * matter for the order
     *
     * @return array<int|string, float> Document id => score, in result order
     */
    private function byDate(SearchCriteria $criteria, int $limit, int $offset): array
    {
        $direction = $criteria->sort === SearchSort::Newest ? SortDirection::Descending : SortDirection::Ascending;
        $query = $this->entityManager
            ->createQueryBuilder()
            ->select('d.id')
            ->from(SearchDocument::class, 'd')
            ->orderBy('d.sortDate', $direction)
            ->addOrderBy('d.id', $direction)
            ->setFirstResult($offset)
            ->setMaxResults($limit);
        $terms = $this->bindTerms($query, $criteria);

        foreach ([...$this->termChecks($terms, 'd.id'), ...$this->filterChecks($query, $criteria, 'd.id')] as $check) {
            $query->andWhere($check);
        }

        $this->documentConditions($query, $criteria);

        /** @var list<int|string> $ids */
        $ids = $query->getQuery()->getSingleColumnResult();

        return array_fill_keys($ids, 0.0);
    }

    /**
     * Word rows matching any term, of documents that match every term and filter. With several terms the longest one,
     * probably the most selective, picks the candidate documents, the other terms and the filters are checked per row.
     * Checking them inside the candidate subquery is faster on SQLite and PostgreSQL but a lot slower on MySQL
     */
    private function matching(SearchCriteria $criteria, bool $joined): QueryBuilder
    {
        $query = $this->entityManager->createQueryBuilder()->from(SearchWord::class, 'w');
        $terms = $this->bindTerms($query, $criteria);
        $query->where($this->anyTerm(count($terms)));

        if ($joined) {
            $query->join(SearchDocument::class, 'd', Join::ON, 'd.id = w.documentId');
            $this->documentConditions($query, $criteria);
        }

        if (count($terms) > 1) {
            $query->andWhere(
                'w.documentId IN (SELECT t0.documentId FROM '
                    . SearchWord::class
                    . " t0 WHERE {$this->range('t0', 0)})",
            );
        }

        $checks = [
            ...array_slice($this->termChecks($terms, 'w.documentId'), 1),
            ...$this->filterChecks($query, $criteria, 'w.documentId'),
        ];

        foreach ($checks as $check) {
            $query->andWhere($check);
        }

        return $query;
    }

    /**
     * Binds the terms longest first as term_0, term_1, ...
     *
     * @return non-empty-list<string> The terms in that order
     */
    private function bindTerms(QueryBuilder $query, SearchCriteria $criteria): array
    {
        $terms = $criteria->terms;
        usort($terms, static fn(string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        foreach ($terms as $index => $term) {
            $query
                ->setParameter("term_{$index}", $term, Types::BINARY)
                ->setParameter("term_{$index}_end", $term . self::AFTER_UTF8, Types::BINARY);
        }

        return $terms;
    }

    private function range(string $alias, int $index): string
    {
        return "{$alias}.word >= :term_{$index} AND {$alias}.word < :term_{$index}_end";
    }

    private function anyTerm(int $terms): string
    {
        return (
            '('
                . implode(') OR (', array_map(
                    fn(int $index): string => $this->range('w', $index),
                    range(0, $terms - 1),
                ))
                . ')'
        );
    }

    /**
     * @param list<string> $terms
     *
     * @return list<string> One EXISTS per term, for the document $documentId (a DQL expression)
     */
    private function termChecks(array $terms, string $documentId): array
    {
        return array_map(
            fn(int $index): string => (
                "EXISTS (SELECT t{$index}x.score FROM "
                . SearchWord::class
                . " t{$index}x WHERE t{$index}x.documentId = {$documentId} AND {$this->range("t{$index}x", $index)})"
            ),
            array_keys($terms),
        );
    }

    /**
     * @return list<string> One EXISTS per filter, for the document $documentId (a DQL expression)
     */
    private function filterChecks(QueryBuilder $query, SearchCriteria $criteria, string $documentId): array
    {
        $checks = [];

        foreach (array_keys($criteria->filters) as $index => $name) {
            $query
                ->setParameter("filter_{$index}", $name)
                ->setParameter("filter_{$index}_values", $criteria->filters[$name], ArrayParameterType::BINARY);
            $checks[] =
                "EXISTS (SELECT f{$index}.name FROM "
                . SearchFilter::class
                . " f{$index} WHERE f{$index}.documentId = {$documentId} AND f{$index}.name = :filter_{$index} AND f{$index}.value IN (:filter_{$index}_values))";
        }

        return $checks;
    }

    private function hasDocumentConditions(SearchCriteria $criteria): bool
    {
        return $criteria->classes !== null || $criteria->dateFrom !== null || $criteria->dateTo !== null;
    }

    /**
     * Class and date conditions on the document table as "d"
     */
    private function documentConditions(QueryBuilder $query, SearchCriteria $criteria): void
    {
        if ($criteria->classes !== null) {
            $query->andWhere('d.entityClass IN (:classes)')->setParameter('classes', $criteria->classes);
        }

        if ($criteria->dateFrom !== null) {
            $query->andWhere('d.sortDate >= :date_from')->setParameter('date_from', $criteria->dateFrom);
        }

        if ($criteria->dateTo !== null) {
            $query
                ->andWhere('d.sortDate <= :date_to AND d.sortDate > :undated')
                ->setParameter('date_to', $criteria->dateTo)
                ->setParameter('undated', SearchDocument::UNDATED);
        }
    }

    /**
     * @param list<int|string> $ids
     *
     * @return array<int|string, SearchDocument> By id
     */
    private function documents(array $ids): array
    {
        if (count($ids) === 0) {
            return [];
        }

        /** @var array<int|string, SearchDocument> */
        return $this->entityManager
            ->createQueryBuilder()
            ->select('d')
            ->from(SearchDocument::class, 'd', 'd.id')
            ->where('d.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }
}
