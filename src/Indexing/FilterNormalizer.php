<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Indexing;

use Atomicptr\Symfony\IndexedSearch\Exception\InvalidArgumentException;
use BackedEnum;
use Stringable;
use UnitEnum;

/**
 * Validates filter names and converts values to strings
 *
 * @internal
 */
final class FilterNormalizer
{
    public const int MAX_NAME_LENGTH = 64;
    public const int MAX_VALUE_BYTES = 255;
    private const string NAME_PATTERN = '/^[a-z][a-z0-9_.\-]{0,' . (self::MAX_NAME_LENGTH - 1) . '}$/';

    /**
     * @throws InvalidArgumentException
     */
    public static function name(string $name): string
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            $maxLength = self::MAX_NAME_LENGTH;

            throw new InvalidArgumentException(
                "Invalid filter name \"{$name}\": expected 1-{$maxLength} characters of a-z, 0-9, \"_\", \".\" or \"-\", starting with a letter.",
            );
        }

        return $name;
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function value(string $name, mixed $value): string
    {
        $string = match (true) {
            is_bool($value) => $value ? '1' : '0',
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof UnitEnum => $value->name,
            is_string($value), is_int($value), $value instanceof Stringable => (string) $value,
            default => throw new InvalidArgumentException(
                "Filter \"{$name}\" values must be strings, ints, bools, enums or Stringable, "
                    . get_debug_type($value)
                    . ' given.',
            ),
        };

        if ($string === '' || strlen($string) > self::MAX_VALUE_BYTES) {
            $maxBytes = self::MAX_VALUE_BYTES;

            throw new InvalidArgumentException("Filter \"{$name}\" values must be 1-{$maxBytes} bytes long.");
        }

        return $string;
    }
}
