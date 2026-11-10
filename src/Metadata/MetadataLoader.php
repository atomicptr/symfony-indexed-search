<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Metadata;

use Atomicptr\Symfony\IndexedSearch\Attribute\Searchable;
use Atomicptr\Symfony\IndexedSearch\Attribute\SearchableDate;
use Atomicptr\Symfony\IndexedSearch\Attribute\SearchableDocuments;
use Atomicptr\Symfony\IndexedSearch\Attribute\SearchableFilter;
use Atomicptr\Symfony\IndexedSearch\Attribute\SearchableText;
use Atomicptr\Symfony\IndexedSearch\Enum\MemberKind;
use Atomicptr\Symfony\IndexedSearch\Exception\InvalidArgumentException;
use Atomicptr\Symfony\IndexedSearch\Exception\InvalidMappingException;
use Atomicptr\Symfony\IndexedSearch\Indexing\FilterNormalizer;
use Atomicptr\Symfony\IndexedSearch\ValueObject\Member;
use Atomicptr\Symfony\IndexedSearch\ValueObject\SearchableClass;
use Atomicptr\Symfony\IndexedSearch\ValueObject\FilterMapping;
use Atomicptr\Symfony\IndexedSearch\ValueObject\TextMapping;
use Doctrine\ORM\Mapping\ClassMetadata;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Reads and validates the searchable attributes of an entity
 *
 * @internal
 */
