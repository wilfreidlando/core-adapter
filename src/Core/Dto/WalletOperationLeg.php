<?php

declare(strict_types=1);

namespace SaasCommerce\Core\Dto;

use SaasCommerce\Core\Support\Payload;

/**
 * Une jambe d'un transfert : qui paie, qui reçoit, combien.
 */
final readonly class WalletOperationLeg
{
    public function __construct(
        public string $from,
        public string $to,
        public int $amountMinor,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            from: Payload::string($data, 'from') ?? '',
            to: Payload::string($data, 'to') ?? '',
            amountMinor: Payload::int($data, 'amount_minor') ?? 0,
        );
    }
}
