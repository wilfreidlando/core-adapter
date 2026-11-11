<?php

declare(strict_types=1);

namespace CoreAdapter\Laravel\Tenant;

/**
 * Le locataire a été créé.
 *
 * L'événement que le paquet émet une fois le travail fait : votre
 * application peut y enchaîner — écrire une ligne de trace, prévenir
 * quelqu'un, ouvrir une session.
 *
 * ```php
 * Event::listen(TenantProvisioned::class, function (TenantProvisioned $e): void {
 *     Log::info('Locataire créé', ['reference' => $e->reference]);
 * });
 * ```
 */
final readonly class TenantProvisioned
{
    public function __construct(
        /** La référence que le Core connaît. */
        public string $reference,
        /** Le locataire local, tel que votre fabrique l'a rendu. */
        public object $locataire,
        /** L'identifiant du client chez le Core. */
        public string $customerId,
    ) {}
}
