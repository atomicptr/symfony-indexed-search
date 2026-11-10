<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Tests\Fixtures\Entity;

use Atomicptr\Symfony\IndexedSearch\Attribute\Searchable;
use Atomicptr\Symfony\IndexedSearch\Attribute\SearchableText;
use Atomicptr\Symfony\IndexedSearch\Attribute\SearchableWhen;
use Atomicptr\Symfony\IndexedSearch\Enum\TextFormat;
use Atomicptr\Symfony\IndexedSearch\Enum\Weight;
use Doctrine\ORM\Mapping as ORM;

/**
 * Only indexed while visible, with an HTML body
 */
#[ORM\Entity]
#[Searchable]
class Article
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column]
    #[SearchableText(weight: Weight::Highest)]
    public string $title = '';

    #[ORM\Column]
    #[SearchableText(format: TextFormat::Html)]
    public string $body = '';

    #[ORM\Column]
    #[SearchableWhen]
    public bool $visible = true;
}
