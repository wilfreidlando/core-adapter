<?php

declare(strict_types=1);

namespace CoreAdapter\Tests\Fakes;

use CoreAdapter\Laravel\Tenant\TenantFactory;
use DomainException;

/**
 * Une fabrique de locataire pour les tests : elle note ce qu'on lui demande,
 * et rend ce qu'on lui a préparé.
 */
final class FakeTenantFactory implements TenantFactory
{
    /** @var list<string> */
    public array $lies = [];

    /** @var list<array{cle: string, attributs: array<string, mixed>}> */
    public array $creations = [];

    /** Le locataire déjà connu, s'il y en a un. */
    public ?object $existant = null;

    /** L'échec à produire, s'il y en a un. */
    public ?DomainException $echec = null;

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
        if ($this->echec instanceof DomainException) {
            throw $this->echec;
        }

        $this->creations[] = ['cle' => $cle, 'attributs' => $attributs];

        return (object) ['code' => $cle];
    }
}
