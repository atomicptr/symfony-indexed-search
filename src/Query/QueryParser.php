<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Query;

use Atomicptr\Symfony\IndexedSearch\Indexing\Tokenizer;

/**
 * Turns user input into search terms, see Tokenizer
 *
 * @internal
 */
final readonly class QueryParser
{
    public function __construct(
        private int $minTermLength,
        private int $maxTerms,
    ) {
    }

    /**
     * @return list<non-empty-string>
     */
    public function parse(string $query): array
    {
        return Tokenizer::words($query)
            |> (fn(array $words): array => array_filter(
                $words,
                fn(string $word): bool => mb_strlen($word) >= $this->minTermLength,
            ))
            |> array_unique(...)
            |> (fn(array $words): array => array_slice($words, 0, $this->maxTerms));
    }
}
