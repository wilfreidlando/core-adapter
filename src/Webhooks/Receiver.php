<?php

declare(strict_types=1);

namespace CoreAdapter\Webhooks;

use CoreAdapter\Contracts\EventStore;

/**
 * La réception d'un événement : vérifier, lire, dédoublonner.
 *
 * Cette classe ne connaît ni HTTP, ni Laravel, ni l'horloge : elle reçoit le
 * corps brut et les en-têtes, et rend une **décision**. L'adaptateur Laravel
 * s'occupe du reste.
 */
final class Receiver
{
    public function __construct(
        private readonly Signature $signature,
        private readonly EventStore $events,
        private readonly string $secret,
    ) {}

    /**
     * Vérifie la signature et lit l'enveloppe.
     *
     * @param array<string, string> $headers les en-têtes de la requête
     *
     * @throws InvalidSignatureException la signature est absente, fausse ou trop vieille
     */
    public function accept(string $rawBody, array $headers): ?Event
    {
        $normalised = [];

        foreach ($headers as $name => $value) {
            $normalised[strtolower($name)] = $value;
        }

        $timestamp = $normalised['x-timestamp'] ?? '';
        $signatures = array_values(array_filter([
            $normalised['x-signature'] ?? '',
            $normalised['x-signature-previous'] ?? '',
        ], static fn (string $value): bool => $value !== ''));

        if (! $this->signature->verify($rawBody, $timestamp, $signatures, $this->secret)) {
            throw InvalidSignatureException::because('The signature of the event does not match.');
        }

        $decoded = json_decode($rawBody, true);

        if (! is_array($decoded)) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        return Event::fromArray($decoded);
    }

    /** L'événement a-t-il déjà été traité ? */
    public function isDuplicate(Event $event): bool
    {
        return $this->events->has($event->id);
    }

    /**
     * Marque l'événement comme traité — **après** que vos écouteurs ont
     * tourné, jamais avant : une erreur de traitement doit être rejouée par
     * le Core, pas avalée.
     */
    public function markProcessed(Event $event): void
    {
        $this->events->remember($event->id);
    }
}
