<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | L'adresse du Core
    |--------------------------------------------------------------------------
    |
    | Le staging et la production sont deux déploiements distincts : une clé
    | du staging ne vaut rien sur la production.
    |
    */

    'base_url' => env('CORE_BASE_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | La clé du projet
    |--------------------------------------------------------------------------
    |
    | La clé (`pk_…`) que l'administrateur du Core vous a remise. Demandez le
    | modèle de portées « Application cliente » : elle lit les droits de vos
    | locataires, compte leur usage et ouvre leur portail — et n'a **aucun**
    | droit d'écriture sur le commerce.
    |
    */

    'api_key' => env('CORE_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Les délais
    |--------------------------------------------------------------------------
    |
    | Un encaissement attend le prestataire : laissez `timeout` généreux.
    | `connect_timeout` borne l'attente de la connexion seule.
    |
    */

    'timeout' => (int) env('CORE_TIMEOUT', 30),
    'connect_timeout' => (int) env('CORE_CONNECT_TIMEOUT', 5),

    /*
    |--------------------------------------------------------------------------
    | Le secret des événements
    |--------------------------------------------------------------------------
    |
    | Le secret (`whsec_…`) que l'opérateur du Core vous a remis pour votre
    | point d'entrée. Il signe les événements que vous recevez.
    |
    */

    'webhook_secret' => env('CORE_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | La tolérance d'horloge des événements
    |--------------------------------------------------------------------------
    |
    | Un événement plus vieux que cela est refusé : sans cette borne, un
    | message intercepté pourrait être rejoué indéfiniment.
    |
    */

    'webhook_tolerance' => (int) env('CORE_WEBHOOK_TOLERANCE', 300),

    /*
    |--------------------------------------------------------------------------
    | Les notifications
    |--------------------------------------------------------------------------
    |
    | Le nom de la session WhatsApp liée à votre projet. Les sessions sont
    | créées et liées par l'administrateur du Core, jamais par le client :
    | un nom inconnu donne `422 channel-session-not-found`, et rien n'est
    | envoyé.
    |
    */

    'notifications' => [
        'whatsapp_session' => env('CORE_WHATSAPP_SESSION'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Le cache des droits
    |--------------------------------------------------------------------------
    |
    | Les droits sont lus à chaque page sensible : les mettre en cache évite
    | une requête par page. Le cache est vidé dès qu'un événement
    | `subscription.*`, `invoice.*` ou `usage.*` arrive. `0` désactive le
    | cache.
    |
    */

    'entitlements_ttl' => (int) env('CORE_ENTITLEMENTS_TTL', 60),
];
