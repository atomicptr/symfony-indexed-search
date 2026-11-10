<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Service;

use Atomicptr\Symfony\IndexedSearch\Exception\InvalidArgumentException;
use Atomicptr\Symfony\IndexedSearch\Exception\InvalidValueException;
use Atomicptr\Symfony\IndexedSearch\Indexing\DocumentExtractor;
use Atomicptr\Symfony\IndexedSearch\Metadata\SearchableRegistry;
use Atomicptr\Symfony\IndexedSearch\ValueObject\SearchableClass;
use Atomicptr\Symfony\IndexedSearch\Entity\SearchDocument;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Stringable;

/**
 * Keeps the search index up to date
 *
 * @api
 */
final readonly class SearchIndexer
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SearchableRegistry $registry,
        private DocumentExtractor $extractor,
        private DocumentStore $store,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Adds, updates or removes the documents of these entities
     *
     * @param iterable<object> $entities
     *
     * @throws InvalidArgumentException If an entity isn't searchable
     * @throws InvalidValueException If an entity returns something we can't index
     */
    public function index(iterable $entities): void
    {
        $this->write($entities, $this->now());
    }

    /**
     * Loads the entities by id and indexes them, missing ones get removed
     *
     * @param class-string $class
     * @param list<int|string|Stringable> $ids
     */
    public function indexByIds(string $class, array $ids): void
    {
        $searchable = $this->registry->get($class);
        $ids = $this->ids($ids);

        if (count($ids) === 0) {
            return;
        }

        $entities = $this->entityManager->getRepository($searchable->class)->findBy([$searchable->idField => $ids]);
        $this->index($entities);

        $found = array_map(fn(object $entity): string => $this->entityId($searchable, $entity), $entities);
        $this->store->remove($searchable->class, array_values(array_diff($ids, $found)));
    }

    /**
     * @param class-string $class
     * @param list<int|string|Stringable> $ids
     */
    public function remove(string $class, array $ids): void
    {
        $this->store->remove($this->registry->get($class)->class, $this->ids($ids));
    }

    /**
     * Removes the entity from the index
     */
    public function removeEntity(object $entity): void
    {
        $class = $this->registry->get($entity);
        $this->store->remove($class->class, [$this->entityId($class, $entity)]);
    }

    /**
     * Rebuilds the whole index in batches and removes everything it didn't index, like documents of deleted entities
     * or of classes that aren't searchable anymore
     *
     * Clears the entity manager after every batch to keep memory flat, so flush your changes first and don't keep using
     * entities you loaded before. Meant for the command or a background job, not the middle of a request.
     * Enabled Doctrine filters apply too, entities they hide get removed from the index
     *
     * @param positive-int $batchSize
     * @param (callable(class-string, int): void)|null $onProgress Called after each batch
     *
     * @return int How many entities were processed
     */
    public function reindex(int $batchSize = 1000, ?callable $onProgress = null): int
    {
        $startedAt = $this->now();
        $processed = 0;

        foreach ($this->registry->all() as $searchable) {
            $processed += $this->reindexClass($searchable, $batchSize, $startedAt, $onProgress);
        }

        $this->store->removeIndexedBefore($startedAt);

        return $processed;
    }

    /**
     * @param iterable<object> $entities
     */
    private function write(iterable $entities, int $indexedAt): void
    {
        $documents = [];

        foreach ($entities as $entity) {
            $class = $this->registry->get($entity);
            $documents[$class->class][$this->entityId($class, $entity)] = $this->extractor->extract($class, $entity);
        }

        foreach ($documents as $class => $byEntity) {
            try {
                $this->store->replace($class, $byEntity, $indexedAt);
            } catch (UniqueConstraintViolationException) {
                // someone else inserted the same document at the same time, the retry updates it instead
                $this->store->replace($class, $byEntity, $indexedAt);
            }
        }
    }

    /**
     * @param positive-int $batchSize
     * @param (callable(class-string, int): void)|null $onProgress
     */
    private function reindexClass(SearchableClass $class, int $batchSize, int $indexedAt, ?callable $onProgress): int
    {
        $processed = 0;
        $lastId = null;
        $metadata = $this->entityManager->getClassMetadata($class->class);

        // page by id instead of OFFSET so every batch stays fast
        do {
            $condition = $lastId === null ? '' : "WHERE e.{$class->idField} > :last_id";
            $query = $this->entityManager
                ->createQuery("SELECT e FROM {$class->class} e {$condition} ORDER BY e.{$class->idField} ASC")
                ->setMaxResults($batchSize);

            if ($lastId !== null) {
                // the id's own type, e.g. a Uuid object must be converted the same way Doctrine stores it
                $query->setParameter('last_id', $lastId, $metadata->getTypeOfField($class->idField));
            }

            /** @var list<object> $entities */
            $entities = $query->getResult();

            if (count($entities) === 0) {
                break;
            }

            $this->write($entities, $indexedAt);

            $lastId = $metadata->getFieldValue($entities[count($entities) - 1], $class->idField);
            $processed += count($entities);
            $this->entityManager->clear();

            if ($onProgress !== null) {
                $onProgress($class->class, count($entities));
            }
        } while (count($entities) === $batchSize);

        return $processed;
    }

    private function entityId(SearchableClass $class, object $entity): string
    {
        $id = $this->entityManager->getClassMetadata($class->class)->getFieldValue($entity, $class->idField);

        if (!is_int($id) && !is_string($id) && !$id instanceof Stringable) {
            throw new InvalidValueException("{$class->class} entity has no identifier yet; flush it before indexing.");
        }

        $id = (string) $id;

        if ($id === '' || mb_strlen($id) > SearchDocument::MAX_ENTITY_ID_LENGTH) {
            $maxLength = SearchDocument::MAX_ENTITY_ID_LENGTH;

            throw new InvalidValueException("{$class->class} identifiers must be 1-{$maxLength} characters long.");
        }

        return $id;
    }

    /**
     * @param list<int|string|Stringable> $ids
     *
     * @return list<string>
     */
    private function ids(array $ids): array
    {
        return array_values(array_unique(array_map(strval(...), $ids)));
    }

    /**
     * Microseconds so a reindex can tell old rows from new ones
     */
    private function now(): int
    {
        return (int) $this->clock->now()->format('Uu');
    }
}
