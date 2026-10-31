# saas-commerce-dep

**Le client PHP du Core.** Votre application reçoit son locataire, lit ses droits,
compte son usage, ouvre son portail et reçoit ses événements. Elle ne fait
**aucun** commerce : le portail du Core s'en charge.

Le paquet est le client officiel de l'API `/v1` du Core. Il contient :

- un **noyau** sans framework (`SaasCommerce\Core`) : le client HTTP, le type
  d'erreur, la vérification de signature, et une classe par service ;
- un **adaptateur Laravel** (`SaasCommerce\Laravel`) : le service provider
  (auto-découvert), le transport sur `Illuminate\Http\Client`, le contrôleur de
  webhook, le middleware, l'événement, l'écouteur et la commande
  `core:check`.

Le noyau n'a **aucune dépendance runtime** : `require` ne contient que `php`.
L'application Laravel bénéficie de sa propre configuration HTTP ; une
application qui n'est pas en Laravel utilise le transport `curl` du noyau.

---

## Installation

### 1. Le paquet

Le paquet n'est pas sur Packagist : il vient du dépôt. Dans le `composer.json`
de votre application :

```json
{
    "require": {
        "wilfreidlando/saas-commerce-dep": "^1.0"
    },
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/wilfreidlando/saas-commerce-dep.git"
        }
    ]
}
```

Puis :

```bash
composer require wilfreidlando/saas-commerce-dep
```

### 2. Les trois clés

Le Core vous remet une clé de projet (`pk_…`) et un secret d'événements
(`whsec_…`). Trois variables suffisent :

```dotenv
CORE_BASE_URL=https://core.example.com
CORE_API_KEY=pk_XXXXXXXX.XXXXXXXXXXXXXXXX
CORE_WEBHOOK_SECRET=whsec_XXXXXXXXXXXXXXXX
```

`CORE_BASE_URL` est l'adresse du Core — le staging et la production sont deux
déploiements distincts, avec des clés distinctes.

Sous Laravel, publiez la configuration si vous voulez la modifier :

```bash
php artisan vendor:publish --tag=core-config
```

### 3. La route du point d'entrée

