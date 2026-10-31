<?php

declare(strict_types=1);

namespace SaasCommerce\Laravel\Events;

/**
 * Un événement du Core, vérifié et dédoublonné.
 *
 * C'est ici que VOTRE métier réagit :
 *
 * ```php
 * Event::listen(CoreEventReceived::class, function (CoreEventReceived $e): void {
 *     if ($e->type === 'customer.created') {
 *         // le locataire existe : créer (ou lier) son compte local
 *     }
 * });
 * ```
 *
 * La charge utile ne porte **aucune donnée personnelle** : le locataire y est
 * nommé par votre propre référence. Si vous avez besoin de son e-mail, vous le
 * **demandez** (`Customers::get()`).
 */
final readonly class CoreEventReceived
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public string $id,
        public string $type,
        public array $data,
    ) {}

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
