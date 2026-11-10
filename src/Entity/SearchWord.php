<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Entity;

use Atomicptr\Symfony\IndexedSearch\Indexing\Tokenizer;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A word of a document and how much it counts. The primary key starts with the word, so a prefix search is one index
 * range. Binary so every database compares bytes the same way
 *
 * @internal
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(name: 'indexed_search_word_document', columns: ['document_id', 'word'])]
class SearchWord
{
    public const string TABLE = 'indexed_search_word';
    public const int MAX_WORD_BYTES = Tokenizer::MAX_WORD_LENGTH * 4;

    #[ORM\Id]
    #[ORM\Column(name: 'word', type: Types::BINARY, length: self::MAX_WORD_BYTES)]
    public private(set) string $word;

    #[ORM\Id]
    #[ORM\Column(name: 'document_id', type: Types::BIGINT)]
    public private(set) int|string $documentId;

    #[ORM\Column(name: 'score', type: Types::FLOAT)]
    public private(set) float $score;
}
