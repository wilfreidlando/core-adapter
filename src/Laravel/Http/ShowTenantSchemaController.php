<?php

declare(strict_types=1);

namespace CoreAdapter\Laravel\Http;

use CoreAdapter\Laravel\Tenant\TenantDeclaration;
use CoreAdapter\Support\DeclarationRefused;
use CoreAdapter\Support\PatternRefused;
use CoreAdapter\Support\TenantSchemaPayload;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * La forme que l'application veut pour son locataire.
 *
 * Le Core vient chercher cette forme pour la proposer à l'opérateur, qui la
 * lit et la décide (ADR-0133). La réponse est **exactement** ce que le Core
 * attend sur `PUT /v1/projects/{id}/tenant-schema` : la même clé unique, le
 * même motif, les mêmes champs.
 *
 * - `200` la déclaration ;
 * - `422` l'application ne déclare aucune forme, ou sa déclaration n'a pas
 *   de traduction honnête (le motif, la source qui ne nomme pas la clé) ;
 * - `401` signature invalide — rendu par le garde, avant d'arriver ici.
 */
final class ShowTenantSchemaController
{
    public function __construct(private readonly Container $container) {}

    public function __invoke(Request $request): Response
    {
        $declaration = $this->declaration();

        if (! $declaration instanceof TenantDeclaration) {
            return new JsonResponse([
                'error' => 'tenant_schema_not_declared',
                'message' => 'Cette application ne déclare aucune forme de locataire.',
            ], 422);
        }

        try {
            $charge = TenantSchemaPayload::build(
                $declaration->cleUnique(),
                array_values($declaration->champs()),
            );
        } catch (PatternRefused|DeclarationRefused $e) {
            // La déclaration est **localement** invalide : on le dit, on
            // n'envoie rien au Core, et on ne l'enregistre nulle part.
            return new JsonResponse([
                'error' => 'invalid_tenant_schema',
                'message' => $e->getMessage(),
            ], 422);
        }

        return new JsonResponse($charge);
    }

    /**
     * La déclaration de l'application, si elle en a une.
     */
    private function declaration(): ?TenantDeclaration
    {
        if (! $this->container->bound(TenantDeclaration::class)) {
            return null;
        }

        try {
            /** @var mixed $declaration */
            $declaration = $this->container->make(TenantDeclaration::class);
        } catch (Throwable) {
            return null;
        }

        return $declaration instanceof TenantDeclaration ? $declaration : null;
    }
}
