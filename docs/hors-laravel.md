# Hors Laravel

Ce que fait cette page : utiliser le paquet dans une application qui n'est
pas en Laravel — un service Symfony, une API Node qui parle à PHP, un
script, une commande CLI.

Le **noyau** du paquet (`src/`, hors `src/Laravel/`) ne dépend de rien : ni
Laravel, ni Guzzle, ni une queue. Il ne demande qu'un **transport HTTP** et,
pour les événements, un **magasin** capable de se souvenir d'un identifiant.

> Le noyau ne référence **jamais** Illuminate — c'est vérifié par un test du
> paquet (`tests/Unit/PackageTest.php`). Vous pouvez l'utiliser depuis
> n'importe quel langage, en suivant la même documentation.

---

## 1. La configuration

```php
use CoreAdapter\CoreConfig;

$config = new CoreConfig(
    baseUrl: 'https://core.exemple.com',
    apiKey: 'pk_xxxxxxxx',
    timeout: 30,
    connectTimeout: 5,
    webhookSecret: 'whsec_xxxxxxxx',   // si vous recevez des événements
    whatsappSession: null,
    webhookTolerance: 300,
    entitlementsTtl: 60,
);
```

## 2. Le client

```php
use CoreAdapter\CoreClient;
use CoreAdapter\Http\CurlTransport;

$client = new CoreClient($config, new CurlTransport(timeout: 30, connectTimeout: 5));
```

Le transport par défaut est **curl** (`ext-curl`). Si votre environnement n'a
pas curl, écrivez le vôtre : l'interface `Transport` tient en une méthode.

```php
use CoreAdapter\Http\Transport;
use CoreAdapter\Http\TransportResponse;

final class MonTransport implements Transport
{
    public function send(string $method, string $url, array $headers, ?string $body): TransportResponse
    {
        // … votre HTTP
        return new TransportResponse($status, $headers, $rawBody);
    }
}
```

## 3. Les services

Chaque service prend le client. Ils sont indépendants : prenez ceux dont vous
avez besoin.

```php
use CoreAdapter\Services\Customers;
use CoreAdapter\Services\Entitlements;
use CoreAdapter\Services\Notifications;
use CoreAdapter\Services\Payments;
use CoreAdapter\Services\Portal;
use CoreAdapter\Services\Usage;
use CoreAdapter\Services\Wallets;

$clients = new Customers($client);
$droits = new Entitlements($client);          // sans cache
$usage = new Usage($client);
$portail = new Portal($client);
$notifications = new Notifications($client, $whatsappSession);
$paiements = new Payments($client);
$portefeuilles = new Wallets($client);
```

### Le cache des droits, hors Laravel

`Entitlements` accepte un cache, ou rien :

```php
$droits = new Entitlements($client);                        // pas de cache
$droits = new Entitlements($client, $monCache);              // avec le vôtre
```

Le contrat est minuscule :

```php
use CoreAdapter\Contracts\EntitlementsCache;
use CoreAdapter\Dto\Entitlements;

final class MonCache implements EntitlementsCache
{
    public function remember(string $customerRef, callable $resolver): Entitlements
    {
        // … cachez la valeur de $resolver(), avec votre durée
    }

    public function forget(string $customerRef): void
    {
        // … oubliez la clé
    }
}
```

N'oubliez pas d'appeler `forget()` quand l'état du locataire bouge : sinon
votre cache sert un abonnement suspendu pendant une minute de trop.

## 4. Recevoir les événements

Trois pièces : la **signature**, le **magasin**, et votre **point d'entrée**.

```php
use CoreAdapter\Webhooks\Receiver;
use CoreAdapter\Webhooks\Signature;

$signature = new Signature(toleranceSeconds: 300);
$receiver = new Receiver($signature, $monEventStore, $config->webhookSecret ?? '');
```

### Le magasin d'événements

Le Core livre **au moins une fois** : le même événement peut arriver deux
fois. L'`id` de l'événement est la clé de dédoublonnage.

```php
use CoreAdapter\Contracts\EventStore;

final class MonEventStore implements EventStore
{
    public function has(string $eventId): bool { /* … */ }

    public function remember(string $eventId): void { /* … */ }
}
```

