<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Tests\Fixtures\Entity;

use Atomicptr\Symfony\IndexedSearch\Attribute\Searchable;
use Atomicptr\Symfony\IndexedSearch\Attribute\SearchableDate;
use Atomicptr\Symfony\IndexedSearch\Attribute\SearchableFilter;
use Atomicptr\Symfony\IndexedSearch\Attribute\SearchableText;
use Atomicptr\Symfony\IndexedSearch\Enum\Weight;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[Searchable(filters: ['type' => 'product', 'area' => ['global', 'shop']])]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    /**
     * @param list<string> $labels
     */
    public function __construct(
        #[ORM\Column]
        #[SearchableText(weight: Weight::Highest)]
        public string $name,
        #[ORM\Column(nullable: true)]
        #[SearchableText(weight: Weight::High)]
        public ?string $sku = null,
        #[ORM\Column(nullable: true)]
        #[SearchableText]
        public ?string $summary = null,
        #[ORM\Column(nullable: true)]
        #[SearchableText(weight: Weight::Low)]
        private ?string $body = null,
        #[ORM\Column(enumType: Category::class)]
        #[SearchableFilter('category')]
        public Category $category = Category::Shoes,
        #[ORM\Column(type: 'json')]
        public array $labels = [],
        #[ORM\Column(nullable: true)]
        #[SearchableFilter('tenant')]
        public ?int $tenantId = null,
        #[ORM\Column(nullable: true)]
        #[SearchableDate]
        public ?DateTimeImmutable $publishedAt = null,
        #[ORM\Column]
        #[SearchableFilter('published')]
        public bool $published = true,
    ) {
    }

    /**
     * @return list<string>
     */
    #[SearchableFilter('label')]
    public function getLabels(): array
    {
        return $this->labels;
    }

    public function setBody(?string $body): void
    {
        $this->body = $body;
    }
}
