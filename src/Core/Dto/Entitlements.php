<?php

declare(strict_types=1);

namespace SaasCommerce\Core\Dto;

use SaasCommerce\Core\Support\Payload;

/**
 * Les droits du locataire — « ai-je le droit ? », et « qu'est-ce qu'il me
 * reste ? ».
 *
 * `active` vaut `false` quand aucun contrat ne donne accès : l'application
 * doit alors **refuser** l'accès (une page d'explication, pas une erreur 500).
 */
final readonly class Entitlements
{
    /**
     * @param list<Entitlement> $entitlements
     */
    public function __construct(
        public string $customerRef,
        public bool $active,
        public ?string $planCode,
        public ?string $subscriptionId,
        public array $entitlements,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $entitlements = [];

        foreach (Payload::list($data, 'entitlements') as $item) {
            if (is_array($item)) {
                /** @var array<string, mixed> $item */
                $entitlements[] = Entitlement::fromArray($item);
            }
        }

        return new self(
            customerRef: Payload::string($data, 'customer_ref') ?? '',
            active: Payload::bool($data, 'active'),
            planCode: Payload::string($data, 'plan_code'),
            subscriptionId: Payload::string($data, 'subscription_id'),
            entitlements: $entitlements,
        );
    }

    /** Le droit `key` existe-t-il et est-il ouvert ? */
    public function allows(string $key): bool
    {
        $entitlement = $this->entitlement($key);

        if ($entitlement === null) {
            return false;
        }

        return $entitlement->type === 'boolean' ? $entitlement->enabled : true;
    }

    /** Combien reste-t-il sur le compteur `key` ? `null` si le droit n'existe pas. */
    public function remaining(string $key): ?int
    {
        return $this->entitlement($key)?->remaining;
    }

    /** Le droit `key`, ou `null`. */
    public function entitlement(string $key): ?Entitlement
    {
        foreach ($this->entitlements as $entitlement) {
            if ($entitlement->key === $key) {
                return $entitlement;
            }
        }

        return null;
    }
}
