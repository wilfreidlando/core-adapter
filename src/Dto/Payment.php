<?php

declare(strict_types=1);

namespace CoreAdapter\Dto;

use CoreAdapter\Support\Payload;

/**
 * Un paiement, et son état.
 *
 * Rien n'est certain avant l'événement : attendez `payment.status_changed`
 * avec `isFinal = true`, ou relisez le paiement. `amount` est **un entier
 * dans la plus petite unité de la devise** (`5000` XAF vaut 5 000 FCFA).
 */
final readonly class Payment
{
    public function __construct(
        public string $id,
        public string $status,
        public bool $isFinal,
        public int $amount,
        public string $currency,
        public string $channel,
        public ?string $createdAt,
        public ?string $updatedAt,
        public ?string $paymentUrl,
        public ?string $actionHint,
        public ?string $failureReason,
        public ?string $callbackSecret,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Payload::string($data, 'id') ?? '',
            status: Payload::string($data, 'status') ?? '',
            isFinal: Payload::bool($data, 'is_final'),
            amount: Payload::int($data, 'amount') ?? 0,
            currency: Payload::string($data, 'currency') ?? '',
            channel: Payload::string($data, 'channel') ?? '',
            createdAt: Payload::string($data, 'created_at'),
            updatedAt: Payload::string($data, 'updated_at'),
            paymentUrl: Payload::string($data, 'payment_url'),
            actionHint: Payload::string($data, 'action_hint'),
            failureReason: Payload::string($data, 'failure_reason'),
            callbackSecret: Payload::string($data, 'callback_secret'),
        );
    }
}
