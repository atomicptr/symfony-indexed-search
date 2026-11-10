<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Indexing;

use Normalizer;

/**
 * Splits text into searchable words
 *
 * @internal
 */
final class Tokenizer
{
    public const int MAX_WORD_LENGTH = 128;

    /**
     * @return list<non-empty-string> In order, duplicates included
     */
    public static function words(string $text): array
    {
        $decomposed = Normalizer::normalize(mb_strtolower(mb_scrub($text)), Normalizer::FORM_D);
        $folded = is_string($decomposed)
            ? Normalizer::normalize(preg_replace('/\p{Mn}+/u', '', $decomposed) ?? '', Normalizer::FORM_C)
            : false;
        $words = is_string($folded) ? preg_split('/[^\p{L}\p{M}\p{N}]+/u', $folded, flags: PREG_SPLIT_NO_EMPTY) : false;

        return array_values(array_filter(
            $words === false ? [] : $words,
            static fn(string $word): bool => mb_strlen($word) <= self::MAX_WORD_LENGTH,
        ));
    }
}