> `InMemoryEventStore` existe, mais il ne survit pas au redémarrage : le Core
> rejouera alors les événements du tampon. En production, utilisez un Redis,
> une table, ou un fichier.

### Le point d'entrée

```php
$event = $receiver->accept($rawBody, $headers);   // lève InvalidSignatureException

if ($event === null) {
    // 204 : la signature est bonne, mais ce n'est pas une enveloppe
}

if ($receiver->isDuplicate($event)) {
    // 204 : déjà traité
}

try {
    // … votre métier
} finally {
    $receiver->markProcessed($event);   // APRÈS votre métier
}
```

**La règle qui compte : marquez l'événement comme traité *après* votre
traitement.** Si vous le marquez avant, une erreur de traitement est avalée
pour toujours. Si vous ne le marquez pas du tout, le Core vous en renverra
autant que vous ne l'aurez pas fait.

Répondez `204` une fois que tout est fait, et `401` si la signature ne
tient pas. **N'envoyez jamais d'autre 4xx** : le Core abandonne la
livraison, et l'événement part en lettres mortes.

### Les en-têtes

Le receiver lit `X-Signature` et `X-Timestamp` (noms insensibles à la
casse). Pendant une rotation de secret, le Core envoie **deux**
signatures : `X-Signature` (le nouveau) et `X-Signature-Previous` (l'ancien,
encore valable). Les deux sont acceptées : la bascule se fait sans rejet.

## 5. Le locataire, hors Laravel

La partie Laravel du paquet (la file, l'écouteur, la commande) n'existe pas
hors Laravel — mais le **noyau** suffit :

```php
use CoreAdapter\Services\Customers;

$client = $clients->get('AE1234');

$client->displayName;          // la raison sociale
$client->email;                // l'e-mail
$client->phone;
$client->declaredAttributes;   // ['localisation' => 'Douala']
```

À vous d'écrire le reste : créer votre locataire quand l'événement arrive,
le mettre en file, etc. La règle du Core reste la même — **aucun appel réseau
dans un écouteur**, le travail part en file.

## 6. Ce qui n'existe pas hors Laravel

| Ce qui manque | Pourquoi | Que faire |
|---|---|---|
| `php artisan core:check` | une commande Artisan | vérifiez `GET /v1/whoami` à la main : il rend le projet et les portées de la clé |
| `php artisan core:declare-tenant-schema` | une commande Artisan | `PUT /v1/projects/{id}/tenant-schema` — et le paquet translate le motif dans `CorePattern::normalize()` |
| Le provisionnement automatique | une file Laravel | écrivez-le, en file de votre framework |
| Le cache des droits Laravel | le cache Laravel | implémentez `EntitlementsCache` |

> Pour la déclaration de la forme du locataire, le noyau est utilisable :
>
> ```php
> use CoreAdapter\Support\CorePattern;
> use CoreAdapter\Support\TenantSchemaPayload;
>
> $charge = TenantSchemaPayload::build(
>     ['attribut' => 'code', 'motif' => '/^AE[0-9]{4}$/'],
>     [['cle' => 'localisation', 'source' => 'declared.localisation', 'requis' => true]],
> );
>
> $charge['unique_key_pattern'];   // '^AE[0-9]{4}$' — déjà normalisé
> ```

## 7. L'erreur unique

Toutes les routes lèvent la même exception :

```php
use CoreAdapter\CoreException;

try {
    $paiements->paylink($commande, 'commande-42');
} catch (CoreException $e) {
    $e->status;         // 0 (pas de réponse), 402, 409, 422…
    $e->type;           // 'insufficient-funds', 'quota-exceeded'…
    $e->detail;         // le message, en français
    $e->body;           // le problem+json décodé
    $e->retryAfter;     // null, ou un nombre de secondes
    $e->correlationId;  // l'identifiant de corrélation, à mettre dans vos logs

    if ($e->isTransient()) {
        // rejouer plus tard, avec la MÊME clé d'idempotence
    }
}
```

Voir [Erreurs](erreurs.md).
