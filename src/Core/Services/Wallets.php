<?php

declare(strict_types=1);

namespace SaasCommerce\Core\Services;

use SaasCommerce\Core\CoreClient;
use SaasCommerce\Core\CoreException;
use SaasCommerce\Core\Dto\Wallet;
use SaasCommerce\Core\Dto\WalletOperation;
use SaasCommerce\Core\Dto\WalletTransaction;
use SaasCommerce\Core\Support\Payload;

/**
 * Les portefeuilles du Core, par « partie » — une partie est qui que vous
 * nommez (un utilisateur, un partenaire, un compte de séquestre) par VOTRE
 * référence.
 *
 * - L'argent n'entre dans un portefeuille que par un paiement **confirmé** qui
 *   le nomme (`wallet_recipient_external_ref`) : il n'y a pas de crédit libre.
 * - `transfer()` déplace de la valeur entre portefeuilles, **tout ou rien**
 *   (une à vingt jambes, une seule devise).
 * - `withdraw()` **enregistre** un versement que vous avez fait vous-même
 *   (mobile money, banque) : il débite le portefeuille et garde votre preuve ;
 *   le Core n'envoie pas l'argent.
 *
 * Les montants sont des entiers dans la plus petite unité de la devise.
 */
final class Wallets
{
    public function __construct(private readonly CoreClient $client) {}

    /**
     * `GET /v1/parties/{ref}/wallets` — les portefeuilles d'une partie, un par
     * devise, avec leur solde. Une partie qui n'a jamais rien reçu n'en a
     * aucun : la liste est vide, et lire ne crée rien.
     *
     * @return list<Wallet>
     */
    public function ofParty(string $partyRef): array
    {
        $payload = $this->client->get('parties/'.rawurlencode($partyRef).'/wallets');

        $wallets = [];

        foreach (Payload::list($payload, 'data') as $item) {
            if (is_array($item)) {
                /** @var array<string, mixed> $item */
                $wallets[] = Wallet::fromArray($item);
            }
        }

        return $wallets;
    }

    /**
     * `GET /v1/wallets/{id}/balance` — le solde d'un portefeuille.
     */
    public function balance(string $walletId): Wallet
    {
        $payload = $this->client->get('wallets/'.rawurlencode($walletId).'/balance');

        return Wallet::fromArray($payload);
    }

    /**
     * `GET /v1/wallets/{id}/transactions` — les mouvements d'un portefeuille,
     * du plus récent au plus ancien.
     *
     * @return list<WalletTransaction>
     */
    public function transactions(string $walletId, int $page = 1, int $perPage = 25): array
    {
        $payload = $this->client->get('wallets/'.rawurlencode($walletId).'/transactions', [
            'page' => max(1, $page),
            'per_page' => min(100, max(1, $perPage)),
        ]);

        $transactions = [];

        foreach (Payload::list($payload, 'data') as $item) {
            if (is_array($item)) {
                /** @var array<string, mixed> $item */
                $transactions[] = WalletTransaction::fromArray($item);
            }
        }

        return $transactions;
    }

    /**
     * `POST /v1/wallets/transfers` — déplacer de la valeur entre parties.
     *
     * @param list<array{from: string, to: string, amount: int}> $legs une à
     *                                                                 vingt jambes, une seule devise ; toutes passent, ou aucune
     * @param string $idempotencyKey VOTRE identifiant du transfert : rejoué,
     *                               il ne déplace la valeur qu'une fois
     * @param array<string, mixed> $metadata opaque pour le Core, 4 Ko au plus
     *
     * @throws CoreException `insufficient-funds` (409) si une source n'a pas
     *                       assez — alors rien n'a bougé
     */
    public function transfer(
        string $idempotencyKey,
        string $currency,
        array $legs,
        ?string $description = null,
        array $metadata = [],
    ): WalletOperation {
        $body = [
            'currency' => $currency,
            'legs' => $legs,
        ];

        if ($description !== null) {
            $body['description'] = $description;
        }

        if ($metadata !== []) {
            $body['metadata'] = $metadata;
        }

        $payload = $this->client->postIdempotent('wallets/transfers', $body, $idempotencyKey);

        return WalletOperation::fromArray($payload);
    }

    /**
     * `POST /v1/wallets/withdrawals` — enregistrer un versement que vous avez
     * fait hors du Core.
     *
     * @param string $idempotencyKey VOTRE identifiant du retrait
     * @param string $partyRef la partie débitée
     * @param int $amount le montant sorti, en plus petite unité de la devise
     * @param string|null $externalReference votre preuve (l'identifiant de la
     *                                       transaction mobile money, la référence d'un virement)
     */
    public function withdraw(
        string $idempotencyKey,
        string $partyRef,
        int $amount,
        string $currency,
        ?string $description = null,
        ?string $externalReference = null,
    ): WalletOperation {
        $body = [
            'party' => $partyRef,
            'amount' => $amount,
            'currency' => $currency,
        ];

        if ($description !== null) {
            $body['description'] = $description;
        }

        if ($externalReference !== null) {
            $body['external_reference'] = $externalReference;
        }

        $payload = $this->client->postIdempotent('wallets/withdrawals', $body, $idempotencyKey);

        return WalletOperation::fromArray($payload);
    }
}
