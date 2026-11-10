<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Tests\Fixtures\Entity;

use Atomicptr\Symfony\IndexedSearch\Attribute\Searchable;
use Atomicptr\Symfony\IndexedSearch\Attribute\SearchableDocuments;
use Atomicptr\Symfony\IndexedSearch\Enum\Weight;
use Atomicptr\Symfony\IndexedSearch\ValueObject\Document;
use Doctrine\ORM\Mapping as ORM;

/**
 * Translations in separate fields, indexed as one document per language. Also has a string id and a fixed filter
 */
#[ORM\Entity]
#[Searchable(filters: ['type' => 'recipe'])]
class Recipe
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
        #[ORM\Column]
        public string $name,
        #[ORM\Column(nullable: true)]
        public ?string $nameGerman = null,
        #[ORM\Column]
        public int $tenantId = 1,
    ) {
    }

    /**
     * @return iterable<Document>
     */
    #[SearchableDocuments]
    public function searchDocuments(): iterable
    {
        yield Document::create('en')
            ->withText($this->name, Weight::Highest)
            ->withFilter('lang', 'en')
            ->withFilter('tenant', $this->tenantId);

        yield Document::create('de')
            ->withText($this->nameGerman, Weight::Highest)
            ->withFilter('lang', 'de')
            ->withFilter('tenant', $this->tenantId);
    }
}
