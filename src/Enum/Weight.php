<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Enum;

/**
 * Relevance weight of indexed text
 *
 * @api
 */
enum Weight: string
{
    case Highest = 'highest';
    case High = 'high';
    case Normal = 'normal';
    case Low = 'low';

    /**
     * How much a word in text of this weight counts
     */
    public function multiplier(): float
    {
        return match ($this) {
            self::Highest => 10.0,
            self::High => 5.0,
            self::Normal => 2.0,
            self::Low => 1.0,
        };
    }
}
