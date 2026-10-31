<?php

declare(strict_types=1);

namespace CoreAdapter\Services;

use CoreAdapter\CoreClient;
use CoreAdapter\Dto\Payment;

/**
 * L'encaissement — le Core route vers le prestataire, jamais vous.
 *
 * Deux flux : `paylink` (le Core renvoie une page hébergée, vous y envoyez le
 * payeur) et `payin` (le payeur reçoit une invite sur son téléphone, vous
 * menez l'interface). `amount` est **un entier dans la plus petite unité de
 * la devise** : `5000` XAF vaut 5 000 FCFA, `100` EUR vaut 1 €.
 *
 * Rien n'est certain avant l'événement : attendez `payment.status_changed`
 * avec `isFinal = true`, ou relisez le paiement.
 */
final class Payments
{
    public function __construct(private readonly CoreClient $client) {}

    /**
     * `POST /v1/payments/paylink` — un lien de paiement hébergé par le Core.
     *
     * @param array{channel: string, amount: int, currency: string, reason: string, customer_phone_number?: string|null, customer_name?: string|null, customer_email?: string|null, customer_lang?: string|null, return_url?: string|null, callback_url?: string|null, wallet_recipient_external_ref?: string|null} $payment
     * @param string $idempotencyKey l'identifiant de VOTRE commande (ou de
     *                               toute opération stable) : le rejouer ne fait jamais encaisser deux
     *                               fois
     */
    public function paylink(array $payment, string $idempotencyKey): Payment
    {
        $payload = $this->client->postIdempotent('payments/paylink', $payment, $idempotencyKey);

        return Payment::fromArray($payload);
    }

    /**
     * `POST /v1/payments/payin` — une charge directe, pilotée par votre
     * application.
     *
     * @param array{channel: string, amount: int, currency: string, reason: string, customer_phone_number?: string|null, customer_name?: string|null, customer_email?: string|null, customer_lang?: string|null, return_url?: string|null, callback_url?: string|null, wallet_recipient_external_ref?: string|null} $payment
     * @param string $idempotencyKey l'identifiant de VOTRE commande
     */
    public function payin(array $payment, string $idempotencyKey): Payment
    {
        $payload = $this->client->postIdempotent('payments/payin', $payment, $idempotencyKey);

        return Payment::fromArray($payload);
    }

    /**
     * `GET /v1/payments/{id}` — l'état du paiement, tel que le Core le
     * connaît (sans réconcilier en direct avec le prestataire).
     */
    public function get(string $paymentId): Payment
    {
        $payload = $this->client->get('payments/'.rawurlencode($paymentId));

        return Payment::fromArray($payload);
    }
}
