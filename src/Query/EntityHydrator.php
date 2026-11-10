<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Query;

use Atomicptr\Symfony\IndexedSearch\Metadata\SearchableRegistry;
use Atomicptr\Symfony\IndexedSearch\ValueObject\SearchHit;
use Doctrine\ORM\EntityManagerInterface;
use Stringable;

/**
 * Loads the entities for search hits, one query per class
 *
 * @internal
 */
final readonly class EntityHydrator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SearchableRegistry $registry,
    ) {
    }

    /**
     * Hits whose entity can't be loaded are dropped
     *
     * @param list<SearchHit<object>> $hits
     *
     * @return list<SearchHit<object>>
     */
    public function hydrate(array $hits): array
    {
        $idsByClass = [];

        foreach ($hits as $hit) {
            $idsByClass[$hit->entityClass][$hit->entityId] = $hit->entityId;
        }

        $searchables = $this->registry->all();
        $entities = [];

        foreach ($idsByClass as $class => $ids) {
            $searchable = $searchables[$class] ?? null;

            if ($searchable === null) {
                continue;
            }

            $metadata = $this->entityManager->getClassMetadata($searchable->class);
            $found = $this->entityManager
                ->getRepository($searchable->class)
                ->findBy([$searchable->idField => array_values($ids)]);

            foreach ($found as $entity) {
                $id = $metadata->getFieldValue($entity, $searchable->idField);

                if (is_int($id) || is_string($id) || $id instanceof Stringable) {
                    $entities[$class][(string) $id] = $entity;
                }
            }
        }

        $hydrated = [];

        foreach ($hits as $hit) {
            $entity = $entities[$hit->entityClass][$hit->entityId] ?? null;

            if ($entity !== null) {
                $hydrated[] = new SearchHit(
                    $hit->entityClass,
                    $hit->entityId,
                    $hit->variant,
                    $hit->date,
                    $hit->score,
                    $entity,
                );
            }
        }

        return $hydrated;
    }
}
