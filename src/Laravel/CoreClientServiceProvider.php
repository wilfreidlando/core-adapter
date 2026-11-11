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
use CoreAdapter\Laravel\Listeners\CustomerEventProvisionsTenant;
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
use Illuminate\Contracts\Foundation\Application;
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

        // Laravel appelle une fermeture de liaison avec `($container, $parameters)` :
        // le premier argument est **l'Application**, jamais la dépendance typée.
        // On reçoit donc le conteneur et on résout soi-même ce qu'on construit.
        $this->app->singleton(
            CoreConfig::class,
            fn (Application $app): CoreConfig => $this->coreConfig($app->make(ConfigRepository::class)),
        );

        // Le transport est celui de l'application : `Http::fake()`, les
        // intergiciels globaux et le proxy du projet s'appliquent.
        $this->app->bind(
            Transport::class,
            function (Application $app): Transport {
                $config = $app->make(CoreConfig::class);

                return new LaravelTransport(
                    $app->make(HttpFactory::class),
                    $config->timeout,
                    $config->connectTimeout,
                );
            },
        );

        $this->app->singleton(
            CoreClient::class,
            fn (Application $app): CoreClient => new CoreClient(
                $app->make(CoreConfig::class),
                $app->make(Transport::class),
            ),
        );

        $this->app->singleton(
            EventStore::class,
            fn (Application $app): EventStore => new CacheEventStore($app->make(CacheRepository::class)),
        );

        $this->app->singleton(
            EntitlementsCache::class,
            fn (Application $app): EntitlementsCache => new CacheEntitlementsCache(
                $app->make(CacheRepository::class),
                $app->make(CoreConfig::class)->entitlementsTtl,
            ),
        );

        $this->app->singleton(
            Signature::class,
            fn (Application $app): Signature => new Signature($app->make(CoreConfig::class)->webhookTolerance),
        );

        $this->app->singleton(
            Receiver::class,
            fn (Application $app): Receiver => new Receiver(
                $app->make(Signature::class),
                $app->make(EventStore::class),
                $app->make(CoreConfig::class)->webhookSecret ?? '',
            ),
        );

        $this->app->singleton(Customers::class, fn (Application $app): Customers => new Customers($app->make(CoreClient::class)));

        $this->app->singleton(
            Entitlements::class,
            fn (Application $app): Entitlements => new Entitlements(
                $app->make(CoreClient::class),
                $app->make(EntitlementsCache::class),
            ),
        );

        $this->app->singleton(Usage::class, fn (Application $app): Usage => new Usage($app->make(CoreClient::class)));

        $this->app->singleton(Portal::class, fn (Application $app): Portal => new Portal($app->make(CoreClient::class)));

        $this->app->singleton(
            Notifications::class,
            fn (Application $app): Notifications => new Notifications(
                $app->make(CoreClient::class),
                $app->make(CoreConfig::class)->whatsappSession,
            ),
        );

        $this->app->singleton(Payments::class, fn (Application $app): Payments => new Payments($app->make(CoreClient::class)));

        $this->app->singleton(Wallets::class, fn (Application $app): Wallets => new Wallets($app->make(CoreClient::class)));
    }

    public function boot(): void
    {
        // Les droits sont lus à chaque page sensible : ils sont mis en cache,
        // et ce cache est vidé dès que l'état du locataire bouge.
        Event::listen(CoreEventReceived::class, ApplyBillingEvent::class);

        // Un client existe chez le Core : l'application crée son locataire.
        // C'est la seule conséquence que le paquet attache à cet événement —
        // et elle ne se produit que si l'application fournit une fabrique.
        Event::listen(CoreEventReceived::class, CustomerEventProvisionsTenant::class);

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
