<?php

declare(strict_types = 1);

use Atomicptr\Symfony\IndexedSearch\Query\QueryParser;

it('normalizes queries into safe terms', function (string $query, array $terms): void {
    expect(new QueryParser(minTermLength: 2, maxTerms: 3)->parse($query))->toBe($terms);
})->with([
    'lower-cases and splits on punctuation' => ['Red-Shoe, SIZE', ['red', 'shoe', 'size']],
    'treats search operators as separators' => ['+red* -"shoe" (x) NEAR/2', ['red', 'shoe', 'near']],
    'drops short terms' => ['a b cd', ['cd']],
    'deduplicates' => ['shoe Shoe SHOE', ['shoe']],
    'limits the number of terms' => ['one two three four', ['one', 'two', 'three']],
    'removes accents and keeps other letters and digits' => ['Größe Café 東京', ['große', 'cafe', '東京']],
    'keeps vowel signs inside words' => ['हिन्दी', ['हिनदी']],
    'keeps digits and hashes' => ['42 3FA9e0ab', ['42', '3fa9e0ab']],
    'empty' => ['  ', []],
]);
