<?php

declare(strict_types=1);

namespace CoreAdapter\Laravel\Tenant;

use CoreAdapter\CoreException;
use CoreAdapter\Services\TenantSchemas;
use CoreAdapter\Support\DeclarationRefused;
use CoreAdapter\Support\PatternRefused;
use CoreAdapter\Support\TenantSchemaPayload;

/**
 * Propose au Core la forme du locataire.
 *
 * C'est ici que la déclaration passe de **votre** vocabulaire à celui du
 * Core : le motif de la clé unique y est normalisé, et les sources
 * `declared.<x>` y sont vérifiées. Ce qui n'a pas de traduction honnête est
 * refusé ici, avec un message qui dit quoi faire.
 *
 * @see TenantDeclaration
 * @see TenantSchemaPayload
 */
final class TenantSchemaDeclarer
{
    public function __construct(private readonly TenantSchemas $schemas) {}

    /**
     * La déclaration, telle qu'elle est **envoyée** au Core.
     *
     * @param string $projectId le projet que la clé représente
     * @param TenantDeclaration $declaration la déclaration, dans le vocabulaire de l'application
     * @return array<string, mixed> la déclaration, telle que le Core la rend
     *
     * @throws PatternRefused le motif n'a pas de traduction honnête
     * @throws DeclarationRefused une source `declared.<x>` ne nomme pas la clé du champ
     * @throws CoreException le Core a refusé la déclaration
     */
    public function declare(string $projectId, TenantDeclaration $declaration): array
    {
        $charge = TenantSchemaPayload::build(
            $declaration->cleUnique(),
            array_values($declaration->champs()),
        );

        return $this->schemas->propose($projectId, $charge);
    }
}
