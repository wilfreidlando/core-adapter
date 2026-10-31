<?php

declare(strict_types=1);

namespace SaasCommerce\Core\Dto;

use SaasCommerce\Core\Support\Payload;

/**
 * Un portefeuille de partie, et son solde.
 *
 * L'argent n'entre dans un portefeuille que par un paiement **confirmé** qui
 * le nomme : il n'y a pas de crédit libre. Les montants sont des entiers dans
 * la plus petite unité de la devise.
 */
final readonly class Wallet
{
    public function __construct(
        public string $walletId,
        public ?string $party,
        public string $currency,
        public int $availableMinor,
        public int $heldMinor,
        public ?string $status,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            walletId: Payload::string($data, 'wallet_id') ?? '',
            party: Payload::string($data, 'party'),
            currency: Payload::string($data, 'currency') ?? '',
            availableMinor: Payload::int($data, 'available_minor') ?? 0,
            heldMinor: Payload::int($data, 'held_minor') ?? 0,
            status: Payload::string($data, 'status'),
        );
    }
}
