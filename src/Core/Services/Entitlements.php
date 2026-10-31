<?php

declare(strict_types=1);

namespace SaasCommerce\Core\Services;

use SaasCommerce\Core\Contracts\EntitlementsCache;
use SaasCommerce\Core\CoreClient;
use SaasCommerce\Core\Dto\Entitlements as EntitlementsView;

/**
 * Les droits du locataire — « ai-je le droit ? », et « qu'est-ce qu'il me
 * reste ? ».
 *
 * C'est la question que votre application pose à chaque page sensible. La
 * réponse peut être mise en cache pendant une minute (`entitlements_ttl`),
 * et le cache est vidé dès qu'un événement `subscription.*` ou `usage.*`
 * arrive : un abonnement suspendu doit s'appliquer vite.
 */
final class Entitlements
{
    public function __construct(
        private readonly CoreClient $client,
        private readonly ?EntitlementsCache $cache = null,
    ) {}

    /**
     * `GET /v1/customers/{ref}/entitlements` — les droits du locataire.
     */
    public function get(string $customerRef): EntitlementsView
    {
        if ($this->cache === null) {
            return $this->fetch($customerRef);
        }

        return $this->cache->remember($customerRef, fn (): EntitlementsView => $this->fetch($customerRef));
    }

    private function fetch(string $customerRef): EntitlementsView
    {
        $payload = $this->client->get('customers/'.rawurlencode($customerRef).'/entitlements');

        return EntitlementsView::fromArray($payload);
    }
}
