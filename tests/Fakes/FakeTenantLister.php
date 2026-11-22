<?php

declare(strict_types=1);

namespace CoreAdapter\Tests\Fakes;

use CoreAdapter\Laravel\Tenant\TenantLister;

/**
 * Une liste de locataires pour les tests.
 */
final class FakeTenantLister implements TenantLister
{
    /**
     * @param list<array<string, mixed>> $tenants
     */
    public function __construct(private readonly array $tenants = []) {}

    /**
     * @return iterable<array<string, mixed>>
     */
    public function tenants(): iterable
    {
        yield from $this->tenants;
    }
}
