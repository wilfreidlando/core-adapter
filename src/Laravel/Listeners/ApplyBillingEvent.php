<?php

declare(strict_types=1);

namespace CoreAdapter\Laravel\Listeners;

use CoreAdapter\Contracts\EntitlementsCache;
use CoreAdapter\Laravel\Events\CoreEventReceived;

/**
 * L'effet des événements du Core sur l'état **local** de votre application.
 *
 * Le paquet ne fait pas de commerce : il ne crée ni abonnement, ni facture. Il
 * fait une chose — **rafraîchir** ce que votre application a en cache quand
 * l'état du locataire bouge.
 *
 * - un abonnement activé, renouvelé ou résilié ;
 * - une facture payée, ou en échec ;
 * - un quota bientôt atteint, ou atteint ;
 * - un locataire suspendu, rétabli, ou effacé.
 *
 * Dans tous ces cas, les droits mis en cache sont périmés : on les oublie. La
 * prochaine lecture ira voir le Core.
 *
 * Pour votre propre état (l'accès au locataire, un bandeau, une suspension),
 * écoutez `CoreEventReceived` directement.
 */
final class ApplyBillingEvent
{
    public function __construct(private readonly EntitlementsCache $cache) {}

    public function __invoke(CoreEventReceived $event): void
    {
        if (! $event->is('subscription') && ! $event->is('invoice') && ! $event->is('usage') && ! $event->is('customer')) {
            return;
        }

        $ref = $event->customerRef();

        if ($ref === null) {
            return;
        }

        $this->cache->forget($ref);
    }
}
