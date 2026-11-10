<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Service;

use Atomicptr\Symfony\IndexedSearch\Entity\SearchDocument;
use Atomicptr\Symfony\IndexedSearch\Entity\SearchFilter;
use Atomicptr\Symfony\IndexedSearch\Entity\SearchWord;
use Atomicptr\Symfony\IndexedSearch\Enum\Weight;
use Atomicptr\Symfony\IndexedSearch\Indexing\Tokenizer;
use Atomicptr\Symfony\IndexedSearch\ValueObject\Document;
use Atomicptr\Symfony\IndexedSearch\ValueObject\DocumentRow;
use Atomicptr\Symfony\IndexedSearch\ValueObject\StoredDocument;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;

/**
 * Writes and deletes documents with their words and filters
 *
 * @internal
 */
final readonly class DocumentStore
{
    private const int CHUNK_SIZE = 500;

    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * Replaces all documents of these entities in one transaction
     *
     * @param class-string $entityClass
     * @param array<array-key, array<string, Document>> $documents
     * @param int $indexedAt
     */
    public function replace(string $entityClass, array $documents, int $indexedAt): void
    {
        if (count($documents) === 0) {
            return;
        }

        $this->connection->transactional(function () use ($entityClass, $documents, $indexedAt): void {
            foreach (array_chunk($documents, self::CHUNK_SIZE, preserve_keys: true) as $chunk) {
                $this->replaceChunk($entityClass, $chunk, $indexedAt);
            }
        });
    }

    /**
     * @param class-string $entityClass
     * @param list<string> $entityIds
     */
    public function remove(string $entityClass, array $entityIds): void
    {
        foreach (array_chunk($entityIds, self::CHUNK_SIZE) as $chunk) {
            $this->delete(
                'entity_class = :class AND entity_id IN (:ids)',
                ['class' => $entityClass, 'ids' => $chunk],
                ['ids' => ArrayParameterType::BINARY],
            );
        }
    }

    /**
     * Removes every document a reindex started at $indexedAt didn't touch, also of classes that aren't searchable anymore
     *
     * @return int How many documents were removed
     */
    public function removeIndexedBefore(int $indexedAt): int
    {
        return $this->delete(
            'indexed_at < :indexed_at',
            ['indexed_at' => $indexedAt],
            [
                'indexed_at' => ParameterType::INTEGER,
            ],
        );
    }

    /**
     * A handful of statements per chunk instead of several per document: unchanged documents only get the new
     * indexed_at, new ones are inserted together and changed ones get their words and filters replaced
     *
     * @param class-string $entityClass
     * @param array<array-key, array<string, Document>> $documents
     */
    private function replaceChunk(string $entityClass, array $documents, int $indexedAt): void
    {
        $existing = $this->existing($entityClass, array_map(strval(...), array_keys($documents)));
        $unchanged = [];
        $changed = [];
        $new = [];

        foreach ($documents as $entityId => $variants) {
            foreach ($variants as $document) {
                $row = $this->row($entityClass, (string) $entityId, $document, $indexedAt);
                $current = $existing[$entityId][$document->variant] ?? null;

                match (true) {
                    $current === null => $new[] = $row,
                    $current->contentHash === $row->contentHash => $unchanged[] = $current->id,
                    default => $changed[$current->id] = $row,
                };
            }
        }

        $stale = array_values(array_diff(
            array_map(
                static fn(StoredDocument $stored): int|string => $stored->id,
                array_merge(...array_map(array_values(...), array_values($existing))),
            ),
            $unchanged,
            array_keys($changed),
        ));

        foreach (array_chunk($stale, self::CHUNK_SIZE) as $ids) {
            $this->delete('id IN (:ids)', ['ids' => $ids], ['ids' => ArrayParameterType::INTEGER]);
        }

        foreach (array_chunk($unchanged, self::CHUNK_SIZE) as $ids) {
            $this->connection->executeStatement(
                'UPDATE ' . SearchDocument::TABLE . ' SET indexed_at = :indexed_at WHERE id IN (:ids)',
                ['indexed_at' => $indexedAt, 'ids' => $ids],
                ['indexed_at' => ParameterType::INTEGER, 'ids' => ArrayParameterType::INTEGER],
            );
        }

        foreach (array_chunk(array_keys($changed), self::CHUNK_SIZE) as $ids) {
            $this->deleteWordsAndFilters(
                'document_id IN (:ids)',
                ['ids' => $ids],
                [
                    'ids' => ArrayParameterType::INTEGER,
                ],
            );
        }

        foreach ($changed as $id => $row) {
            $this->connection->update(SearchDocument::TABLE, $row->columns(), ['id' => $id], DocumentRow::TYPES);
        }

        $this->insert(
            SearchDocument::TABLE,
            array_map(static fn(DocumentRow $row): array => $row->columns(), $new),
            DocumentRow::TYPES,
        );
        $this->insertWordsAndFilters($changed + $this->withIds($entityClass, $new));
    }

    /**
     * Looks up the ids the database gave the new documents
     *
     * @param class-string $entityClass
     * @param list<DocumentRow> $rows
     *
     * @return array<int|string, DocumentRow> Document id => row
     */
    private function withIds(string $entityClass, array $rows): array
    {
        if (count($rows) === 0) {
            return [];
        }

        $stored = $this->existing(
            $entityClass,
            array_values(array_unique(array_map(static fn(DocumentRow $row): string => $row->entityId, $rows))),
        );
        $byId = [];

        foreach ($rows as $row) {
            $byId[$stored[$row->entityId][$row->variant]->id] = $row;
        }

        return $byId;
    }

    /**
     * @param array<int|string, DocumentRow> $rows Document id => row
     */
    private function insertWordsAndFilters(array $rows): void
    {
        $words = [];
        $filters = [];

        foreach ($rows as $id => $row) {
            foreach ($row->words as $word => $score) {
                $words[] = ['document_id' => $id, 'word' => (string) $word, 'score' => $score];
            }

            foreach ($row->filters as $name => $values) {
                foreach ($values as $value) {
                    $filters[] = ['name' => $name, 'value' => $value, 'document_id' => $id];
                }
            }
        }

        $this->insert(SearchWord::TABLE, $words, [
            'document_id' => ParameterType::INTEGER,
            'word' => ParameterType::BINARY,
        ]);
        $this->insert(SearchFilter::TABLE, $filters, [
            'value' => ParameterType::BINARY,
            'document_id' => ParameterType::INTEGER,
        ]);
    }

    /**
     * Multi-row inserts, rows are small since no text is stored
     *
     * @param list<array<string, string|int|float|null>> $rows Column => value, every row has the same columns
     * @param array<string, ParameterType> $types Column => type, the rest are strings
     */
    private function insert(string $table, array $rows, array $types): void
    {
        foreach (array_chunk($rows, self::CHUNK_SIZE) as $batch) {
            $columns = array_keys($batch[0]);
            $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';

            $this->connection->executeStatement(
                "INSERT INTO {$table} (" . implode(', ', $columns) . ') VALUES '
                    . implode(', ', array_fill(0, count($batch), $placeholders)),
                array_merge(...array_map(array_values(...), $batch)),
                array_merge(...array_fill(
                    0,
                    count($batch),
                    array_map(
                        static fn(string $column): ParameterType => $types[$column] ?? ParameterType::STRING,
                        $columns,
                    ),
                )),
            );
        }
    }

    /**
     * Deletes the documents matching a condition on the document table, with their words and filters
     *
     * @param array<string, mixed> $parameters
     * @param array<string, ArrayParameterType|ParameterType> $types
     *
     * @return int How many documents were removed
     */
    private function delete(string $condition, array $parameters, array $types): int
    {
        $this->deleteWordsAndFilters(
            'document_id IN (SELECT id FROM ' . SearchDocument::TABLE . " WHERE {$condition})",
            $parameters,
            $types,
        );

        return (int) $this->connection->executeStatement(
            'DELETE FROM ' . SearchDocument::TABLE . " WHERE {$condition}",
            $parameters,
            $types,
        );
    }

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, ArrayParameterType|ParameterType> $types
     */
    private function deleteWordsAndFilters(string $condition, array $parameters, array $types): void
    {
        foreach ([SearchWord::TABLE, SearchFilter::TABLE] as $table) {
            $this->connection->executeStatement("DELETE FROM {$table} WHERE {$condition}", $parameters, $types);
        }
    }

    /**
     * @param list<string> $entityIds
     *
     * @return array<string, array<string, StoredDocument>> Entity id => variant => row
     */
    private function existing(string $entityClass, array $entityIds): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, entity_id, variant, content_hash FROM '
                . SearchDocument::TABLE
                . ' WHERE entity_class = :class AND entity_id IN (:ids)',
            ['class' => $entityClass, 'ids' => $entityIds],
            ['ids' => ArrayParameterType::BINARY],
        );

        $existing = [];

        foreach ($rows as $row) {
            /** @var array{id: int|string, entity_id: mixed, variant: mixed, content_hash: string} $row */
            $entityId = $this->binary($row['entity_id']);
            $existing[$entityId][$this->binary($row['variant'])] = new StoredDocument($row['id'], $row['content_hash']);
        }

        return $existing;
    }

    /**
     * PostgreSQL returns binary columns as streams
     */
    private function binary(mixed $value): string
    {
        $string = $this->connection->convertToPHPValue($value, Types::BINARY);

        return is_string($string) ? $string : '';
    }

    private function row(string $entityClass, string $entityId, Document $document, int $indexedAt): DocumentRow
    {
        $sortDate = $document->date?->getTimestamp() ?? SearchDocument::UNDATED;
        $words = $this->words($document);

        return new DocumentRow(
            $entityClass,
            $entityId,
            $document->variant,
            $sortDate,
            $words,
            $document->filters,
            hash('sha3-256', serialize([$sortDate, $words, $document->filters])),
            $indexedAt,
        );
    }

    /**
     * Every occurrence adds its weight, so a word in the title and the body counts more than one only in the body
     *
     * @return array<array-key, float> Word => score
     */
    private function words(Document $document): array
    {
        $words = [];

        foreach (Weight::cases() as $weight) {
            foreach (Tokenizer::words($document->text($weight)) as $word) {
                $words[$word] = ($words[$word] ?? 0.0) + $weight->multiplier();
            }
        }

        return $words;
    }
}
