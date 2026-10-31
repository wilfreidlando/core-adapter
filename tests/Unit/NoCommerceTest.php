<?php

declare(strict_types=1);

namespace CoreAdapter\Tests\Unit;

use CoreAdapter\ForbiddenMethods;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use SplFileInfo;

/**
 * Le paquet ne fait **pas** de commerce — et ce n'est pas une promesse de
 * documentation : c'est une propriété vérifiée.
 *
 * Le Core est le maître de la relation commerciale : une application cliente
 * lit ses droits, compte son usage, ouvre son portail et reçoit ses
 * événements. Elle ne crée ni client, ni abonnement, ni facture, ni offre, et
 * n'enregistre pas son point d'entrée webhook. Ce test parcourt **toutes** les
 * classes du paquet et échoue si l'une d'elles expose un de ces noms de
 * méthodes.
 *
 * ⚠️ Les noms interdits n'apparaissent que dans `ForbiddenMethods` (et ici) :
 * ce sont des interdits, pas des fonctionnalités.
 */
final class NoCommerceTest extends TestCase
{
    public function test_the_package_exposes_no_commerce_write_method(): void
    {
        $package = $this->packageClasses();
        $own = array_flip($package);
        $offenders = [];

        foreach ($package as $class) {
            $reflection = new ReflectionClass($class);

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                // Seules les méthodes **du paquet** comptent : une méthode
                // héritée du framework (celles de Symfony, par exemple) n'est
                // pas la surface de ce paquet.
                if (! isset($own[$method->getDeclaringClass()->getName()])) {
                    continue;
                }

                foreach (ForbiddenMethods::NAMES as $forbidden) {
                    if (str_contains(strtolower($method->getName()), strtolower($forbidden))) {
                        $offenders[] = $class.'::'.$method->getName();
                    }
                }
            }
        }

        self::assertSame(
            [],
            $offenders,
            'Le paquet n\'expose aucune méthode de commerce. Trouvé(s) : '.implode(', ', $offenders),
        );
    }

    public function test_the_forbidden_names_are_the_ones_we_think(): void
    {
        self::assertSame([
            'createCustomer',
            'deleteCustomer',
            'subscribe',
            'createInvoice',
            'issueInvoice',
            'createPlan',
            'publishPlan',
            'registerWebhookEndpoint',
        ], ForbiddenMethods::NAMES);
    }

    /**
     * @return list<class-string>
     */
    private function packageClasses(): array
    {
        $root = dirname(__DIR__, 2).'/src';
        $classes = [];

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($root) + 1);
            $class = 'CoreAdapter\\'.str_replace(['/', '.php'], ['\\', ''], $relative);

            if (class_exists($class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }
}
