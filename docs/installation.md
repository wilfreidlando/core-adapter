# Installation

Ce que fait cette page : installer le paquet, le brancher sur le Core, et
**vérifier** que le branchement tient — en une commande.

Elle s'adresse à une application **Laravel 11, 12 ou 13**. Pour une
application qui n'est pas en Laravel, voir [Hors Laravel](hors-laravel.md).

---

## 1. Installer le paquet

Le paquet n'est pas sur Packagist : il vient du dépôt. Dans le
`composer.json` de votre application :

```json
{
    "require": {
        "wilfreidlando/core-adapter": "^1.6"
    },
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/wilfreidlando/core-adapter.git"
        }
    ]
}
```

Puis :

```bash
composer require wilfreidlando/core-adapter
```

Le service provider est **auto-découvert** par Laravel (la clé `extra.laravel`
du `composer.json`). Il n'y a rien à ajouter dans `config/app.php`.

> Le paquet ne dépend que de `php ^8.2`. Il n'installe **ni** Laravel, **ni**
> Guzzle, **ni** une queue : votre application garde la main sur tout cela.

## 2. La configuration

Publiez le fichier de configuration :

```bash
php artisan vendor:publish --tag=core-config
```

Il crée `config/core.php`. Renseignez votre `.env` :

```dotenv
CORE_BASE_URL=https://core.exemple.com
CORE_API_KEY=pk_xxxxxxxxxxxxxxxxxxxx
CORE_WEBHOOK_SECRET=whsec_xxxxxxxxxxxxxxxxxxxx

# Facultatif
CORE_TIMEOUT=30
CORE_CONNECT_TIMEOUT=5
CORE_WHATSAPP_SESSION=            # la session WhatsApp liée à votre projet
CORE_ENTITLEMENTS_TTL=60          # 0 désactive le cache des droits
CORE_WEBHOOK_TOLERANCE=300         # en secondes
```

> ⚠️ **Si vous encaissez en `payin`, montez le délai à 60.**
> `CORE_TIMEOUT=30` convient au `paylink`, mais le `payin` attend **15 à 20
> secondes** le prestataire (le Core ne peut répondre qu'une fois l'opérateur
> du payeur interrogé). À 30 secondes, l'appel est coupé à mi-chemin :
> le Core, lui, a déjà enregistré le paiement, et votre rejoue avec la même
> clé le retrouvera — mais vous aurez perdu 30 secondes pour le savoir.

### Les trois clés

| Clé | Où elle vient | À quoi elle sert |
|---|---|---|
| `CORE_BASE_URL` | l'administrateur du Core | l'adresse du Core, **sans** barre finale |
| `CORE_API_KEY` (`pk_…`) | l'administrateur du Core | votre clé de projet : elle porte vos **portées** |
| `CORE_WEBHOOK_SECRET` (`whsec_…`) | l'administrateur du Core | il signe les événements que le Core vous envoie |

Le staging et la production sont **deux déploiements distincts** : une clé du
staging ne vaut rien sur la production.

### Les portées de la clé

Demandez le modèle de portées **« Application cliente »**. Il donne :

| Portée | Ce qu'elle ouvre |
|---|---|
| `customers:read` | lire l'identité d'un client (`GET /v1/customers/{ref}`) |
| `usage:read` | lire les droits d'un client (`GET /v1/customers/{ref}/entitlements`) |
| `usage:write` | compter l'usage (`POST /v1/usage`) |
| `portal:write` | ouvrir le portail (`POST /v1/portal/sessions`) |
| `notifications:read` | relire l'état d'une notification |
| `notifications:write` | envoyer une notification |
| `payments:read` | relire un paiement |
| `payments:write` | encaisser (`paylink`, `payin`) |
| `wallets:read` | lire les portefeuilles, soldes, mouvements |
| `wallets:write` | transférer, enregistrer un retrait |
| `tenant_schema:write` | proposer la forme du locataire |
| `tenant_schema:read` | relire la forme du locataire |

Les quatre premières (`customers:read`, `usage:read`, `usage:write`,
`portal:write`) sont **requises** par le paquet : sans elles, il ne peut pas
vivre. Les autres sont **optionnelles**, service par service — une application
qui n'envoie pas de WhatsApp n'a pas besoin de `notifications:write`.

> Une clé qui porte la portée `*` fonctionne, mais demandez quand même le
> modèle « Application cliente » : elle ne vous empêche de rien, et elle
> n'ouvre pas le commerce.

## 3. La route du point d'entrée

Le Core pousse ses événements **vers vous**, à une URL que vous choisissez.
Une seule route est nécessaire : `POST /webhooks/core`.

**Laravel 11 et plus** — dans `bootstrap/app.php` :

