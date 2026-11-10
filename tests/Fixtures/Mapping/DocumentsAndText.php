<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Tests\Fixtures\Mapping;

use Atomicptr\Symfony\IndexedSearch\Attribute\Searchable;
use Atomicptr\Symfony\IndexedSearch\Attribute\SearchableDocuments;
use Atomicptr\Symfony\IndexedSearch\Attribute\SearchableText;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[Searchable]
class DocumentsAndText
{
    #[ORM\Id]
    #[ORM\Column]
    public int $id = 1;

    #[SearchableText]
    public string $text = '';

    /**
     * @return list<never>
     */
    #[SearchableDocuments]
    public function documents(): array
    {
        return [];
    }
}
