<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Entity;

use Atomicptr\Symfony\IndexedSearch\ValueObject\Document;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One indexed document of an entity. Written with DBAL by DocumentStore, read with DQL by SearchQuery
 *
 * @internal
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\UniqueConstraint(name: 'indexed_search_document_entity', columns: ['entity_class', 'entity_id', 'variant'])]
#[ORM\Index(name: 'indexed_search_document_sort_date', columns: ['sort_date'])]
class SearchDocument
{
    public const string TABLE = 'indexed_search_document';
    public const int MAX_CLASS_LENGTH = 255;
    public const int MAX_ENTITY_ID_LENGTH = 191;
    public const int UNDATED = PHP_INT_MIN;
    public const int CONTENT_HASH_LENGTH = 64;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT)]
    public private(set) int|string|null $id = null;

    #[ORM\Column(name: 'entity_class', length: self::MAX_CLASS_LENGTH)]
    public private(set) string $entityClass;

    #[ORM\Column(name: 'entity_id', type: Types::BINARY, length: self::MAX_ENTITY_ID_LENGTH)]
    public private(set) string $entityId;

    #[ORM\Column(name: 'variant', type: Types::BINARY, length: Document::MAX_VARIANT_LENGTH)]
    public private(set) string $variant;

    #[ORM\Column(name: 'sort_date', type: Types::BIGINT)]
    public private(set) int|string $sortDate;

    #[ORM\Column(name: 'content_hash', length: self::CONTENT_HASH_LENGTH)]
    public private(set) string $contentHash;

    #[ORM\Column(name: 'indexed_at', type: Types::BIGINT)]
    public private(set) int|string $indexedAt;
}
