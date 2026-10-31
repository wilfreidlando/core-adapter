<?php

declare(strict_types=1);

namespace CoreAdapter\Laravel;

use CoreAdapter\Contracts\EntitlementsCache;
use CoreAdapter\Contracts\EventStore;
use CoreAdapter\CoreClient;
use CoreAdapter\CoreConfig;
use CoreAdapter\Http\Transport;
use CoreAdapter\Laravel\Cache\CacheEntitlementsCache;
use CoreAdapter\Laravel\Cache\CacheEventStore;
use CoreAdapter\Laravel\Console\CheckCommand;
use CoreAdapter\Laravel\Events\CoreEventReceived;
use CoreAdapter\Laravel\Http\LaravelTransport;
use CoreAdapter\Laravel\Listeners\ApplyBillingEvent;
use CoreAdapter\Services\Customers;
use CoreAdapter\Services\Entitlements;
use CoreAdapter\Services\Notifications;
use CoreAdapter\Services\Payments;
use CoreAdapter\Services\Portal;
use CoreAdapter\Services\Usage;
use CoreAdapter\Services\Wallets;
use CoreAdapter\Webhooks\Receiver;
use CoreAdapter\Webhooks\Signature;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Le collage Laravel du paquet client du Core.
 *
 * Il est **auto-découvert** par Laravel : rien à faire dans `config/app.php`.
 * Il enregistre :
 *
 * - `CoreClient` et le `Transport` — sur le client HTTP de l'application, pour
 *   qu'elle garde la main sur sa configuration réseau ;
 * - les sept services, résolus par le conteneur :
 *   `Customers`, `Entitlements`, `Usage`, `Portal`, `Notifications`,
 *   `Payments`, `Wallets` ;
 * - le `Receiver` d'événements, le magasin de dédoublonnage (cache) et le
 *   cache des droits ;
 * - l'écouteur qui rafraîchit le cache des droits quand l'état du locataire
 *   bouge ;
 * - la commande `core:check`.
 *
 * ```php
 * $rights = app(CoreAdapter\Services\Entitlements::class)->get('auto-ecole-42');
 * ```
 */
final class CoreClientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/core.php', 'core');

        $this->app->singleton(
            CoreConfig::class,
            fn (ConfigRepository $config): CoreConfig => $this->coreConfig($config),
        );

        // Le transport est celui de l'application : `Http::fake()`, les
        // intergiciels globaux et le proxy du projet s'appliquent.
        $this->app->bind(
            Transport::class,
            fn (HttpFactory $factory, CoreConfig $config): Transport => new LaravelTransport(
                $factory,
                $config->timeout,
                $config->connectTimeout,
            ),
        );

        $this->app->singleton(
            CoreClient::class,
            fn (CoreConfig $config, Transport $transport): CoreClient => new CoreClient($config, $transport),
        );

        $this->app->singleton(
            EventStore::class,
            fn (CacheRepository $cache): EventStore => new CacheEventStore($cache),
        );

        $this->app->singleton(
            EntitlementsCache::class,
            fn (CacheRepository $cache, CoreConfig $config): EntitlementsCache => new CacheEntitlementsCache(
                $cache,
                $config->entitlementsTtl,
            ),
        );

        $this->app->singleton(
            Signature::class,
            fn (CoreConfig $config): Signature => new Signature($config->webhookTolerance),
        );

        $this->app->singleton(
            Receiver::class,
            fn (Signature $signature, EventStore $events, CoreConfig $config): Receiver => new Receiver(
                $signature,
                $events,
                $config->webhookSecret ?? '',
            ),
        );

        $this->app->singleton(Customers::class, fn (CoreClient $client): Customers => new Customers($client));

        $this->app->singleton(
            Entitlements::class,
            fn (CoreClient $client, EntitlementsCache $cache): Entitlements => new Entitlements($client, $cache),
        );

        $this->app->singleton(Usage::class, fn (CoreClient $client): Usage => new Usage($client));

        $this->app->singleton(Portal::class, fn (CoreClient $client): Portal => new Portal($client));

        $this->app->singleton(
            Notifications::class,
            fn (CoreClient $client, CoreConfig $config): Notifications => new Notifications(
                $client,
                $config->whatsappSession,
            ),
        );

        $this->app->singleton(Payments::class, fn (CoreClient $client): Payments => new Payments($client));

        $this->app->singleton(Wallets::class, fn (CoreClient $client): Wallets => new Wallets($client));
    }

    public function boot(): void
    {
        // Les droits sont lus à chaque page sensible : ils sont mis en cache,
        // et ce cache est vidé dès que l'état du locataire bouge.
        Event::listen(CoreEventReceived::class, ApplyBillingEvent::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/core.php' => $this->app->configPath('core.php'),
            ], 'core-config');

            $this->commands([CheckCommand::class]);
        }
    }

    private function coreConfig(ConfigRepository $config): CoreConfig
    {
        return new CoreConfig(
            baseUrl: $this->configString($config, 'core.base_url'),
            apiKey: $this->configString($config, 'core.api_key'),
            timeout: $this->configInt($config, 'core.timeout', 30),
            connectTimeout: $this->configInt($config, 'core.connect_timeout', 5),
            webhookSecret: $this->configStringOrNull($config, 'core.webhook_secret'),
            whatsappSession: $this->configStringOrNull($config, 'core.notifications.whatsapp_session'),
            webhookTolerance: $this->configInt($config, 'core.webhook_tolerance', 300),
            entitlementsTtl: $this->configInt($config, 'core.entitlements_ttl', 60),
        );
    }

    private function configString(ConfigRepository $config, string $key): string
    {
        $value = $config->get($key);

        return is_string($value) ? $value : '';
    }

    private function configStringOrNull(ConfigRepository $config, string $key): ?string
    {
        $value = $config->get($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function configInt(ConfigRepository $config, string $key, int $default): int
    {
        $value = $config->get($key);

        return is_numeric($value) ? (int) $value : $default;
    }
}
