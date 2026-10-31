<?php

declare(strict_types=1);

namespace SaasCommerce\Laravel;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SaasCommerce\Core\Contracts\EntitlementsCache;
use SaasCommerce\Core\Contracts\EventStore;
use SaasCommerce\Core\CoreClient;
use SaasCommerce\Core\CoreConfig;
use SaasCommerce\Core\Http\Transport;
use SaasCommerce\Core\Services\Customers;
use SaasCommerce\Core\Services\Entitlements;
use SaasCommerce\Core\Services\Notifications;
use SaasCommerce\Core\Services\Payments;
use SaasCommerce\Core\Services\Portal;
use SaasCommerce\Core\Services\Usage;
use SaasCommerce\Core\Services\Wallets;
use SaasCommerce\Core\Webhooks\Receiver;
use SaasCommerce\Core\Webhooks\Signature;
use SaasCommerce\Laravel\Cache\CacheEntitlementsCache;
use SaasCommerce\Laravel\Cache\CacheEventStore;
use SaasCommerce\Laravel\Console\CheckCommand;
use SaasCommerce\Laravel\Events\CoreEventReceived;
use SaasCommerce\Laravel\Http\LaravelTransport;
use SaasCommerce\Laravel\Listeners\ApplyBillingEvent;

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
 * $rights = app(SaasCommerce\Core\Services\Entitlements::class)->get('auto-ecole-42');
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
