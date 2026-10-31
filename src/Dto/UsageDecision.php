<?php

declare(strict_types=1);

namespace CoreAdapter\Dto;

use CoreAdapter\Support\Payload;

/**
 * La décision du Core sur une unité d'usage.
 *
 * - `admitted` — l'unité a été comptée ;
 * - `replayed` — la clé d'idempotence était déjà connue, rien n'a été compté
 *   de plus (c'est le bon signe : l'appel est rejouable) ;
 * - `quota_exceeded` — le plan ne permet pas l'unité ; **rien** n'a été compté,
 *   et l'appel peut être rejoué plus tard avec la même clé.
 */
final readonly class UsageDecision
{
    public function __construct(
        public string $status,
        public string $meter,
        public int $used,
        public ?int $limit,
        public ?int $remaining,
        public ?string $windowStart,
        public ?string $windowEnd,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            status: Payload::string($data, 'status') ?? '',
            meter: Payload::string($data, 'meter') ?? '',
            used: Payload::int($data, 'used') ?? 0,
            limit: Payload::int($data, 'limit'),
            remaining: Payload::int($data, 'remaining'),
            windowStart: Payload::string($data, 'window_start'),
            windowEnd: Payload::string($data, 'window_end'),
        );
    }

    /** L'unité a-t-elle été comptée (ou rejouée sans double comptage) ? */
    public function isCounted(): bool
    {
        return $this->status === 'admitted' || $this->status === 'replayed';
    }
}
