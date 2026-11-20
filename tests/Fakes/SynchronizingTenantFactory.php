<?php

declare(strict_types=1);

namespace CoreAdapter\Tests\Fakes;

use CoreAdapter\Dto\Customer;
use CoreAdapter\Laravel\Tenant\SynchronizesTenant;
use CoreAdapter\Laravel\Tenant\TenantFactory;

/**
 * Une fabrique qui sait **se synchroniser** : elle note ce que le paquet
 * lui demande, et rend ce qu'on lui a préparé.
 */
final class SynchronizingTenantFactory implements SynchronizesTenant, TenantFactory
{
    /** @var list<string> */
    public array $lies = [];

    /** @var list<array{cle: string, attributs: array<string, mixed>}> */
    public array $creations = [];

    /** @var list<array{reference: string, client: Customer}> */
    public array $synchronisations = [];

    /** Le locataire déjà connu, s'il y en a un. */
    public ?object $existant = null;

    public function trouver(string $reference): ?object
    {
        return $this->existant;
    }

    public function lier(object $locataire, string $reference): void
    {
        $this->lies[] = $reference;
    }

    /**
     * @param array<string, mixed> $attributs
     */
    public function creer(string $cle, array $attributs): object
    {
        $this->creations[] = ['cle' => $cle, 'attributs' => $attributs];

        return (object) ['code' => $cle];
    }

    public function synchroniser(object $locataire, string $reference, Customer $client): void
    {
        $this->synchronisations[] = ['reference' => $reference, 'client' => $client];
    }
}
