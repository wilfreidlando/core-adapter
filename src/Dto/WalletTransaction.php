<?php

declare(strict_types=1);

namespace CoreAdapter\Dto;

use CoreAdapter\Support\Payload;

/**
 * Un mouvement de portefeuille.
 *
 * `type` vaut `payment` (un paiement confirmé l'a crédité), `transfer`,
 * `withdrawal` ou `debit`. `reference` est votre clé d'idempotence, celle que
 * vous avez donnée au Core.
 */
final readonly class WalletTransaction
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $id,
        public ?string $occurredAt,
        public string $direction,
        public int $amountMinor,
        public string $currency,
        public string $type,
        public ?string $operationId,
        public ?string $paymentId,
        public ?string $reference,
        public ?string $description,
        public array $metadata,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Payload::string($data, 'id') ?? '',
            occurredAt: Payload::string($data, 'occurred_at'),
            direction: Payload::string($data, 'direction') ?? '',
            amountMinor: Payload::int($data, 'amount_minor') ?? 0,
            currency: Payload::string($data, 'currency') ?? '',
            type: Payload::string($data, 'type') ?? '',
            operationId: Payload::string($data, 'operation_id'),
            paymentId: Payload::string($data, 'payment_id'),
            reference: Payload::string($data, 'reference'),
            description: Payload::string($data, 'description'),
            metadata: Payload::map($data, 'metadata'),
        );
    }
}
