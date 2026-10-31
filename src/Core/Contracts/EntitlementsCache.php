<?php

declare(strict_types=1);

namespace SaasCommerce\Core\Contracts;

use SaasCommerce\Core\Dto\Entitlements;

/**
 * Le cache des droits du locataire.
 *
 * Le guide du Core le dit : « mettez la réponse en cache pendant une minute,
 * et rafraîchissez-la quand un événement `subscription.*` arrive ». Ce contrat
 * est là pour que le noyau n'ait pas à savoir **qui** cache : le cache
 * Laravel, un cache Redis, ou rien du tout.
 */
interface EntitlementsCache
{
    /**
     * @param callable(): Entitlements $resolver la lecture, appelée seulement si le cache est froid
     */
    public function remember(string $customerRef, callable $resolver): Entitlements;

    /** Oublie les droits d'un locataire — quand son état a changé. */
    public function forget(string $customerRef): void;
}
