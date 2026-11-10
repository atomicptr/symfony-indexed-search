<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Enum;

/**
 * @api
 */
enum SearchSort
{
    /** Best match first */
    case Relevance;

    /** Best match first but older stuff ranks lower, documents without a date count as very old */
    case RelevanceWithRecency;

    /** Newest first, no date goes last */
    case Newest;

    /** Oldest first, no date goes first */
    case Oldest;
}
