<?php

declare(strict_types=1);

namespace CoreAdapter\Services;

use CoreAdapter\CoreClient;
use CoreAdapter\Dto\Customer;

/**
 * L'identité du locataire — **en lecture seule**.
 *
 * Le Core est le maître de l'identité du locataire : c'est lui qui le crée
 * (l'opérateur, depuis la console du Core) et qui le pousse vers votre
 * application (`customer.created`). Votre application ne l'écrit **jamais** :
 * elle le **reçoit**, puis elle le **lit** quand elle a besoin de son e-mail
 * pour le lier à son compte local — la charge utile d'un événement ne porte
 * aucune donnée personnelle.
 */
final class Customers
{
    public function __construct(private readonly CoreClient $client) {}

    /**
     * `GET /v1/customers/{ref}` — le locataire, par la référence que VOTRE
     * application connaît.
     *
     * L'e-mail et le téléphone ne sont rendus qu'à une clé portant
     * `customers:read` — celle d'une application cliente.
     */
    public function get(string $customerRef): Customer
    {
        $payload = $this->client->get('customers/'.rawurlencode($customerRef));

        return Customer::fromArray($payload);
    }
}
