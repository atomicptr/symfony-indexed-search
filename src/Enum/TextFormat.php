<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Enum;

use Atomicptr\Symfony\IndexedSearch\Exception\InvalidArgumentException;

/**
 * How indexed text is written
 *
 * @api
 */
enum TextFormat
{
    case Plain;

    /** Script and style elements are removed, other tags stripped and entities decoded */
    case Html;

    private const string NON_TEXT_ELEMENTS = '~<(script|style)\b.*?</\1\s*>~is';

    // every other tag, like <p>, <li> or <br>, separates words, these don't: Co<b>ff</b>ee stays one word
    private const string WORD_SEPARATING_TAGS = '~<(?!/?(?:a|abbr|b|bdi|bdo|cite|code|data|del|dfn|em|i|ins|kbd|mark|q|s|samp|small|span|strong|sub|sup|time|u|var)\b)~i';

    /**
     * @throws InvalidArgumentException If the HTML can't be processed
     */
    public function toPlain(string $text): string
    {
        return match ($this) {
            self::Plain => $text,
            self::Html => html_entity_decode(
                strip_tags(self::replace(
                    self::WORD_SEPARATING_TAGS,
                    ' <',
                    self::replace(self::NON_TEXT_ELEMENTS, ' ', $text),
                )),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8',
            ),
        };
    }

    private static function replace(string $pattern, string $replacement, string $text): string
    {
        return (
            preg_replace($pattern, $replacement, $text) ?? throw new InvalidArgumentException(
                'Could not convert HTML to text: ' . preg_last_error_msg(),
            )
        );
    }
}
