<?php

declare(strict_types=1);

namespace CoreAdapter\Tests\Unit;

use CoreAdapter\CoreClient;
use CoreAdapter\CoreConfig;
use CoreAdapter\ForbiddenMethods;
use CoreAdapter\Laravel\CoreClientServiceProvider;
use CoreAdapter\Services\Portal;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * L'identité du paquet : ce que Composer installe, ce que Laravel
 * auto-découvre, et la frontière entre le noyau et l'adaptateur.
 */
final class PackageTest extends TestCase
{
    public function test_the_package_is_named_and_psr_4_mapped(): void
    {
        $composer = $this->composer();

        self::assertSame('wilfreidlando/core-adapter', $composer['name']);
        self::assertSame('MIT', $composer['license']);

        $autoload = $composer['autoload'];
        self::assertIsArray($autoload);

        $psr4 = $autoload['psr-4'];
        self::assertIsArray($psr4);
        self::assertSame(['CoreAdapter\\' => 'src/'], $psr4);
    }

    public function test_it_has_no_runtime_dependency_but_php(): void
    {
        $composer = $this->composer();

        $require = $composer['require'];
        self::assertIsArray($require);
        self::assertSame(['php' => '^8.2'], $require);
    }

    public function test_the_laravel_provider_exists_and_is_auto_discovered(): void
    {
        $composer = $this->composer();

        $extra = $composer['extra'];
        self::assertIsArray($extra);

        $laravel = $extra['laravel'];
        self::assertIsArray($laravel);

        $providers = $laravel['providers'];
        self::assertIsArray($providers);
        self::assertCount(1, $providers);

        $provider = $providers[0];
        self::assertIsString($provider);
        self::assertTrue(class_exists($provider), $provider.' should exist.');
    }

    public function test_the_nucleus_does_not_depend_on_a_framework(): void
    {
        $root = dirname(__DIR__, 2).'/src';
        $glue = $root.DIRECTORY_SEPARATOR.'Laravel';
        $offenders = [];

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            // `src/Laravel` est le seul endroit où le framework a le droit
            // d'apparaître : c'est l'adaptateur.
            if (str_starts_with($file->getPathname(), $glue.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            if (str_contains($contents, 'Illuminate\\') || str_contains($contents, 'Laravel\\')) {
                $offenders[] = $file->getPathname();
            }
        }

        self::assertSame(
            [],
            $offenders,
            'Le noyau ne dépend d\'aucun framework. Trouvé(s) : '.implode(', ', $offenders),
        );
    }

    public function test_the_nucleus_sits_at_the_root_of_the_namespace(): void
    {
        // Le noyau est à la racine du namespace ; l'adaptateur est dessous.
        self::assertTrue(class_exists(CoreClient::class), CoreClient::class.' should exist.');
        self::assertTrue(class_exists(CoreConfig::class), CoreConfig::class.' should exist.');
        self::assertTrue(class_exists(ForbiddenMethods::class), ForbiddenMethods::class.' should exist.');
        self::assertTrue(class_exists(Portal::class), Portal::class.' should exist.');

        self::assertTrue(
            class_exists(CoreClientServiceProvider::class),
            CoreClientServiceProvider::class.' should exist.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function composer(): array
    {
        $raw = (string) file_get_contents(dirname(__DIR__, 2).'/composer.json');
        $decoded = json_decode($raw, true);

        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
