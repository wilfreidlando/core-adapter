<?php

declare(strict_types=1);

namespace CoreAdapter\Laravel\Http;

use CoreAdapter\Laravel\Tenant\TenantDeclaration;
use CoreAdapter\Laravel\Tenant\TenantLister;
use CoreAdapter\Laravel\Tenant\TenantRows;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Les locataires que l'application a **déjà**.
 *
 * Le Core vient chercher cette liste pour reconnaître les clients d'une
 * application qui existait avant lui : il les crée chez lui, il les pousse,
 * et l'application, qui les connaît déjà, les **relie** (ADR-0133).
 *
 * La réponse est paginée, et la pagination est dans le **corps** de la
 * requête : c'est le corps qui est signé, donc la page demandée est
 * elle aussi signée. Un `GET` porterait sa page dans la chaîne de requête,
 * que la signature ne couvre pas.
 *
 * ```json
 * {"page": 1, "per_page": 100}
 * ```
 *
 * ```json
 * {"data": [{"external_ref": "AE0042", "display_name": "…"}], "meta": {"page": 1, "per_page": 100, "count": 1, "has_more": false}}
 * ```
 *
 * - `200` la page demandée ;
 * - `422` l'application ne sait pas lister ses locataires ;
 * - `401` signature invalide — rendu par le garde, avant d'arriver ici.
 */
final class ListTenantsController
{
    /** La page demandée, au plus. */
    private const MAX_PER_PAGE = 500;

    public function __construct(private readonly Container $container) {}

    public function __invoke(Request $request): Response
    {
        $lister = $this->lister();

        if (! $lister instanceof TenantLister) {
            return new JsonResponse([
                'error' => 'tenant_listing_not_supported',
                'message' => 'Cette application ne sait pas lister ses locataires.',
            ], 422);
        }

        [$page, $perPage] = $this->pagination($request);

        $champs = $this->champs();
        $lignes = [];
        $hasMore = false;
        $restants = ($page - 1) * $perPage;

        foreach ($lister->tenants() as $tenant) {
            if ($restants > 0) {
                // Les lignes des pages précédentes : on les saute sans les
                // construire.
                $restants--;

                continue;
            }

            if (count($lignes) >= $perPage) {
                // Une ligne de plus : il y en a au-delà de cette page.
                $hasMore = true;

                break;
            }

            $lignes[] = TenantRows::build(self::ligne($tenant), $champs);
        }

        return new JsonResponse([
            'data' => $lignes,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'count' => count($lignes),
                'has_more' => $hasMore,
            ],
        ]);
    }

    /**
     * La page demandée et la taille de la page.
     *
     * @return array{0: int, 1: int}
     */
    private function pagination(Request $request): array
    {
        $corps = json_decode($request->getContent(), true);

        if (! is_array($corps)) {
            $corps = [];
        }

        $page = $corps['page'] ?? 1;
        $perPage = $corps['per_page'] ?? 100;

        return [
            is_numeric($page) ? max(1, (int) $page) : 1,
            is_numeric($perPage) ? min(self::MAX_PER_PAGE, max(1, (int) $perPage)) : 100,
        ];
    }

    /**
     * Les clés déclarées par l'application, si elle en déclare.
     *
     * @return list<array{cle: string, source: string, requis?: bool, libelle?: string, type?: string, longueur?: int}>
     */
    private function champs(): array
    {
        if (! $this->container->bound(TenantDeclaration::class)) {
            return [];
        }

        try {
            /** @var mixed $declaration */
            $declaration = $this->container->make(TenantDeclaration::class);
        } catch (Throwable) {
            return [];
        }

        if (! $declaration instanceof TenantDeclaration) {
            return [];
        }

        return array_values($declaration->champs());
    }

    /**
     * Le locataire rendu par l'application.
     *
     * @return array<string, mixed>
     */
    private static function ligne(mixed $tenant): array
    {
        if (! is_array($tenant)) {
            return [];
        }

        /** @var array<string, mixed> $tenant */
        return $tenant;
    }

    /**
     * La liste des locataires, si l'application sait la faire.
     */
    private function lister(): ?TenantLister
    {
        if (! $this->container->bound(TenantLister::class)) {
            return null;
        }

        try {
            /** @var mixed $lister */
            $lister = $this->container->make(TenantLister::class);
        } catch (Throwable) {
            return null;
        }

        return $lister instanceof TenantLister ? $lister : null;
    }
}
