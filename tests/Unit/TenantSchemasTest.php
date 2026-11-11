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
}
