<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Tests\Fixtures;

use Atomicptr\Symfony\IndexedSearch\Service\SearchIndexer;
use Atomicptr\Symfony\IndexedSearch\Service\SearchService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final readonly class TestApp
{
    private function __construct(
        private TestKernel $kernel,
    ) {
    }

    /**
     * @param array<string, mixed> $searchConfig
     */
    public static function boot(array $searchConfig = []): self
    {
        $kernel = new TestKernel($searchConfig);
        $kernel->boot();
        $app = new self($kernel);

        $entityManager = $app->entityManager();
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($metadata);

        return $app;
    }

    public function shutdown(): void
    {
        $this->kernel->shutdown();
    }

    public function entityManager(): EntityManagerInterface
    {
        return $this->service(EntityManagerInterface::class);
    }

    public function search(): SearchService
    {
        return $this->service(SearchService::class);
    }

    public function indexer(): SearchIndexer
    {
        return $this->service(SearchIndexer::class);
    }

    public function command(string $name): CommandTester
    {
        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        return new CommandTester($application->find($name));
    }

    public function migrationsDirectory(): string
    {
        return "{$this->kernel->getCacheDir()}/migrations";
    }

    /**
     * Persists, flushes and indexes the entities
     */
    public function store(object ...$entities): void
    {
        foreach ($entities as $entity) {
            $this->entityManager()->persist($entity);
        }

        $this->entityManager()->flush();
        $this->indexer()->index($entities);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    public function service(string $id): object
    {
        $service = $this->kernel->getContainer()->get('test.service_container')->get($id);

        if (!$service instanceof $id) {
            throw new LogicException("Service {$id} not found.");
        }

        return $service;
    }
}
