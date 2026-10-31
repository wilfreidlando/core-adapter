<?php

declare(strict_types=1);

namespace CoreAdapter\Laravel\Cache;

use CoreAdapter\Contracts\EventStore;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Le magasin d'événements sur le cache de Laravel.
 *
 * Le Core livre au moins une fois : l'`id` de l'événement est la clé de
 * dédoublonnage. On la garde deux jours — assez pour survivre à une
 * redelivery, et pas plus. Un rejeu demandé par l'opérateur porte le même
 * `id`, donc la même clé : il est bien dédoublonné.
 */
final class CacheEventStore implements EventStore
{
    public function __construct(
        private readonly CacheRepository $cache,
        private readonly int $ttlSeconds = 172800,
    ) {}

    public function has(string $eventId): bool
    {
        return $this->cache->has($this->key($eventId));
    }

    public function remember(string $eventId): void
    {
        $this->cache->put($this->key($eventId), true, $this->ttlSeconds);
    }

    private function key(string $eventId): string
    {
        return 'core_adapter:event:'.$eventId;
    }
}
