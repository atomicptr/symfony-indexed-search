<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\ValueObject;

use Atomicptr\Symfony\IndexedSearch\Enum\TextFormat;
use Atomicptr\Symfony\IndexedSearch\Enum\Weight;
use Atomicptr\Symfony\IndexedSearch\Exception\InvalidArgumentException;
use Atomicptr\Symfony\IndexedSearch\Indexing\FilterNormalizer;
use DateTimeImmutable;
use DateTimeInterface;
use Stringable;
use UnitEnum;

/**
 * What gets indexed for an entity. You only build these yourself in a #[SearchableDocuments] method, e.g.
 *
 *     yield Document::create('de')
 *         ->withText($this->nameGerman, Weight::Highest)
 *         ->withFilter('lang', 'de');
 *
 * @api
 */
final readonly class Document
{
    public const int MAX_VARIANT_LENGTH = 64;

    /**
     * @param string $variant Tells multiple documents of the same entity apart, e.g. 'en' and 'de'. Empty if there's only one
     * @param array<value-of<Weight>, list<string>> $texts
     * @param array<string, list<string>> $filters Name => values
     */
    private function __construct(
        public string $variant,
        public array $texts,
        public array $filters,
        public ?DateTimeImmutable $date,
    ) {
    }

    public static function create(string $variant = ''): self
    {
        if (mb_strlen($variant) > self::MAX_VARIANT_LENGTH) {
            $maxLength = self::MAX_VARIANT_LENGTH;

            throw new InvalidArgumentException("Document variants are limited to {$maxLength} characters.");
        }

        return new self($variant, [], [], null);
    }

    /**
     * Adds text, null and empty strings are ignored
     */
    public function withText(
        string|Stringable|null $text,
        Weight $weight = Weight::Normal,
        TextFormat $format = TextFormat::Plain,
    ): self {
        $text = trim($format->toPlain((string) $text));

        if ($text === '') {
            return $this;
        }

        $texts = $this->texts;
        $texts[$weight->value][] = $text;

        return clone($this, ['texts' => $texts]);
    }

    /**
     * Adds a filter value, call it again with the same name for more values
     *
     * @throws InvalidArgumentException
     */
    public function withFilter(string $name, string|int|bool|Stringable|UnitEnum|null $value): self
    {
        if ($value === null) {
            return $this;
        }

        $value = FilterNormalizer::value(FilterNormalizer::name($name), $value);
        $filters = $this->filters;

        if (in_array($value, $filters[$name] ?? [], true)) {
            return $this;
        }

        $filters[$name][] = $value;

        return clone($this, ['filters' => $filters]);
    }

    /**
     * Date for sorting by date, date ranges and recency ranking
     */
    public function withDate(?DateTimeInterface $date): self
    {
        return clone($this, ['date' => $date === null ? null : DateTimeImmutable::createFromInterface($date)]);
    }

    public function text(Weight $weight): string
    {
        return implode("\n", $this->texts[$weight->value] ?? []);
    }

    public function hasText(): bool
    {
        return count($this->texts) > 0;
    }
}
