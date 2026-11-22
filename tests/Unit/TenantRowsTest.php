<?php

declare(strict_types=1);

namespace CoreAdapter\Tests\Unit;

use CoreAdapter\Laravel\Tenant\TenantRows;
use PHPUnit\Framework\TestCase;

/**
 * La ligne d'un locataire, dans les colonnes que le Core attend.
 *
 * Le Core lit la liste des clients comme il lit un fichier d'import : une
 * ligne par client, une colonne par clé. Ce test regarde la ligne **telle
 * qu'elle part**, pas celle que le paquet a prévue.
 */
final class TenantRowsTest extends TestCase
{
    public function test_the_identity_is_carried_as_it_is(): void
    {
        $ligne = TenantRows::build([
            'external_ref' => 'AE0042',
            'display_name' => 'Auto-école du Centre',
            'email' => 'contact@exemple.test',
            'phone' => '+33600000000',
        ], []);

        self::assertSame('AE0042', $ligne['external_ref']);
        self::assertSame('Auto-école du Centre', $ligne['display_name']);
        self::assertSame('contact@exemple.test', $ligne['email']);
        self::assertSame('+33600000000', $ligne['phone']);
    }

    public function test_a_declared_key_carries_the_value_the_application_knows(): void
    {
        $ligne = TenantRows::build([
            'external_ref' => 'AE0042',
            'display_name' => 'Auto-école du Centre',
            'email' => 'contact@exemple.test',
            'phone' => null,
            'localisation' => 'Lyon',
            'nom_respo' => 'Madame Dupont',
            'annee_creation' => 2019,
        ], [
            ['cle' => 'localisation', 'source' => 'declared.localisation'],
            ['cle' => 'nom_respo', 'source' => 'declared.nom_respo'],
            ['cle' => 'annee_creation', 'source' => 'declared.annee_creation'],
        ]);

        self::assertSame('Lyon', $ligne['localisation']);
        self::assertSame('Madame Dupont', $ligne['nom_respo']);
        self::assertSame(2019, $ligne['annee_creation']);
    }

    public function test_a_key_that_replaces_an_identity_field_carries_that_identity(): void
    {
        $ligne = TenantRows::build([
            'external_ref' => 'AE0042',
            'display_name' => 'Auto-école du Centre',
            'email' => 'contact@exemple.test',
            'phone' => '+33600000000',
        ], [
            ['cle' => 'nom', 'source' => 'core_identity.display_name'],
            ['cle' => 'courriel', 'source' => 'core_identity.email'],
        ]);

        self::assertSame('Auto-école du Centre', $ligne['nom']);
        self::assertSame('contact@exemple.test', $ligne['courriel']);
    }

    public function test_a_missing_value_stays_empty_rather_than_guessed(): void
    {
        $ligne = TenantRows::build([
            'external_ref' => 'AE0042',
        ], [
            ['cle' => 'localisation', 'source' => 'declared.localisation'],
        ]);

        self::assertNull($ligne['localisation']);
        self::assertNull($ligne['display_name']);
        self::assertNull($ligne['email']);
    }

    public function test_an_unknown_source_yields_nothing(): void
    {
        $ligne = TenantRows::build([
            'external_ref' => 'AE0042',
            'localisation' => 'Lyon',
        ], [
            ['cle' => 'localisation', 'source' => 'core_identity.quelque_chose'],
        ]);

        self::assertNull($ligne['localisation']);
    }
}
