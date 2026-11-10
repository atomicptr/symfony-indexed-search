<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Entity;

use Atomicptr\Symfony\IndexedSearch\Indexing\FilterNormalizer;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A filter value of a document. Binary value so it matches exactly on every database, e.g. "ABC" isn't "abc"
 *
 * @internal
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(name: 'indexed_search_filter_document', columns: ['document_id', 'name', 'value'])]
class SearchFilter
{
    public const string TABLE = 'indexed_search_filter';

    #[ORM\Id]
    #[ORM\Column(name: 'name', length: FilterNormalizer::MAX_NAME_LENGTH)]
    public private(set) string $name;

    #[ORM\Id]
    #[ORM\Column(name: 'value', type: Types::BINARY, length: FilterNormalizer::MAX_VALUE_BYTES)]
    public private(set) string $value;

    #[ORM\Id]
    #[ORM\Column(name: 'document_id', type: Types::BIGINT)]
    public private(set) int|string $documentId;
}
