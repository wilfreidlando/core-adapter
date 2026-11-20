<?php

declare(strict_types=1);

namespace CoreAdapter\Tests\Unit;

use CoreAdapter\Laravel\Tenant\TenantSchemaDeclarer;
use CoreAdapter\Services\TenantSchemas;
use CoreAdapter\Support\DeclarationRefused;
use CoreAdapter\Support\PatternRefused;
use CoreAdapter\Tests\Fakes\FakeTenantDeclaration;
use CoreAdapter\Tests\TestCase;

/**
 * La déclaration de la forme du locataire, **telle qu'elle part**.
 *
 * Le motif de la clé unique est un motif `preg` chez l'application, et un
 * motif nu chez le Core : la commande ne peut pas l'envoyer tel quel. Ce
 * test regarde ce qui est **réellement** envoyé à
 * `PUT /v1/projects/{id}/tenant-schema` — pas ce que le paquet a prévu.
 */
final class TenantSchemaDeclarationTest extends TestCase
{
    public function test_the_pattern_really_sent_to_the_core_is_normalized(): void
    {
        $this->transport->json(201, [
            'id' => 'sch_1',
            'project_id' => 'prj_1',
            'version' => 1,
            'status' => 'pending',
            'unique_key' => 'code',
            'unique_key_pattern' => '^AE[0-9]{4}([0-9]{2}){0,2}$',
            'fields' => [],
        ]);

        $declaration = new FakeTenantDeclaration(
            [
                ['cle' => 'localisation', 'source' => 'declared.localisation', 'libelle' => 'Localisation'],
            ],
            '/^AE[0-9]{4}(?:[0-9]{2}){0,2}$/',
        );

        $this->declarer()->declare('prj_1', $declaration);

        $requete = $this->transport->lastRequest();

        self::assertSame('PUT', $requete['method']);
        self::assertSame('https://core.test/v1/projects/prj_1/tenant-schema', $requete['url']);

        $corps = $this->sentBody();

        self::assertSame('code', $corps['unique_key']);
        self::assertSame('^AE[0-9]{4}([0-9]{2}){0,2}$', $corps['unique_key_pattern']);
    }

    public function test_the_fields_are_sent_in_the_words_of_the_core(): void
    {
        $this->transport->json(201, ['status' => 'pending', 'version' => 1]);

        $declaration = new FakeTenantDeclaration([
            ['cle' => 'nom', 'source' => 'core_identity.display_name', 'requis' => true, 'libelle' => 'Raison sociale', 'type' => 'text', 'longueur' => 200],
            ['cle' => 'localisation', 'source' => 'declared.localisation'],
        ]);

        $this->declarer()->declare('prj_1', $declaration);

        $corps = $this->sentBody();

        self::assertSame([
            [
                'key' => 'nom',
                'label' => 'Raison sociale',
                'type' => 'text',
                'required' => true,
                'max_length' => 200,
                'source' => 'core_identity.display_name',
            ],
            [
                'key' => 'localisation',
                'label' => 'localisation',
                'type' => 'string',
                'required' => false,
                'max_length' => null,
                'source' => 'declared.localisation',
            ],
        ], $corps['fields']);
    }

    public function test_a_pattern_outside_the_alphabet_fails_locally_and_nothing_is_sent(): void
    {
        $declaration = new FakeTenantDeclaration([], '/^AE[0-9]{4}é$/');

        try {
            $this->declarer()->declare('prj_1', $declaration);
            self::fail('La déclaration aurait dû être refusée.');
        } catch (PatternRefused $e) {
            self::assertStringContainsString('ne peut pas être envoyé au Core', $e->getMessage());
            self::assertStringContainsString('motif **nu**', $e->getMessage());
        }

        self::assertSame([], $this->transport->requests());
    }

    public function test_a_declared_source_must_name_the_key_of_its_field(): void
    {
        $declaration = new FakeTenantDeclaration([
            ['cle' => 'localisation', 'source' => 'declared.emplacement'],
        ]);

        try {
            $this->declarer()->declare('prj_1', $declaration);
            self::fail('La déclaration aurait dû être refusée.');
        } catch (DeclarationRefused $e) {
            self::assertStringContainsString('declared.emplacement', $e->getMessage());
            self::assertStringContainsString('doit nommer la clé du champ', $e->getMessage());
        }

        self::assertSame([], $this->transport->requests());
    }

    private function declarer(): TenantSchemaDeclarer
    {
        return new TenantSchemaDeclarer(new TenantSchemas($this->client));
    }
}
