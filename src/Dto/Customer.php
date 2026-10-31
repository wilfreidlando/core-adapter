<?php

declare(strict_types=1);

namespace CoreAdapter\Dto;

use CoreAdapter\Support\Payload;

/**
 * Le locataire du Core, tel que votre application le connaît.
 *
 * Le Core est le maître de son identité : c'est lui qui le crée (l'opérateur,
 * depuis la console) et qui le pousse (`customer.created`). Votre application
 * le **reçoit**, puis elle le **lit** quand elle a besoin de son e-mail pour
 * le lier à son compte local — la charge utile d'un événement ne porte aucune
 * donnée personnelle.
 */
final readonly class Customer
{
    /**
     * @param array<string, mixed> $metadata des identifiants techniques (jamais des données personnelles)
     */
    public function __construct(
        public string $id,
        public string $projectId,
        public string $externalRef,
        public string $displayName,
        public ?string $email,
        public ?string $phone,
        public string $locale,
        public array $metadata,
        public string $status,
        public int $version,
        public ?string $createdAt,
        public ?string $updatedAt,
        public ?string $erasedAt,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Payload::string($data, 'id') ?? '',
            projectId: Payload::string($data, 'project_id') ?? '',
            externalRef: Payload::string($data, 'external_ref') ?? '',
            displayName: Payload::string($data, 'display_name') ?? '',
            email: Payload::string($data, 'email'),
            phone: Payload::string($data, 'phone'),
            locale: Payload::string($data, 'locale') ?? 'fr',
            metadata: Payload::map($data, 'metadata'),
            status: Payload::string($data, 'status') ?? 'active',
            version: Payload::int($data, 'version') ?? 1,
            createdAt: Payload::string($data, 'created_at'),
            updatedAt: Payload::string($data, 'updated_at'),
            erasedAt: Payload::string($data, 'erased_at'),
        );
    }

    /** Le locataire a-t-il été effacé définitivement ? */
    public function isErased(): bool
    {
        return $this->erasedAt !== null || $this->status === 'erased';
    }
}
