<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Tests\Fixtures;

use Atomicptr\Symfony\IndexedSearch\IndexedSearchBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use Override;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, mixed> $searchConfig
     */
    public function __construct(
        private readonly array $searchConfig = [],
    ) {
        parent::__construct('test', false);
    }

    #[Override]
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new DoctrineBundle();
        yield new DoctrineMigrationsBundle();
        yield new IndexedSearchBundle();
    }

    #[Override]
    public function getProjectDir(): string
    {
        return dirname(__DIR__, 2);
    }

    #[Override]
    public function getCacheDir(): string
    {
        // the database comes from %env()% so it's resolved at runtime, only the search config changes the container
        return "{$this->getProjectDir()}/var/cache/test/" . md5(serialize($this->searchConfig));
    }

    #[Override]
    public function getLogDir(): string
    {
        return "{$this->getProjectDir()}/var/log";
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'test' => true,
            'secret' => bin2hex(random_bytes(16)),
            'http_method_override' => false,
            'php_errors' => ['log' => false],
        ]);

        $container->extension('doctrine', [
            // Gets wiped for every test, don't point DATABASE_URL at anything with real data
            'dbal' => ['url' => '%env(resolve:DATABASE_URL)%'],
            'orm' => [
                'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
                'mappings' => [
                    'Fixtures' => [
                        'type' => 'attribute',
                        'dir' => __DIR__ . '/Entity',
                        'prefix' => 'Atomicptr\Symfony\IndexedSearch\Tests\Fixtures\Entity',
                    ],
                ],
            ],
        ]);

        $container->extension('doctrine_migrations', [
            'migrations_paths' => [
                'Atomicptr\Symfony\IndexedSearch\Tests\Migrations' => '%kernel.cache_dir%/migrations',
            ],
        ]);

        $container->extension('indexed_search', $this->searchConfig);
    }
}
