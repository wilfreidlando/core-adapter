<?php

declare(strict_types=1);

namespace CoreAdapter\Tests\Unit;

use CoreAdapter\Services\TenantSchemas;
use CoreAdapter\Tests\TestCase;

/**
 * La proposition de la forme du locataire.
 *
 * L'application dit au Core la forme de son locataire ; le Core ne la rend
 * qu'après que l'opérateur l'a lue et acceptée. Ce test vérifie le geste
 * lui-même : la méthode, le chemin, le corps.
 */
final class TenantSchemasTest extends TestCase
{
    public function test_the_declaration_is_proposed_to_the_project(): void
    {
        $this->transport->json(201, [
            'id' => 'sch_1',
            'project_id' => 'prj_1',
            'version' => 1,
            'status' => 'pending',
            'unique_key' => 'code',
            'unique_key_pattern' => '/^AE[0-9]{4}$/',
            'fields' => [],
        ]);

        $declaration = [
            'unique_key' => 'code',
            'unique_key_pattern' => '/^AE[0-9]{4}$/',
            'fields' => [[
                'key' => 'nom',
                'label' => 'Raison sociale',
                'type' => 'string',
                'required' => true,
                'max_length' => 200,
                'source' => 'core_identity.display_name',
            ]],
        ];

        (new TenantSchemas($this->client))->propose('prj_1', $declaration);

        $requete = $this->transport->lastRequest();

        self::assertSame('PUT', $requete['method']);
        self::assertSame('https://core.test/v1/projects/prj_1/tenant-schema', $requete['url']);
        self::assertSame($declaration, $this->sentBody());
    }

    public function test_the_form_in_force_is_read_from_the_core(): void
    {
        $this->transport->json(200, [
            'accepted' => [
                'id' => 'sch_1',
                'version' => 1,
                'status' => 'accepted',
                'unique_key' => 'code',
                'unique_key_pattern' => '^AE[0-9]{4}$',
                'fields' => [],
            ],
            'pending' => null,
        ]);

        $accepted = (new TenantSchemas($this->client))->accepted('prj_1');

        self::assertNotNull($accepted);
        self::assertSame('accepted', $accepted['status']);
        self::assertSame(1, $accepted['version']);

        $requete = $this->transport->lastRequest();

        self::assertSame('GET', $requete['method']);
        self::assertSame('https://core.test/v1/projects/prj_1/tenant-schema', $requete['url']);
    }

    public function test_the_pending_proposal_is_read_beside_the_form_in_force(): void
    {
        $this->transport->json(200, [
            'accepted' => ['id' => 'sch_1', 'version' => 1, 'status' => 'accepted'],
            'pending' => ['id' => 'sch_2', 'version' => 2, 'status' => 'pending'],
        ]);

        $state = (new TenantSchemas($this->client))->state('prj_1');

        self::assertIsArray($state['accepted']);
        self::assertIsArray($state['pending']);
        self::assertSame(2, $state['pending']['version']);
    }

    public function test_a_project_without_any_form_reads_nothing(): void
    {
        $this->transport->json(200, ['accepted' => null, 'pending' => null]);

        $state = (new TenantSchemas($this->client))->state('prj_1');

        self::assertNull($state['accepted']);
        self::assertNull($state['pending']);
    }
}
