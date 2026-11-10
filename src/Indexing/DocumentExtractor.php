<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Indexing;

use Atomicptr\Symfony\IndexedSearch\Enum\MemberKind;
use Atomicptr\Symfony\IndexedSearch\Exception\InvalidArgumentException;
use Atomicptr\Symfony\IndexedSearch\Exception\InvalidValueException;
use Atomicptr\Symfony\IndexedSearch\ValueObject\Document;
use Atomicptr\Symfony\IndexedSearch\ValueObject\Member;
use Atomicptr\Symfony\IndexedSearch\ValueObject\SearchableClass;
use DateTimeInterface;
use ReflectionMethod;
use ReflectionProperty;
use Stringable;
use UnitEnum;

/**
 * Builds the documents of an entity
 *
 * @internal
 */
final readonly class DocumentExtractor
{
    /**
     * @return array<string, Document> Keyed by variant
     *
     * @throws InvalidValueException
     */
    public function extract(SearchableClass $class, object $entity): array
    {
        if ($class->condition !== null && !$this->condition($class->condition, $entity)) {
            return [];
        }

        $documents = $class->documents === null
            ? [$this->fromAttributes($class, $entity)]
            : $this->fromMethod($class->documents, $entity);

        $result = [];

        foreach ($documents as $document) {
            if (array_key_exists($document->variant, $result)) {
                throw new InvalidValueException(
                    "{$class->class} returned the document variant \"{$document->variant}\" more than once.",
                );
            }

            foreach ($class->classFilters as $name => $values) {
                foreach ($values as $value) {
                    $document = $document->withFilter($name, $value);
                }
            }

            if ($class->date !== null && $document->date === null) {
                $document = $document->withDate($this->date($this->read($class->date, $entity), $class->date));
            }

            if ($document->hasText()) {
                $result[$document->variant] = $document;
            }
        }

        return $result;
    }

    private function fromAttributes(SearchableClass $class, object $entity): Document
    {
        $document = Document::create();

        foreach ($class->texts as $text) {
            foreach ($this->values($this->read($text->member, $entity), $text->member) as $value) {
                if (!is_string($value) && !$value instanceof Stringable) {
                    throw new InvalidValueException(
                        "{$text->member->label()} must provide strings, Stringable or null, "
                            . get_debug_type($value)
                            . ' given.',
                    );
                }

                $document = $document->withText($value, $text->weight, $text->format);
            }
        }

        foreach ($class->filters as $filter) {
            foreach ($this->values($this->read($filter->member, $entity), $filter->member) as $value) {
                if (
                    !is_string($value)
                    && !is_int($value)
                    && !is_bool($value)
                    && !$value instanceof Stringable
                    && !$value instanceof UnitEnum
                ) {
                    throw new InvalidValueException(
                        "{$filter->member->label()} must provide scalars, enums, Stringable or null, "
                            . get_debug_type($value)
                            . ' given.',
                    );
                }

                try {
                    $document = $document->withFilter($filter->name, $value);
                } catch (InvalidArgumentException $exception) {
                    throw new InvalidValueException(
                        "{$filter->member->label()}: {$exception->getMessage()}",
                        previous: $exception,
                    );
                }
            }
        }

        return $document;
    }

    /**
     * @return iterable<Document>
     */
    private function fromMethod(Member $member, object $entity): iterable
    {
        $documents = $this->read($member, $entity);

        if (!is_iterable($documents)) {
            throw new InvalidValueException(
                "{$member->label()} must return iterable<Document>, " . get_debug_type($documents) . ' given.',
            );
        }

        foreach ($documents as $document) {
            if (!$document instanceof Document) {
                throw new InvalidValueException(
                    "{$member->label()} must return iterable<Document>, contains " . get_debug_type($document) . '.',
                );
            }

            yield $document;
        }
    }

    /**
     * @return iterable<mixed>
     */
    private function values(mixed $value, Member $member): iterable
    {
        if ($value === null) {
            return [];
        }

        if (!is_iterable($value)) {
            return [$value];
        }

        return (static function () use ($value, $member): iterable {
            foreach ($value as $item) {
                if (is_iterable($item)) {
                    throw new InvalidValueException("{$member->label()} must not provide nested iterables.");
                }

                if ($item !== null) {
                    yield $item;
                }
            }
        })();
    }

    private function condition(Member $member, object $entity): bool
    {
        $value = $this->read($member, $entity);

        if (!is_bool($value)) {
            throw new InvalidValueException(
                "{$member->label()} must provide a bool, " . get_debug_type($value) . ' given.',
            );
        }

        return $value;
    }

    private function date(mixed $value, Member $member): ?DateTimeInterface
    {
        if ($value === null || $value instanceof DateTimeInterface) {
            return $value;
        }

        throw new InvalidValueException(
            "{$member->label()} must provide a DateTimeInterface or null, " . get_debug_type($value) . ' given.',
        );
    }

    /**
     * Reads the property or calls the method, private ones too
     */
    private function read(Member $member, object $entity): mixed
    {
        return match ($member->kind) {
            MemberKind::Property => new ReflectionProperty($member->declaringClass, $member->name)->getValue($entity),
            MemberKind::Method => new ReflectionMethod($member->declaringClass, $member->name)->invoke($entity),
        };
    }
}
