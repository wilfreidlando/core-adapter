<?php

declare(strict_types=1);

namespace SaasCommerce\Core\Dto;

use SaasCommerce\Core\Support\Payload;

/**
 * Le résultat d'un transfert ou d'un retrait.
 *
 * `reference` est votre clé d'idempotence : la rejouer ne déplace rien une
 * seconde fois.
 */
final readonly class WalletOperation
{
    /**
     * @param array<string, mixed> $metadata
     * @param list<WalletOperationLeg> $legs
     * @param list<Wallet> $wallets
     */
    public function __construct(
        public string $id,
        public string $type,
        public ?string $reference,
        public string $currency,
        public int $totalMinor,
        public ?string $description,
        public array $metadata,
        public array $legs,
        public ?string $externalReference,
        public ?string $createdAt,
        public array $wallets,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $legs = [];

        foreach (Payload::list($data, 'legs') as $item) {
            if (is_array($item)) {
                /** @var array<string, mixed> $item */
                $legs[] = WalletOperationLeg::fromArray($item);
            }
        }

        $wallets = [];

        foreach (Payload::list($data, 'wallets') as $item) {
            if (is_array($item)) {
                /** @var array<string, mixed> $item */
                $wallets[] = Wallet::fromArray($item);
            }
        }

        return new self(
            id: Payload::string($data, 'id') ?? '',
            type: Payload::string($data, 'type') ?? '',
            reference: Payload::string($data, 'reference'),
            currency: Payload::string($data, 'currency') ?? '',
            totalMinor: Payload::int($data, 'total_minor') ?? 0,
            description: Payload::string($data, 'description'),
            metadata: Payload::map($data, 'metadata'),
            legs: $legs,
            externalReference: Payload::string($data, 'external_reference'),
            createdAt: Payload::string($data, 'created_at'),
            wallets: $wallets,
        );
    }
}
