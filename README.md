# Symfony Indexed Search

Indexed Search is a Symfony bundle that provides a straightforward database-powered search functionality for Doctrine
entities. It makes implementing search across your application's data simple, flexible and easy to integrate.

## Requirements

- PHP 8.5+ (ext-intl required) and Symfony 8
- Doctrine
- SQLite, MySQL, MariaDB or PostgreSQL

## Installation

```console
composer require atomicptr/symfony-indexed-search
```

Make sure it's installed in your `config/bundles.php`:

```php
Atomicptr\Symfony\IndexedSearch\IndexedSearchBundle::class => ['all' => true],
```

You might also need to create migrations for our index tables:

```console
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

## Usage

Mark what you want to search and what you want to filter by:

```php
#[ORM\Entity]
#[Searchable(filters: ['type' => 'product'])] // fixed filters for every product
class Product
{
    #[ORM\Column]
    #[SearchableText(weight: Weight::Highest)] // Highest, High, Normal (default) or Low
    private string $name;

    #[ORM\Column(type: 'text')]
    #[SearchableText]
    private string $description;

    #[ORM\Column]
    #[SearchableFilter('tenant')]
    private int $tenantId;

    #[ORM\Column]
    #[SearchableDate] // optional, for sorting by date
    private DateTimeImmutable $createdAt;
}
```

Build the index:

```console
bin/console indexed-search:index
```

And search:

```php
$result = $searchService->search(
    'red sho',
    filters: ['tenant' => 42, 'type' => ['product', 'article']],
    sort: SearchSort::Relevance, // or RelevanceWithRecency, Newest, Oldest
    page: 1,
    hydrate: true,
);

foreach ($result->hits as $hit) {
    $hit->entity; // your Product
}

$result->total;
$result->hasNextPage;
```

Every word has to match the start of a word somewhere in the text, so `red sho` finds "Red running shoes". Values of the
same filter are OR, different filters are AND

The index doesn't update itself, call `SearchIndexer::index()` or rerun the command when your entities change

### Translations

If an entity should be found in multiple languages, return one document per language instead of using the attributes:

```php
#[SearchableDocuments]
public function searchDocuments(): iterable
{
    yield Document::create('en')
        ->withText($this->name, Weight::Highest)
        ->withFilter('lang', 'en');

    yield Document::create('de')
        ->withText($this->nameGerman, Weight::Highest)
        ->withFilter('lang', 'de');
}
```

Search with `filters: ['lang' => 'de']` and `$hit->variant` tells you which one matched

## License

MIT
