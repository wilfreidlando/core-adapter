<?php

declare(strict_types=1);

namespace CoreAdapter\Tests\Unit;

use CoreAdapter\Laravel\Http\ListTenantsController;
use CoreAdapter\Laravel\Http\ShowTenantSchemaController;
use CoreAdapter\Laravel\Tenant\TenantDeclaration;
use CoreAdapter\Laravel\Tenant\TenantLister;
use CoreAdapter\Tests\Fakes\FakeTenantDeclaration;
use CoreAdapter\Tests\Fakes\FakeTenantLister;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * Les deux lectures que le Core vient chercher chez l'application.
 *
 * Ce que le Core reçoit doit être **exactement** ce qu'il attend : la forme
 * dans le format de `PUT /v1/projects/{id}/tenant-schema`, les clients dans
 * les colonnes du fichier d'import.
 */
final class ApplicationReadsTest extends TestCase
{
    public function test_the_declaration_is_rendered_in_the_words_of_the_core(): void
    {
        $container = new Container;
        $container->instance(TenantDeclaration::class, new FakeTenantDeclaration(
            [
                ['cle' => 'nom', 'source' => 'core_identity.display_name', 'requis' => true, 'libelle' => 'Nom'],
                ['cle' => 'localisation', 'source' => 'declared.localisation'],
            ],
            '/^AE[0-9]{4}(?:[0-9]{2}){0,2}$/',
        ));

        $request = Request::create('/core/tenant-schema', 'GET');
        $response = (new ShowTenantSchemaController($container))($request);

        self::assertSame(200, $response->getStatusCode());

        $charge = $this->json((string) $response->getContent());

        self::assertSame('code', $charge['unique_key']);
        self::assertSame('^AE[0-9]{4}([0-9]{2}){0,2}$', $charge['unique_key_pattern']);

        /** @var list<array<string, mixed>> $champs */
        $champs = $charge['fields'];

        self::assertCount(2, $champs);
        self::assertSame('nom', $champs[0]['key']);
        self::assertSame('core_identity.display_name', $champs[0]['source']);
        self::assertTrue($champs[0]['required']);
        self::assertSame('Nom', $champs[0]['label']);
    }

    public function test_an_application_without_a_declaration_says_so(): void
    {
        $request = Request::create('/core/tenant-schema', 'GET');
        $response = (new ShowTenantSchemaController(new Container))($request);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('tenant_schema_not_declared', $this->json((string) $response->getContent())['error']);
    }

    public function test_the_tenants_are_rendered_in_the_columns_of_the_core(): void
    {
        $container = new Container;
        $container->instance(TenantLister::class, new FakeTenantLister([
            [
                'external_ref' => 'AE0042',
                'display_name' => 'Auto-école du Centre',
                'email' => 'contact@exemple.test',
                'phone' => null,
                'localisation' => 'Lyon',
            ],
        ]));
        $container->instance(TenantDeclaration::class, new FakeTenantDeclaration([
            ['cle' => 'nom', 'source' => 'core_identity.display_name'],
            ['cle' => 'localisation', 'source' => 'declared.localisation'],
        ]));

        $request = Request::create('/core/tenants', 'POST', [], [], [], [], '{"page":1,"per_page":100}');
        $response = (new ListTenantsController($container))($request);

        self::assertSame(200, $response->getStatusCode());

        $charge = $this->json((string) $response->getContent());

        /** @var list<array<string, mixed>> $data */
        $data = $charge['data'];

        self::assertCount(1, $data);
        self::assertSame('AE0042', $data[0]['external_ref']);
        self::assertSame('Auto-école du Centre', $data[0]['nom']);
        self::assertSame('Lyon', $data[0]['localisation']);

        /** @var array<string, mixed> $meta */
        $meta = $charge['meta'];

        self::assertSame(1, $meta['count']);
        self::assertFalse($meta['has_more']);
    }

    public function test_the_pagination_is_read_in_the_signed_body(): void
    {
        $container = new Container;
        $container->instance(TenantLister::class, new FakeTenantLister([
            ['external_ref' => 'AE0001', 'display_name' => 'Un'],
            ['external_ref' => 'AE0002', 'display_name' => 'Deux'],
            ['external_ref' => 'AE0003', 'display_name' => 'Trois'],
        ]));

        $request = Request::create('/core/tenants', 'POST', [], [], [], [], '{"page":2,"per_page":2}');
        $response = (new ListTenantsController($container))($request);

        $charge = $this->json((string) $response->getContent());

        /** @var list<array<string, mixed>> $data */
        $data = $charge['data'];

        self::assertCount(1, $data);
        self::assertSame('AE0003', $data[0]['external_ref']);

        /** @var array<string, mixed> $meta */
        $meta = $charge['meta'];

        self::assertSame(2, $meta['page']);
        self::assertSame(2, $meta['per_page']);
        self::assertFalse($meta['has_more']);
    }

    public function test_a_page_that_stops_short_announces_the_end(): void
    {
        $container = new Container;
        $container->instance(TenantLister::class, new FakeTenantLister([
            ['external_ref' => 'AE0001', 'display_name' => 'Un'],
            ['external_ref' => 'AE0002', 'display_name' => 'Deux'],
            ['external_ref' => 'AE0003', 'display_name' => 'Trois'],
        ]));

        $request = Request::create('/core/tenants', 'POST', [], [], [], [], '{"page":1,"per_page":2}');
        $response = (new ListTenantsController($container))($request);

        $charge = $this->json((string) $response->getContent());

        /** @var list<array<string, mixed>> $data */
        $data = $charge['data'];

        /** @var array<string, mixed> $meta */
        $meta = $charge['meta'];

        self::assertCount(2, $data);
        self::assertTrue($meta['has_more']);
    }

    public function test_an_application_that_cannot_list_says_so(): void
    {
        $request = Request::create('/core/tenants', 'POST', [], [], [], [], '{}');
        $response = (new ListTenantsController(new Container))($request);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('tenant_listing_not_supported', $this->json((string) $response->getContent())['error']);
    }

    /**
     * Le corps décodé.
     *
     * @return array<string, mixed>
     */
    private function json(string $content): array
    {
        $decoded = json_decode($content, true);

        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
