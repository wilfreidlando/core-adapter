<?php

declare(strict_types=1);

namespace CoreAdapter\Webhooks;

use CoreAdapter\Contracts\EventStore;

/**
 * Un magasin d'événements en mémoire — pour les tests, et pour une
 * application qui n'a pas de cache (une commande CLI, un script).
 *
 * ⚠️ Il ne survit pas au redémarrage : le Core rejouera alors les événements
 * du tampon. En production, utilisez une implémentation persistante.
 */
final class InMemoryEventStore implements EventStore
{
    /** @var array<string, true> */
    private array $seen = [];

    public function has(string $eventId): bool
    {
        return isset($this->seen[$eventId]);
    }

    public function remember(string $eventId): void
    {
        $this->seen[$eventId] = true;
    }
}