Les événements du Core arrivent sur **une** route, hors du groupe `web` (pas de
CSRF, pas de session : la sécurité, c'est la signature). Dans
`bootstrap/app.php` (Laravel 11 et plus) :

```php
->withRouting(then: function () {
    Route::middleware('api')->group(
        base_path('vendor/wilfreidlando/saas-commerce-dep/routes/core.php')
    );
})
```

ou, dans `routes/api.php` (Laravel 10 et avant) :

```php
require base_path('vendor/wilfreidlando/saas-commerce-dep/routes/core.php');
```

### 4. La vérification

```bash
php artisan core:check
```

La commande vérifie que l'adresse est joignable, que la clé est valide, que les
portées dont le paquet a besoin sont présentes, et que le secret des
événements est configuré. Elle dit **ce qui manque**, et sort en code `1` dès
qu'un élément requis manque.

```
Core — https://core.example.com

  ✓ L'adresse du Core est configurée
  ✓ La clé du projet est configurée
  ✓ Le secret des événements est configuré
  ✓ Le Core répond (projet 01a101a1-7a96-712d-b63a-e3bbc27ae721)
  ✓ Les portées du paquet sont présentes (customers:read, usage:read, usage:write, portal:write)
  · notifications (service optionnel) : il manque notifications:write

  Tout est branché.
```

C'est fait : le paquet est installé, les clés sont là, les points d'entrée
sont appelables.

---

## Les cinq capacités

### 1. Recevoir son locataire, et lire son identité

Le Core est le **maître de l'identité** du locataire : c'est lui qui le crée
(l'opérateur, depuis la console du Core) et qui le pousse vers votre
application. Votre application ne l'écrit **jamais** — elle le reçoit, par
l'événement `customer.created`, puis elle le lit quand elle a besoin de son
e-mail pour le lier à son compte local.

```php
use SaasCommerce\Core\Services\Customers;

$customers = app(Customers::class);

// L'événement `customer.created` vous a donné la référence du locataire.
$customer = $customers->get('auto-ecole-42');

$customer->email;      // contact@auto-ecole-la-fontaine.cm
$customer->displayName;
$customer->externalRef;
```

La charge utile d'un événement ne porte **aucune donnée personnelle** : le
locataire y est nommé par votre propre référence. C'est pourquoi l'identité se
**demande** (`GET /v1/customers/{ref}`, portée `customers:read`).

### 2. Lire ses droits

```php
use SaasCommerce\Core\Services\Entitlements;

$entitlements = app(Entitlements::class)->get('auto-ecole-42');

$entitlements->active;             // false : aucun contrat ne donne accès
$entitlements->planCode;            // « pro »
$entitlements->allows('students');  // la fonctionnalité est-elle ouverte ?
$entitlements->remaining('students'); // 38
```

Les droits sont mis en cache pendant une minute (`CORE_ENTITLEMENTS_TTL`, `0`
pour désactiver), et le cache est vidé dès qu'un événement `subscription.*`,
`invoice.*` ou `usage.*` arrive.

### 3. Compter son usage

```php
use SaasCommerce\Core\Services\Usage;

$usage = app(Usage::class);

$decision = $usage->consume(
    customerRef: 'auto-ecole-42',
    meter: 'students',
    idempotencyKey: 'inscription:STUDENT-2026-0042',
    quantity: 1,
);

$decision->isCounted();  // true : compté (ou rejoué sans double comptage)
$decision->remaining;    // 37
```

La clé d'idempotence est **stable** et identifie le *fait*, pas la requête :
`inscription:STUDENT-2026-0042`. Rejouer la même clé ne compte pas deux fois.
Une clé par fait, jamais par requête.

> **La clé d'idempotence de cette route voyage dans le corps** (`idempotency_key`),
> et **seulement** là : c'est la seule route du Core où elle n'est pas un
> en-tête. Le paquet rend la confusion impossible — c'est `Usage::consume()` qui
> construit le corps, et le client refuse d'envoyer une clé en en-tête quand le
> corps en porte déjà une.

### 4. Ouvrir le portail

```php
use SaasCommerce\Core\Services\Portal;

$session = app(Portal::class)->open('auto-ecole-42');

return redirect($session->url);
```

Le lien est **à usage unique** et **valable dix minutes**. Redirectez au clic,
ne le stockez jamais.

Deux paramètres facultatifs : `flow` (`manage` par défaut, ou `pay_invoice`) et
`returnUrl` (où le bouton « Retour » du portail renvoie le propriétaire — l'adresse
doit être dans la liste blanche du projet, sinon le Core répond `422`).

### 5. Recevoir son état

Le Core pousse l'état de votre locataire (abonnement, quotas, factures) vers
votre point d'entrée, **signé**. Le paquet vérifie la signature, dédoublonne par
l'`id` de l'événement, puis vous transmet un événement Laravel :

```php
use Illuminate\Support\Facades\Event;
use SaasCommerce\Laravel\Events\CoreEventReceived;

Event::listen(CoreEventReceived::class, function (CoreEventReceived $event): void {
    if ($event->type === 'customer.created') {
        // le locataire existe : créer (ou lier) son compte local
    }

    if ($event->is('subscription')) {
        // l'abonnement a bougé
    }

    $event->customerRef();  // la référence du locataire, si l'événement en porte une
});
```

Les événements qui vous concernent : `customer.created`, `customer.updated`,
`customer.suspended`, `customer.reactivated`, `customer.erased`,
`subscription.*`, `invoice.*`, `usage.threshold_reached`, `usage.limit_reached`.

Le Core livre **au moins une fois** : le paquet dédoublonne par l'`id`, et
répond `204` à une livraison déjà traitée. Une signature invalide répond `401` —
le seul `4xx` à renvoyer, les autres font abandonner le Core.

---

## Les services optionnels

Trois services s'ajoutent selon ce que votre application fait. Ils sont
disponibles dans le conteneur comme les autres.

### Notifications — les applications qui envoient des WhatsApp

```php
use SaasCommerce\Core\Services\Notifications;

$notifications = app(Notifications::class);

$sent = $notifications->text(
    toPartyRef: 'client-42',              // VOTRE référence, jamais un numéro
    phoneNumber: '+237699887766',
    text: 'Votre inscription est confirmée.',
);

$sent->status;        // « sent » ou « failed »
$sent->hasFailed();
$sent->errorMessage;  // pourquoi, quand c'est « failed »

$notifications->image('client-42', '+237699887766', 'https://exemple/receipt.png');
$notifications->file('client-42', '+237699887766', 'https://exemple/facture.pdf', 'facture.pdf', 'application/pdf');
$notifications->status($sent->id);
```

Le contenu est à vous : le Core n'a aucun modèle pré-enregistré. La session
WhatsApp est celle que l'opérateur du Core a liée à votre projet
(`CORE_WHATSAPP_SESSION`).

### Payments — les applications qui encaissent

```php
use SaasCommerce\Core\Services\Payments;

$payments = app(Payments::class);

$payment = $payments->paylink([
    'channel' => 'mtn_momo',
    'amount' => 5000,          // en plus petite unité : 5 000 XAF
    'currency' => 'XAF',
    'reason' => 'Commande #42',
], 'order-42');                // votre clé d'idempotence

$payment->paymentUrl;  // envoyez-y le payeur
$payment->isFinal;     // false : attendez l'événement

$payments->get($payment->id);
```

Rien n'est certain avant l'événement `payment.status_changed` avec
`is_final = true`.

### Wallets — les applications qui gèrent un portefeuille

```php
use SaasCommerce\Core\Services\Wallets;

$wallets = app(Wallets::class);

$wallets->ofParty('merchant-01');   // les portefeuilles de la partie
$wallets->balance($walletId);
$wallets->transactions($walletId);

$wallets->transfer('transfer-42', 'XAF', [
    ['from' => 'escrow', 'to' => 'merchant-01', 'amount' => 45000],
]);

$wallets->withdraw('withdrawal-42', 'merchant-01', 120000, 'XAF', 'Versement MTN', 'MP241014.1532.C12345');
```

L'argent n'entre dans un portefeuille que par un paiement **confirmé** qui le
nomme : il n'y a pas de crédit libre. `withdraw()` **enregistre** un versement
que vous avez fait hors du Core — le Core n'envoie pas l'argent.

---

## Les portées dont la clé a besoin

| Ce que vous voulez | Appel | Portée |
|---|---|---|
| lire l'identité du locataire | `GET /v1/customers/{ref}` | `customers:read` |
| lire ses droits | `GET /v1/customers/{ref}/entitlements` | `usage:read` |
| compter son usage | `POST /v1/usage` | `usage:write` |
| ouvrir le portail | `POST /v1/portal/sessions` | `portal:write` |
| envoyer une notification | `POST /v1/notifications/{text,image,file}` | `notifications:write` |
| lire une notification | `GET /v1/notifications/{id}` | `notifications:read` |
| encaisser | `POST /v1/payments/{paylink,payin}` | `payments:write` |
| lire un paiement | `GET /v1/payments/{id}` | `payments:read` |
| lire un portefeuille | `GET /v1/wallets/…` | `wallets:read` |
| écrire dans un portefeuille | `POST /v1/wallets/{transfers,withdrawals}` | `wallets:write` |

Les quatre premières sont **obligatoires** : le paquet en a besoin pour ses
surfaces toujours présentes. Les autres ne concernent que les services
optionnels — demandez-les si vous les utilisez.

Demandez à l'administrateur du Core une clé avec le modèle de portées
**« Application cliente »** :

```
customers:read · usage:read · usage:write · portal:write
```

`php artisan core:check` vous dit si votre clé porte ce qu'il faut.

---

## Ce que le paquet ne fait pas

**Le paquet ne fait pas de commerce.** Il n'y a pas de méthode pour créer une
offre, un abonnement ou une facture ; il n'y en a pas plus pour supprimer un
client ou enregistrer un point d'entrée webhook. Les offres, les
abonnements, les factures et les encaissements se gèrent :

- **dans le portail**, que le Core héberge, et où **votre propriétaire** choisit
  son offre, s'abonne, change d'offre, se désabonne, paie ses factures et voit
  son usage ;
- **depuis la console du Core**, par l'opérateur, pour ce qui relève de la
  plateforme (créer un client, enregistrer un point d'entrée webhook, lier une
  session WhatsApp).

Ce n'est pas une promesse de documentation : c'est une propriété vérifiée. La
clé d'une application cliente ne porte **aucune** portée d'écriture sur le
commerce, donc votre application *ne peut pas* faire de commerce, quelle que
soit la ligne de code qu'elle exécute. Et la suite de tests du paquet échoue si
une de ses classes expose un jour une méthode de commerce.

> **Si vous avez besoin d'une opération que le paquet n'expose pas**, c'est
> que c'est une opération de commerce — et elle se fait dans le portail.

Le parcours complet, avec le `curl` de chaque appel et le « pourquoi » de
chaque règle, est dans le guide du Core :
**[`docs/integration/brancher-une-application-cliente.md`](https://github.com/wilfreidlando/vcam-core-system/blob/main/docs/integration/brancher-une-application-cliente.md)**.

---

## Hors Laravel

Le noyau ne dépend d'aucun framework. Vous construisez le client vous-même :

```php
use SaasCommerce\Core\CoreClient;
use SaasCommerce\Core\CoreConfig;
use SaasCommerce\Core\Http\CurlTransport;
use SaasCommerce\Core\Services\Portal;

$client = new CoreClient(
    new CoreConfig(
        baseUrl: 'https://core.example.com',
        apiKey: 'pk_XXXXXXXX.XXXXXXXXXXXXXXXX',
        webhookSecret: 'whsec_XXXXXXXXXXXXXXXX',
    ),
    new CurlTransport(),
);

$session = (new Portal($client))->open('auto-ecole-42');
```

Pour la réception des événements, le noyau fournit la vérification de signature
et le dédoublonnage ; il vous faut un magasin d'événements (`EventStore`) —
`InMemoryEventStore` est fourni pour les tests et les scripts.

---

## Développement

```bash
composer install
composer test     # PHPUnit
composer stan     # PHPStan, niveau 10
composer lint     # Pint (PSR-12)
```

## Licence

MIT. Voir [LICENSE](LICENSE).