```php
->withRouting(then: function () {
    Route::middleware('api')->group(
        base_path('vendor/wilfreidlando/core-adapter/routes/core.php')
    );
})
```

**Laravel 10 et avant** — dans `routes/api.php` :

```php
require base_path('vendor/wilfreidlando/core-adapter/routes/core.php');
```

La route est **hors** du groupe `web` : pas de CSRF, pas de session. La
sécurité, c'est la signature — voir [Événements](evenements.md).

### L'URL à donner à l'opérateur

```
https://votre-application.exemple.com/webhooks/core
```

Elle doit être **joignable depuis l'extérieur**, en HTTPS, et répondre en
moins de dix secondes. Si vous êtes derrière un proxy ou un *load balancer*,
vérifiez que le chemin `/webhooks/core` n'est pas réécrit.

> Le Core peut vous notifier sur **plusieurs** URL : **chacune** de vos
> points d'entrée enregistrés et abonnés reçoit sa propre livraison. Ce n'est
> pas « le premier qui répond gagne » — c'est une diffusion. Utile pour un
> déploiement sans interruption : les deux URLs sont servies, et le Core
> bascule de l'une à l'autre quand vous désactivez l'ancienne.

## 4. Vérifier le branchement

```bash
php artisan core:check
```

La sortie dit ce qui manque, au lieu de laisser croire que tout va bien :

```
Core — https://core.exemple.com

  ✓ L'adresse du Core est configurée
  ✓ La clé du projet est configurée
  ✓ Le secret des événements est configuré
  ✓ Le Core répond (projet auto-ecole)
  ✓ Les portées du paquet sont présentes (customers:read, usage:read, usage:write, portal:write)
  ✓ La forme du locataire est en force (version 3)

  Tout est branché.
```

Trois lignes peuvent être jaunes — elles **informent**, sans être des échecs :

- `! La clé porte la portée « * »` — demandez le modèle « Application cliente » ;
- `! Votre déclaration de la forme du locataire est en attente d'acceptation dans la console du Core.` — l'opérateur n'a pas encore validé votre [déclaration](locataire.md) ;
- `! Aucune forme du locataire n'est déclarée au Core` — lancez `php artisan core:declare-tenant-schema`.

La commande sort en code `1` dès qu'un élément **requis** manque.

## 5. Résoudre les services

Tous les services sont enregistrés dans le conteneur : rien à faire.

```php
use CoreAdapter\Services\Customers;
use CoreAdapter\Services\Entitlements;
use CoreAdapter\Services\Notifications;
use CoreAdapter\Services\Payments;
use CoreAdapter\Services\Portal;
use CoreAdapter\Services\Usage;
use CoreAdapter\Services\Wallets;

$client = app(Customers::class);
```

Pour une dépendance injectée (ce qui est mieux) :

```php
final readonly class MaCommande
{
    public function __construct(private Payments $paiements) {}

    public function __invoke(): void
    {
        $paiement = $this->paiements->paylink([
            'channel' => 'mtn_momo',
            'amount' => 5000,
            'currency' => 'XAF',
            'reason' => 'Abonnement mensuel',
        ], 'commande-2026-0042');
    }
}
```

## 6. Ce qu'il faut ensuite

| Vous voulez… | Faites |
|---|---|
| Que vos clients existent chez le Core | Demandez à l'opérateur d'importer vos locataires : [Le locataire](locataire.md) |
| Que le Core collects des informations sur vos clients | [`php artisan core:declare-tenant-schema`](locataire.md#dire-sa-forme-au-core) |
| Encaisser | [Paiements](paiements.md) |
| Réagir aux changements d'abonnement | [Événements](evenements.md) |

## Ce qui a été fait

```bash
php artisan core:check          # le branchement tient ?
php artisan core:export-tenants  # exporter les locataires existants (facultatif)
php artisan core:declare-tenant-schema   # proposer la forme du locataire
```

## Dépannage

| Symptôme | Cause probable |
|---|---|
| `Le Core ne répond pas` | `CORE_BASE_URL` faux, ou le Core injoignable depuis votre machine |
| `Le Core refuse la clé` | `CORE_API_KEY` d'un autre environnement (staging sur production) |
| `Il manque des portées : …` | le modèle de portées « Application cliente » n'a pas été demandé, ou la clé est périmée |
| `401 invalid signature` sur `/webhooks/core` | `CORE_WEBHOOK_SECRET` ne correspond pas à celui configuré dans la console du Core |
| `422 channel-session-not-found` en envoyant un message | `CORE_WHATSAPP_SESSION` vide ou inconnue : les sessions sont créées et liées par l'opérateur |
| `422 return-url-not-allowed` en ouvrant le portail | l'adresse de retour n'est pas dans la liste blanche du projet (console du Core) |
