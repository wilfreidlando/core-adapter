<?php

declare(strict_types=1);

namespace CoreAdapter\Laravel\Cache;

use CoreAdapter\Contracts\EntitlementsCache;
use CoreAdapter\Dto\Entitlements;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Le cache des droits, sur le cache de Laravel.
 *
 * Les droits sont lus à chaque page sensible : les mettre en cache évite une
 * requête par page. Mais un abonnement suspendu ou un quota atteint doivent
 * s'appliquer **vite** : le cache est vidé dès qu'un événement
 * `subscription.*`, `invoice.*` ou `usage.*` arrive.
 */
final class CacheEntitlementsCache implements EntitlementsCache
{
    public function __construct(
        private readonly CacheRepository $cache,
        private readonly int $ttlSeconds = 60,
    ) {}

    public function remember(string $customerRef, callable $resolver): Entitlements
    {
        if ($this->ttlSeconds <= 0) {
            return $resolver();
        }

        $cached = $this->cache->remember(
            $this->key($customerRef),
            $this->ttlSeconds,
            function () use ($resolver): Entitlements {
                return $resolver();
            },
        );

        return $cached;
    }

    public function forget(string $customerRef): void
    {
        $this->cache->forget($this->key($customerRef));
    }

    private function key(string $customerRef): string
    {
        return 'core_adapter:entitlements:'.$customerRef;
    }
}
