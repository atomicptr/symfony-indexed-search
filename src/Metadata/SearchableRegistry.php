<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Metadata;

use Atomicptr\Symfony\IndexedSearch\Exception\InvalidArgumentException;
use Atomicptr\Symfony\IndexedSearch\ValueObject\SearchableClass;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Finds all searchable entities (once, on first use)
 *
 * @internal
 */
final class SearchableRegistry
{
    /** @var array<class-string, SearchableClass>|null */
    private ?array $classes = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MetadataLoader $loader,
    ) {
    }

    /**
     * @return array<class-string, SearchableClass>
     */
    public function all(): array
    {
        if ($this->classes !== null) {
            return $this->classes;
        }

        $classes = [];

        foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            $searchable = $this->loader->load($metadata);

            if ($searchable !== null) {
                $classes[$searchable->class] = $searchable;
            }
        }

        ksort($classes);

        return $this->classes = $classes;
    }

    /**
     * Mapping for an entity or class, proxies work too
     *
     * @throws InvalidArgumentException
     */
    public function get(object|string $entityOrClass): SearchableClass
    {
        $class = is_object($entityOrClass) ? $entityOrClass::class : $entityOrClass;
        $all = $this->all();

        if (array_key_exists($class, $all)) {
            return $all[$class];
        }

        if (class_exists($class) && !$this->entityManager->getMetadataFactory()->isTransient($class)) {
            $mapped = $all[$this->entityManager->getClassMetadata($class)->getName()] ?? null;

            if ($mapped !== null) {
                return $mapped;
            }
        }

        throw new InvalidArgumentException("{$class} is not a searchable entity.");
    }
}
