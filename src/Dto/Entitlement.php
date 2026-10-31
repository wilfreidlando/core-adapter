<?php

declare(strict_types=1);

namespace CoreAdapter\Dto;

use CoreAdapter\Support\Payload;

/**
 * Un droit du locataire : une fonctionnalité, ou un compteur de quota.
 *
 * - `type` vaut `boolean` (la fonctionnalité est ouverte ou non) ou `limit`
 *   (le compteur, avec ce qu'il reste) ;
 * - `overage` dit ce qui arrive au-delà du seuil : `block`, `allow`…
 */
final readonly class Entitlement
{
    public function __construct(
        public string $key,
        public string $type,
        public bool $enabled,
        public ?string $meter,
        public ?int $limit,
        public ?int $used,
        public ?int $remaining,
        public ?string $overage,
        public ?int $thresholdBps,
        public ?string $windowStart,
        public ?string $windowEnd,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            key: Payload::string($data, 'key') ?? '',
            type: Payload::string($data, 'type') ?? 'boolean',
            enabled: Payload::bool($data, 'enabled'),
            meter: Payload::string($data, 'meter'),
            limit: Payload::int($data, 'limit'),
            used: Payload::int($data, 'used'),
            remaining: Payload::int($data, 'remaining'),
            overage: Payload::string($data, 'overage'),
            thresholdBps: Payload::int($data, 'threshold_bps'),
            windowStart: Payload::string($data, 'window_start'),
            windowEnd: Payload::string($data, 'window_end'),
        );
    }
}
