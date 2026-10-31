<?php

declare(strict_types=1);

namespace SaasCommerce\Core;

/**
 * Les portées dont le paquet a besoin.
 *
 * Le Core est le maître de la relation commerciale : la clé d'une application
 * cliente ne porte **aucune** écriture sur le commerce. Ce n'est pas une
 * promesse — c'est la portée de la clé, vérifiée par le Core à chaque appel.
 */
final class Scopes
{
    /** Lire l'identité du locataire (`GET /v1/customers/{ref}`). */
    public const CUSTOMERS_READ = 'customers:read';

    /** Lire ses droits (`GET /v1/customers/{ref}/entitlements`). */
    public const USAGE_READ = 'usage:read';

    /** Compter son usage (`POST /v1/usage`). */
    public const USAGE_WRITE = 'usage:write';

    /** Ouvrir son portail (`POST /v1/portal/sessions`). */
    public const PORTAL_WRITE = 'portal:write';

    /** Lire une notification (`GET /v1/notifications/{id}`). */
    public const NOTIFICATIONS_READ = 'notifications:read';

    /** Envoyer une notification (`POST /v1/notifications/{text,image,file}`). */
    public const NOTIFICATIONS_WRITE = 'notifications:write';

    /** Lire un paiement (`GET /v1/payments/{id}`). */
    public const PAYMENTS_READ = 'payments:read';

    /** Créer un paiement (`POST /v1/payments/{paylink,payin}`). */
    public const PAYMENTS_WRITE = 'payments:write';

    /** Lire un portefeuille (`GET /v1/wallets/…`). */
    public const WALLETS_READ = 'wallets:read';

    /** Écrire dans un portefeuille (`POST /v1/wallets/{transfers,withdrawals}`). */
    public const WALLETS_WRITE = 'wallets:write';

    /**
     * Les portées des surfaces **toujours présentes** : sans elles, le paquet
     * ne peut pas faire son travail.
     *
     * @var list<string>
     */
    public const REQUIRED = [
        self::CUSTOMERS_READ,
        self::USAGE_READ,
        self::USAGE_WRITE,
        self::PORTAL_WRITE,
    ];

    /**
     * Les portées des services **optionnels**, service par service : une
     * application qui n'envoie pas de WhatsApp n'a pas besoin de
     * `notifications:write`.
     *
     * @var array<string, list<string>>
     */
    public const OPTIONAL = [
        'notifications' => [self::NOTIFICATIONS_READ, self::NOTIFICATIONS_WRITE],
        'payments' => [self::PAYMENTS_READ, self::PAYMENTS_WRITE],
        'wallets' => [self::WALLETS_READ, self::WALLETS_WRITE],
    ];
}