final readonly class MetadataLoader
{
    /**
     * @param ClassMetadata<object> $metadata
     *
     * @throws InvalidMappingException
     */
    public function load(ClassMetadata $metadata): ?SearchableClass
    {
        $reflection = $metadata->getReflectionClass();

        if ($metadata->isMappedSuperclass || $metadata->isEmbeddedClass || $reflection->isAbstract()) {
            return null;
        }

        $searchable = $this->findSearchable($reflection);

        if ($searchable === null) {
            return null;
        }

        $class = $metadata->getName();
        $texts = [];
        $filters = [];
        $date = null;
        $documents = null;

        foreach ($this->members($reflection) as $member => $reflector) {
            foreach ($reflector->getAttributes(SearchableText::class) as $attribute) {
                $texts[] = new TextMapping($member, $attribute->newInstance()->weight);
            }

            foreach ($reflector->getAttributes(SearchableFilter::class) as $attribute) {
                $filters[] = new FilterMapping($this->filterName($attribute->newInstance()->name, $class), $member);
            }

            if (count($reflector->getAttributes(SearchableDate::class)) > 0) {
                $date = $this->single($date, $member, SearchableDate::class, $class);
            }

            if (count($reflector->getAttributes(SearchableDocuments::class)) > 0) {
                if (!$reflector instanceof ReflectionMethod) {
                    throw new InvalidMappingException(
                        "#[SearchableDocuments] must be used on a method, {$member->label()} given.",
                    );
                }

                $documents = $this->single($documents, $member, SearchableDocuments::class, $class);
            }
        }

        if ($documents !== null && (count($texts) > 0 || count($filters) > 0)) {
            throw new InvalidMappingException(
                "{$class} combines #[SearchableDocuments] with #[SearchableText]/#[SearchableFilter]; build the documents in {$documents->label()} instead.",
            );
        }

        if ($documents === null && count($texts) === 0) {
            throw new InvalidMappingException(
                "{$class} is #[Searchable] but has nothing to index. Add #[SearchableText] to a property or method, or a #[SearchableDocuments] method.",
            );
        }

        return new SearchableClass(
            $class,
            $this->idField($metadata),
            $this->classFilters($searchable, $class),
            $texts,
            $filters,
            $date,
            $documents,
        );
    }

    /**
     * @param ReflectionClass<covariant object> $reflection
     */
    private function findSearchable(ReflectionClass $reflection): ?Searchable
    {
        for ($current = $reflection; $current !== false; $current = $current->getParentClass()) {
            $attributes = $current->getAttributes(Searchable::class);

            if (count($attributes) > 0) {
                return $attributes[0]->newInstance();
            }
        }

        return null;
    }

    /**
     * Properties and methods of the class hierarchy, subclass declarations win
     *
     * @param ReflectionClass<covariant object> $reflection
     *
     * @return iterable<Member, ReflectionProperty|ReflectionMethod>
     */
    private function members(ReflectionClass $reflection): iterable
    {
        $seen = [];

        for ($current = $reflection; $current !== false; $current = $current->getParentClass()) {
            $reflectors = [...$current->getProperties(), ...$current->getMethods()];

            foreach ($reflectors as $reflector) {
                $kind = $reflector instanceof ReflectionProperty ? MemberKind::Property : MemberKind::Method;
                $key = "{$kind->name}:{$reflector->name}";

                if (
                    $reflector->getDeclaringClass()->getName() !== $current->getName()
                    || array_key_exists($key, $seen)
                ) {
                    continue;
                }

                $seen[$key] = true;

                if (!$this->hasBundleAttribute($reflector)) {
                    continue;
                }

                $member = new Member($current->getName(), $reflector->name, $kind);
                $this->assertReadable($reflector, $member);

                yield $member => $reflector;
            }
        }
    }

    private function hasBundleAttribute(ReflectionProperty|ReflectionMethod $reflector): bool
    {
        foreach ([
            SearchableText::class,
            SearchableFilter::class,
            SearchableDate::class,
            SearchableDocuments::class,
        ] as $attribute) {
            if (count($reflector->getAttributes($attribute)) > 0) {
                return true;
            }
        }

        return false;
    }

    private function assertReadable(ReflectionProperty|ReflectionMethod $reflector, Member $member): void
    {
        if ($reflector->isStatic()) {
            throw new InvalidMappingException(
                "Searchable attributes cannot be used on static member {$member->label()}.",
            );
        }

        if ($reflector instanceof ReflectionMethod && $reflector->getNumberOfRequiredParameters() > 0) {
            throw new InvalidMappingException(
                "Searchable attributes require a method without required parameters, {$member->label()} given.",
            );
        }
    }

    /**
     * @param class-string $attribute
     */
    private function single(?Member $existing, Member $member, string $attribute, string $class): Member
    {
        if ($existing !== null) {
            throw new InvalidMappingException(
                "{$class} declares #[{$attribute}] more than once ({$existing->label()} and {$member->label()}).",
            );
        }

        return $member;
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function idField(ClassMetadata $metadata): string
    {
        if ($metadata->isIdentifierComposite) {
            throw new InvalidMappingException(
                "Searchable entity {$metadata->getName()} uses a composite identifier, which is not supported.",
            );
        }

        $field = $metadata->getSingleIdentifierFieldName();

        if (!$metadata->hasField($field)) {
            throw new InvalidMappingException(
                "Searchable entity {$metadata->getName()} uses an association as identifier, which is not supported.",
            );
        }

        return $field;
    }

    /**
     * @return array<string, list<string>>
     */
    private function classFilters(Searchable $searchable, string $class): array
    {
        $filters = [];

        try {
            foreach ($searchable->filters as $name => $values) {
                $name = FilterNormalizer::name($name);

                foreach (is_array($values) ? $values : [$values] as $value) {
                    $filters[$name][] = FilterNormalizer::value($name, $value);
                }

                $filters[$name] = array_values(array_unique($filters[$name] ?? []));
            }
        } catch (InvalidArgumentException $exception) {
            throw new InvalidMappingException("{$class}: {$exception->getMessage()}", previous: $exception);
        }

        return $filters;
    }

    private function filterName(string $name, string $class): string
    {
        try {
            return FilterNormalizer::name($name);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidMappingException("{$class}: {$exception->getMessage()}", previous: $exception);
        }
    }
}
