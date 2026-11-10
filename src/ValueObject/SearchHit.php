<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\ValueObject;

use DateTimeImmutable;

/**
 * One search result
 *
 * @template-covariant T of object
 *
 * @api
 */
final readonly class SearchHit
{
    /**
     * @param class-string<T> $entityClass
     * @param string $entityId The entity's id as a string
     * @param string $variant Which document matched, empty if the entity only has one
     * @param float $score How well it matched, only compare it within the same search. 0 when sorted by date
     * @param T|null $entity Only set with hydrate: true
     */
    public function __construct(
        public string $entityClass,
        public string $entityId,
        public string $variant,
        public ?DateTimeImmutable $date,
        public float $score,
        public ?object $entity = null,
    ) {
    }
}
