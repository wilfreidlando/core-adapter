<?php

declare(strict_types=1);

namespace SaasCommerce\Tests\Unit;

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

        self::assertSame('wilfreidlando/saas-commerce-dep', $composer['name']);
        self::assertSame('MIT', $composer['license']);

        $autoload = $composer['autoload'];
        self::assertIsArray($autoload);

        $psr4 = $autoload['psr-4'];
        self::assertIsArray($psr4);
        self::assertSame(['SaasCommerce\\' => 'src/'], $psr4);
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
        $root = dirname(__DIR__, 2).'/src/Core';
        $offenders = [];

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'php') {
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
