<?php

declare(strict_types=1);

namespace CoreAdapter\Tests\Fakes;

use CoreAdapter\Laravel\Tenant\TenantDeclaration;

/**
 * La déclaration d'une application pour les tests : une clé unique et une
 * liste de champs.
 */
final class FakeTenantDeclaration implements TenantDeclaration
{
    /**
     * @param array<int, array{cle: string, source: string, requis?: bool}> $champs
     */
    public function __construct(private readonly array $champs = []) {}

    /**
     * @return array{attribut: string, motif: string}
     */
    public function cleUnique(): array
    {
        return ['attribut' => 'code', 'motif' => '/^AE[0-9]{4}$/'];
    }

    /**
     * @return array<int, array{cle: string, source: string, requis?: bool}>
     */
    public function champs(): array
    {
        return $this->champs;
    }
}
