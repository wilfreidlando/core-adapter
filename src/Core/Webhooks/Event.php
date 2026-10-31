<?php

declare(strict_types=1);

namespace SaasCommerce\Core\Webhooks;

use SaasCommerce\Core\Support\Payload;

/**
 * L'enveloppe d'un événement du Core : `{id, type, data}`.
 *
 * `id` est la **clé de dédoublonnage** : le Core livre au moins une fois.
 * `data` ne porte **aucune donnée personnelle** (ADR-0070 §3) — le locataire
 * y est nommé par votre propre référence. Si vous avez besoin de son e-mail,
 * vous le **demandez** (`GET /v1/customers/{ref}`).
 */
final readonly class Event
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public string $id,
        public string $type,
        public array $data,
    ) {}

    /**
     * @param array<string, mixed> $data
     * @return self|null null si la charge utile n'est pas une enveloppe
     */
    public static function fromArray(array $data): ?self
    {
        $id = $data['id'] ?? null;
        $type = $data['type'] ?? null;

        if (! is_string($id) || $id === '' || ! is_string($type) || $type === '') {
            return null;
        }

        return new self($id, $type, Payload::map($data, 'data'));
    }

    /** `is('customer')` vrai pour `customer.created` comme pour `customer.erased`. */
    public function is(string $prefix): bool
    {
        return $this->type === $prefix || str_starts_with($this->type, $prefix.'.');
    }

    /** La référence du locataire, si l'événement en porte une. */
    public function customerRef(): ?string
    {
        $ref = $this->data['customer_ref'] ?? $this->data['external_ref'] ?? null;

        return is_string($ref) && $ref !== '' ? $ref : null;
    }
}
