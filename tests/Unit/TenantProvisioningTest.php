<?php

declare(strict_types=1);

namespace CoreAdapter\Tests\Unit;

use CoreAdapter\CoreException;
use CoreAdapter\Laravel\Tenant\ProvisionsTenant;
use CoreAdapter\Laravel\Tenant\TenantProvisioned;
use CoreAdapter\Services\Customers;
use CoreAdapter\Tests\Fakes\FakeEventDispatcher;
use CoreAdapter\Tests\Fakes\FakeLogger;
use CoreAdapter\Tests\Fakes\FakeTenantDeclaration;
use CoreAdapter\Tests\Fakes\FakeTenantFactory;
use CoreAdapter\Tests\TestCase;
use DomainException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log;

/**
 * Le provisionnement du locataire — la seule chose que le paquet demande à
 * une application.
 */
final class TenantProvisioningTest extends TestCase
{
    private FakeTenantFactory $fabrique;

    private FakeTenantDeclaration $declaration;

    private FakeLogger $journal;

    private FakeEventDispatcher $evenements;

    public function test_a_known_tenant_is_linked_and_not_created_again(): void
    {
        $this->fabrique->existant = (object) ['code' => 'AE0042'];

        $this->provisionner('AE0042');

        $this->assertSame(['AE0042'], $this->fabrique->lies);
        $this->assertSame([], $this->fabrique->creations);
        $this->assertSame([], $this->transport->requests());
    }

    public function test_a_reference_out_of_format_creates_nothing(): void
    {
        $this->provisionner('PAS-UN-CODE');

        $this->assertSame([], $this->fabrique->creations);
        $this->assertSame([], $this->transport->requests());
        $this->assertNotSame([], $this->journal->avertissements);
    }

    public function test_the_tenant_is_created_with_the_values_read_from_the_core(): void
    {
        $this->transport->json(200, $this->clientEnCore());

        $this->provisionner('AE0042');

        $this->assertCount(1, $this->fabrique->creations);
        $this->assertSame('AE0042', $this->fabrique->creations[0]['cle']);
        $this->assertSame([
            'nom' => 'Auto-École du Centre',
            'email' => 'contact@exemple.cm',
            'telephone' => '+237 6 00 00 00 00',
        ], $this->fabrique->creations[0]['attributs']);

        $this->assertCount(1, $this->evenements->dispatches);
        $this->assertInstanceOf(TenantProvisioned::class, $this->evenements->dispatches[0]);
    }

    public function test_the_declared_values_read_from_the_core_reach_the_factory(): void
    {
        $this->declaration = new FakeTenantDeclaration([
            ['cle' => 'nom', 'source' => 'core_identity.display_name', 'requis' => true],
            ['cle' => 'localisation', 'source' => 'declared.localisation'],
        ]);

        $this->transport->json(200, $this->clientEnCore() + [
            'declared_attributes' => ['localisation' => 'Douala'],
        ]);

        $this->provisionner('AE0042');

        $this->assertCount(1, $this->fabrique->creations);
        $this->assertSame([
            'nom' => 'Auto-École du Centre',
            'localisation' => 'Douala',
        ], $this->fabrique->creations[0]['attributs']);
    }

    public function test_a_customer_the_core_does_not_know_creates_nothing(): void
    {
        $this->transport->json(404, ['type' => 'https://errors.core-system.internal/customer-not-found']);

        $this->provisionner('AE0042');

        $this->assertSame([], $this->fabrique->creations);
        $this->assertNotSame([], $this->journal->avertissements);
    }

    public function test_an_unavailable_core_plays_the_task_again(): void
    {
        $this->transport->json(503, ['type' => 'https://errors.core-system.internal/unavailable']);

        $this->expectException(CoreException::class);

        $this->provisionner('AE0042');
    }

    public function test_a_creation_that_cannot_succeed_stops_without_a_replay(): void
    {
        $this->transport->json(200, $this->clientEnCore());
        $this->fabrique->echec = new DomainException('Le client du Core n’a pas d’e-mail.');

        $this->provisionner('AE0042');

        $this->assertSame([], $this->evenements->dispatches);
        $this->assertNotSame([], $this->journal->avertissements);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->fabrique = new FakeTenantFactory;
        $this->declaration = new FakeTenantDeclaration([
            ['cle' => 'nom', 'source' => 'core_identity.display_name', 'requis' => true],
            ['cle' => 'email', 'source' => 'core_identity.email', 'requis' => true],
            ['cle' => 'telephone', 'source' => 'core_identity.phone'],
        ]);
        $this->journal = new FakeLogger;
        $this->evenements = new FakeEventDispatcher;

        Log::swap($this->journal);
        Event::swap($this->evenements);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstance('log');
        Facade::clearResolvedInstance('events');

        parent::tearDown();
    }

    /**
     * Le client, tel que le Core le rend.
     *
     * @return array<string, mixed>
     */
    private function clientEnCore(): array
    {
        return [
            'id' => 'cus_1',
            'project_id' => 'prj_1',
            'external_ref' => 'AE0042',
            'display_name' => 'Auto-École du Centre',
            'email' => 'contact@exemple.cm',
            'phone' => '+237 6 00 00 00 00',
            'locale' => 'fr',
            'metadata' => [],
            'status' => 'active',
            'version' => 1,
        ];
    }

    private function provisionner(string $reference): void
    {
        $job = new ProvisionsTenant($reference, 'evt_1');

        $job->handle(new Customers($this->client), $this->fabrique, $this->declaration);
    }
}
