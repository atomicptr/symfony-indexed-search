<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\ValueObject;

use Doctrine\DBAL\ParameterType;

/**
 * A document as it gets written: its row in the document table plus its words and filters
 *
 * @internal
 */
final readonly class DocumentRow
{
    /**
     * @param array<array-key, float> $words Word => score, PHP turns numeric words into int keys
     * @param array<string, list<string>> $filters Name => values
     * @param string $contentHash Changes when anything but indexedAt changes
     */
    public function __construct(
        public string $entityClass,
        public string $entityId,
        public string $variant,
        public int $sortDate,
        public array $words,
        public array $filters,
        public string $contentHash,
        public int $indexedAt,
    ) {
    }

    /** DBAL types of the columns that aren't strings */
    public const array TYPES = [
        'entity_id' => ParameterType::BINARY,
        'variant' => ParameterType::BINARY,
        'sort_date' => ParameterType::INTEGER,
        'indexed_at' => ParameterType::INTEGER,
    ];

    /**
     * @return array<string, string|int|null> Column => value of the document table, for DBAL
     */
    public function columns(): array
    {
        return [
            'entity_class' => $this->entityClass,
            'entity_id' => $this->entityId,
            'variant' => $this->variant,
            'sort_date' => $this->sortDate,
            'content_hash' => $this->contentHash,
            'indexed_at' => $this->indexedAt,
        ];
    }
}
