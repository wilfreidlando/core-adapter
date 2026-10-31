<?php

declare(strict_types=1);

namespace CoreAdapter\Dto;

use CoreAdapter\Support\Payload;

/**
 * L'état d'une notification.
 *
 * La réponse est `201` **même quand l'envoi a échoué** : regardez toujours
 * `status` (`sent` ou `failed`), et `errorMessage` quand il est `failed`.
 * L'état final arrive par l'événement `notification.delivered`,
 * `notification.read` ou `notification.failed`.
 */
final readonly class Notification
{
    public function __construct(
        public string $id,
        public string $channel,
        public string $status,
        public ?string $contentType,
        public ?string $errorMessage,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Payload::string($data, 'id') ?? '',
            channel: Payload::string($data, 'channel') ?? '',
            status: Payload::string($data, 'status') ?? '',
            contentType: Payload::string($data, 'content_type'),
            errorMessage: Payload::string($data, 'error_message'),
        );
    }

    /** L'envoi a-t-il échoué ? */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }
}
