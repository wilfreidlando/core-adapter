<?php

declare(strict_types=1);

namespace SaasCommerce\Core\Services;

use SaasCommerce\Core\CoreClient;
use SaasCommerce\Core\Dto\PortalSession;

/**
 * L'ouverture du portail — le commerce se fait là, par le propriétaire.
 *
 * Un seul appel : le Core renvoie un lien **à usage unique**, valable dix
 * minutes. À vous de faire la redirection.
 *
 * Le paquet n'envoie **jamais** `customer` ni `plan_code` : ces deux champs
 * demandent des portées d'écriture (`customers:write`, `subscriptions:write`)
 * que la clé d'une application cliente n'a pas, et le Core répondrait `403`.
 * C'est voulu : le commerce ne se fait pas depuis votre application, il se
 * fait dans le portail, par le propriétaire.
 */
final class Portal
{
    public function __construct(private readonly CoreClient $client) {}

    /**
     * `POST /v1/portal/sessions`.
     *
     * @param string $customerRef la référence du locataire, celle que votre application connaît
     * @param string|null $flow `manage` (la page d'accueil du portail) ou `pay_invoice`
     * @param string|null $invoiceId la facture à ouvrir avec le flux `pay_invoice`
     * @param string|null $returnUrl où le bouton « Retour » renvoie le propriétaire ;
     *                               l'adresse doit être dans la liste blanche du projet, sinon le Core
     *                               répond `422`
     */
    public function open(
        string $customerRef,
        ?string $flow = null,
        ?string $invoiceId = null,
        ?string $returnUrl = null,
    ): PortalSession {
        $body = ['customer_ref' => $customerRef];

        if ($flow !== null) {
            $body['flow'] = $flow;
        }

        if ($invoiceId !== null) {
            $body['invoice_id'] = $invoiceId;
        }

        if ($returnUrl !== null) {
            $body['return_url'] = $returnUrl;
        }

        $payload = $this->client->post('portal/sessions', $body);

        return PortalSession::fromArray($payload);
    }
}
