<?php

declare(strict_types=1);

namespace SaasCommerce\Core\Dto;

use SaasCommerce\Core\Support\Payload;

/**
 * La session de portail : le lien que le propriétaire va suivre.
 *
 * Le lien est **à usage unique** et **valable dix minutes**. À vous de faire
 * la redirection — et de ne jamais le stocker.
 */
final readonly class PortalSession
{
    public function __construct(
        public string $url,
        public ?string $expiresAt,
        public bool $customerCreated,
        public bool $subscriptionCreated,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            url: Payload::string($data, 'url') ?? '',
            expiresAt: Payload::string($data, 'expires_at'),
            customerCreated: Payload::bool($data, 'customer_created'),
            subscriptionCreated: Payload::bool($data, 'subscription_created'),
        );
    }
}
