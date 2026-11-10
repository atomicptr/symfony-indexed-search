<?php

declare(strict_types = 1);

use Atomicptr\Symfony\IndexedSearch\Enum\Weight;
use Atomicptr\Symfony\IndexedSearch\Exception\InvalidArgumentException;
use Atomicptr\Symfony\IndexedSearch\Tests\Fixtures\Entity\Category;
use Atomicptr\Symfony\IndexedSearch\Tests\Fixtures\UnitCategory;
use Atomicptr\Symfony\IndexedSearch\ValueObject\Document;

it('collects text per weight and ignores blank values', function (): void {
    $document = Document::create()
        ->withText('Title', Weight::Highest)
        ->withText('  ')
        ->withText(null)
        ->withText('Body one')
        ->withText('Body two');

    expect($document->text(Weight::Highest))
        ->toBe('Title')
        ->and($document->text(Weight::Normal))
        ->toBe("Body one\nBody two")
        ->and($document->text(Weight::Low))
        ->toBe('')
        ->and(Document::create()->withText(' ')->hasText())
        ->toBeFalse();
});

it('normalizes and deduplicates filters', function (): void {
    $document = Document::create()
        ->withFilter('category', Category::Shoes)
        ->withFilter('kind', UnitCategory::Plain)
        ->withFilter('published', false)
        ->withFilter('tenant', 42)
        ->withFilter('tenant', '42')
        ->withFilter('lang', 'de')
        ->withFilter('lang', 'en')
        ->withFilter('lang', null);

    expect($document->filters)->toBe([
        'category' => ['shoes'],
        'kind' => ['Plain'],
        'published' => ['0'],
        'tenant' => ['42'],
        'lang' => ['de', 'en'],
    ]);
});

it('is immutable', function (): void {
    $document = Document::create();
    $document->withText('x')->withFilter('a', 'b');

    expect($document->hasText())->toBeFalse()->and($document->filters)->toBe([]);
});

it('rejects invalid filters and variants', function (Closure $create): void {
    expect($create)->toThrow(InvalidArgumentException::class);
})->with([
    'upper case name' => [static fn() => Document::create()->withFilter('Lang', 'de')],
    'empty value' => [static fn() => Document::create()->withFilter('lang', '')],
    'too long value' => [static fn() => Document::create()->withFilter('lang', str_repeat('x', 256))],
    'too long variant' => [static fn() => Document::create(str_repeat('x', 65))],
]);
