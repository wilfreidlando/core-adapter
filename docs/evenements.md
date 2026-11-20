# Événements

Ce que fait cette page : recevoir ce que le Core vous dit, sans le
confondre avec un appel.

---

## L'enveloppe

Chaque livraison est un POST JSON sur votre point d'entrée :

```json
{
  "id": "0199f3c2-…",
  "type": "payment.status_changed",
  "data": { "…": "…" }
}
```

| Champ | Ce que c'est |
|---|---|
| `id` | l'identifiant **de la livraison**. C'est votre clé de dédoublonnage. |
| `type` | le type d'événement. |
| `data` | la charge utile de l'événement. |

Les mêmes informations sont dans les en-têtes `X-Event-Id` et
`X-Event-Type` — mais le corps se suffit à lui-même : ne dépendez pas des
en-têtes.

Les livraisons de webhook portent en plus `X-Delivery-Id` : il identifie
**la livraison**, pas l'événement. Un rejeu par l'opérateur garde le même
`X-Event-Id` et change le `X-Delivery-Id` — c'est donc `X-Event-Id` qu'il
faut dédoublonner, jamais `X-Delivery-Id`.

> Le `callback_url` d'un paiement ne porte **pas** `X-Delivery-Id` : c'est
> une livraison à part, sans fichier de livraison derrière.

> **Le Core livre au moins une fois.** Le même événement peut arriver deux
> fois : un `2xx` perdu sur le réseau, un rejeu demandé par l'opérateur. Votre
> traitement doit être **idempotent**, et vous devez dédoublonner sur `id`.

## Enregistrer votre point d'entrée

Le Core ne devine pas votre URL : vous l'enregistrez, **une fois**.

```
POST /v1/webhook-endpoints
Authorization: Bearer pk_…
Content-Type: application/json

{
  "url": "https://mon-app.exemple.com/webhooks/core",
  "events": ["customer.created", "customer.updated", "payment.status_changed"]
}
```

Portée requise : **`webhooks:write`**. La réponse est un `201` :

```json
{
  "id": "0199…",
  "url": "https://mon-app.exemple.com/webhooks/core",
  "events": ["customer.created", "…"],
  "secret": "whsec_…"
}
```

> ⚠️ **Le secret n'est montré qu'une fois.** Il ne sera jamais relu par
> l'API. C'est lui que vous mettez dans `CORE_WEBHOOK_SECRET` — **et c'est
> lui qui signe les livraisons**.

Le paquet n'expose pas encore cet appel en service : faites-le avec
`curl`, ou avec `CoreClient` directement :

```php
$client = app(CoreAdapter\CoreClient::class);

$client->post('webhook-endpoints', [
    'url' => route('core-adapter.webhooks.core', absolute: true),
    'events' => ['customer.created', 'customer.updated', 'payment.status_changed'],
]);
```

### Les règles de l'URL

- elle doit être **joignable depuis l'extérieur** par le réseau du Core ;
- **pas** de `localhost`, **pas** de plage privée (10/8, 192.168/16,
  172.16/12), **pas** d'adresse link-local — le Core refuse une URL qui
  pointe vers l'intérieur ;
- `https://` obligatoire en production ; `http://` n'est accepté qu'en
  développement local.

> Le refus des adresses privées n'est pas de la paranoïa : un point
> d'entrée est **rejoué automatiquement pendant des heures**. Une adresse
> qui pointe aujourd'hui vers un service interne pourrait devenir demain un
> canal d'exfiltration.

### Les événements à écouter

Mettez **la liste** de ce que votre application sait traiter. Le joker
`["*"]` est pratique en développement, et **à éviter en production** : de
nouveaux types d'événements y sont ajoutés sans prévenir, et peuvent casser
un récepteur qui attendait un schéma fixe.

Les autres routes :

| Route | Ce qu'elle fait |
|---|---|
| `GET /v1/webhook-endpoints` | liste vos points d'entrée |
| `DELETE /v1/webhook-endpoints/{id}` | désactive un point d'entrée |
| `POST /v1/webhook-endpoints/{id}/test` | envoie un `webhook.test` signé (10/minute) |
| `POST /v1/webhook-endpoints/{id}/rotate-secret` | fait tourner le secret |

## La signature

Le Core signe **le corps brut** :

```
X-Signature: <hexadécimal minuscule>
X-Timestamp: <unix, en secondes>
```

```
signature = HMAC-SHA256(timestamp + "." + corps_brut, secret)
```

Le paquet vérifie cela pour vous, et refuse tout ce qui ne tient pas :

- un secret vide → refus (à secret vide, n'importe qui calculerait une
  signature valide) ;
- un timestamp non numérique → refus ;
- un écart d'horloge au-delà de `webhook_tolerance` (300 s) → refus ;
- une signature fausse → refus.

La comparaison se fait en **temps constant** (`hash_equals`) : la durée de la
comparaison ne révèle rien.

### La rotation du secret

Pendant une rotation, le Core envoie **deux** signatures :

```
X-Signature: <le nouveau secret>
X-Signature-Previous: <l'ancien secret, encore valable>
```

Les deux sont acceptées : la bascule se fait **sans rejet**. C'est ce qui
permet de faire tourner un secret sans interruption de service.

## Ce qu'il faut répondre

| Réponse | Quand |
|---|---|
| `204` | traité, ou déjà traité |
| `401` | la signature ne tient pas |
| `500` | une exception dans votre traitement — le Core réessaiera |

> **N'envoyez jamais d'autre 4xx.** Un `400`, un `404`, un `422` sont
> considérés comme **définitifs** : la livraison va droit aux lettres
> mortes, sans nouvelle tentative. Seul le `401` est justifié — et il a un
> effet dur : il envoie la livraison en lettres mortes **immédiatement**.
> Si votre secret a changé sans rotation coordonnée, demandez un rejeu à
> l'opérateur.

> ⚠️ **Un paiement qui a un `callback_url` ne vient pas ici.** Pour ce
> paiement-là, l'événement est envoyé au `callback_url` **et nulle part
> ailleurs** : la diffusion vers vos points d'entrée est écartée. Si vous
> utilisez `callback_url`, votre route `/webhooks/core` ne verra **pas** vos
> `payment.status_changed`. Voir [Paiements](paiements.md).

## Les rejoues du Core

Une livraison échouée est **rejouée** : 8 tentatives au maximum, avec un
recul de 30 s, puis ×3 (30 s, 90 s, 270 s, 810 s, 2 430 s, 7 290 s, 6 h
plafond). Au-delà, la livraison part en **lettres mortes**, et l'opérateur
peut la **rejouer** depuis la console.

Le Core ne rejoue que ce qui est **transitoire** : un `5xx`, une connexion
refusée, un délai dépassé. Un `4xx` — sauf `408` et `429` — est définitif.

> **Le Core relaie ses événements une fois par minute.** Une livraison
> normale arrive donc **jusqu'à une minute** après le fait qu'elle rapporte.
> Ce n'est pas un retard de votre côté : ne construisez pas une logique qui
> suppose que l'événement arrive dans la seconde.

## Le catalogue des événements

### `customer.*` — le locataire

| Type | Ce que ça dit |
|---|---|
| `customer.created` | un client vient d'exister chez le Core |
| `customer.updated` | l'identité du client a changé |
| `customer.suspended` | le locataire est bloqué |
| `customer.reactivated` | le locataire est rétabli |
| `customer.erased` | le locataire a été effacé définitivement |

`customer.created` et `customer.updated` portent **exactement quatre clés** :

```json
{
  "customer_id": "0199…",
  "external_ref": "AE1234",
  "status": "active",
  "version": 1
}
```

> **Aucune donnée personnelle.** Ni e-mail, ni téléphone, ni raison sociale,
> ni valeur déclarée. Le locataire y est nommé par **votre** référence
> (`external_ref`). Si vous avez besoin de son identité, vous la
> **demandez** : `Customers::get('AE1234')`.

Et c'est pour ça que le paquet vous fait **relire** le client après
l'événement : c'est le seul moment où ses valeurs déclarées sont
disponibles. Voir [Le locataire](locataire.md).

### `payment.status_changed` — l'encaissement

```json
{
  "payment_id": "0199…",
  "reference": "commande-2026-0042",
  "status": "succeeded",
  "is_final": true,
  "amount": 5000,
  "currency": "XAF",
  "failure_reason": null,
  "occurred_at": "2026-11-20T09:12:44Z",
  "confirmed_amount": 5000,
  "exception": null
}
```

`reference` porte **votre** clé d'idempotence : c'est par là que vous
rapprochez l'événement de votre commande. Voir
[Paiements](paiements.md#1-lévénement--le-canal-fiable).

### `notification.*` — le message

```json
{
  "notification_id": "0199…",
  "channel": "whatsapp",
  "status": "delivered",
  "reason": null
}
```

| Type | Statut rapporté |
|---|---|
| `notification.delivered` | `delivered` |
| `notification.read` | `read` |
| `notification.failed` | `failed` |

`reason` porte la raison de l'échec, quand il y en a une.

### `wallet.*` — la valeur

`wallet.credited` et `wallet.debited` portent :

```json
{
  "wallet_id": "0199…",
  "party_id": "0199…",
  "party_external_ref": "AE1234",
  "amount_minor": 50000,
  "currency": "XAF",
  "operation_type": "transfer",
  "operation_id": "0199…",
  "reference": "transfer:2026-11-20:0042",
  "description": "Commission de mars"
}
```

Pour un crédit issu d'un paiement, `operation_type` vaut `payment` et
`payment_id` remplace `operation_id`.

### `subscription.*` — l'abonnement

```json
{
  "subscription_id": "0199…",
  "customer_ref": "AE1234",
  "status": "active",
  "cancel_at_period_end": false,
  "current_period": {
    "starts_at": "2026-11-01T00:00:00+00:00",
    "ends_at": "2026-12-01T00:00:00+00:00"
  },
  "version": 4
}
```

Les types : `subscription.created`, `.activated`, `.renewed`,
`.trial_will_end`, `.renewal_due`, `.plan_changed`, `.past_due`, `.paused`,
`.resumed`, `.canceled`, `.expired`, `.supplement_purchased`,
`.supplement_activated`, `.supplement_canceled`.

> `version` est le **numéro de version** de l'abonnement : les livraisons
> peuvent arriver **dans le désordre**. Comparez les versions avant
> d'appliquer un changement.

### `invoice.*` — la facture

```json
{
  "invoice_id": "0199…",
  "invoice_number": "FACT-2026-000042",
  "customer_ref": "AE1234",
  "status": "paid",
  "total_minor": 50000,
  "amount_paid_minor": 50000,
  "currency": "XAF",
  "due_at": "2026-11-30T00:00:00+00:00",
  "version": 2
}
```

Les types : `invoice.created`, `.issued`, `.paid`, `.payment_failed`,
`.voided`, `.receipt_recorded`, `.uncollectible`.

### `usage.*` — le quota

```json
{
  "customer_ref": "AE1234",
  "meter": "lessons",
  "used": 45,
  "limit": 50,
  "threshold_bps": 8000,
  "window_start": "2026-11-01T00:00:00+00:00",
  "window_end": "2026-12-01T00:00:00+00:00"
}
```

| Type | Ce que ça dit |
|---|---|
| `usage.threshold_reached` | le seuil est franchi (80 % par exemple) |
| `usage.limit_reached` | la limite est atteinte |

### `plan.*` — le catalogue

`plan.archived`, `plan.version_published` : l'offre a été retirée, ou une
nouvelle version a été publiée. Si votre application affiche un catalogue,
ces deux événements sont à écouter.

## Réagir dans votre application

```php
use CoreAdapter\Laravel\Events\CoreEventReceived;

Event::listen(CoreEventReceived::class, function (CoreEventReceived $e): void {
    match ($e->type) {
        'customer.created' => $this->surLocataireCree($e),
        'payment.status_changed' => $this->surPaiement($e),
        'subscription.*' => $this->surAbonnement($e),
        default => null,
    };
});
```

`is('prefix')` teste une **famille** :

```php
if ($e->is('subscription')) { /* … */ }   // .created, .activated, …
if ($e->is('customer')) { /* … */ }
```

`customerRef()` rend la référence du locataire, quand l'événement en porte
une (`customer_ref` ou `external_ref`) :

```php
$reference = $e->customerRef();   // 'AE1234' ou null
```

### Les deux règles de l'écouteur

**1. Aucun appel réseau dans un écouteur.** Le Core attend une réponse en
moins de dix secondes : lire le client chez le Core, écrire une ligne, et
envoyer un message n'y tient pas. Confiez le travail à une file.

```php
Event::listen(CoreEventReceived::class, function (CoreEventReceived $e): void {
    if ($e->type === 'customer.created') {
        ProvisionsTenant::dispatch($e->customerRef(), $e->id);   // en file
    }
});
```

C'est exactement ce que fait le paquet pour vous.

**2. Traitez l'événement une seule fois.** Dedupliquez sur `$e->id`, et
faites-le idempotent côté base (sur votre référence, sur votre identifiant de
commande).

```php
// L'id de l'événement dédouble les livraisons…
if (Cache::has('core-event:'.$e->id)) {
    return;
}

// …mais c'est VOTRE base qui doit garantir l'unicité du fait.
```

> Le paquet dédouble **les livraisons** (magasin d'événements, en cache).
> Il ne peut pas dédoublonner **vos effets** : seul votre dépôt peut le
> faire, sur votre clé métier.

## Deduplonner : le magasin

Le paquet dédouble sur l'`id` de l'événement, avec un `EventStore`. Sous
Laravel, c'est votre cache :

```php
use CoreAdapter\Contracts\EventStore;

final class MonEventStore implements EventStore
{
    public function has(string $eventId): bool
    {
        return Cache::has('core-event:'.$eventId);
    }

    public function remember(string $eventId): void
    {
        Cache::put('core-event:'.$eventId, true, now()->addDays(7));
    }
}
```

> La durée doit couvrir la fenêtre de rejeu du Core — quelques heures
> suffisent, mais une semaine ne gêne pas.

## Ce que le paquet fait, et ne fait pas

| Le paquet… | |
|---|---|
| ✅ | vérifie la signature |
| ✅ | dédouble les livraisons (`id`) |
| ✅ | émet `CoreEventReceived` avec `{id, type, data}` |
| ✅ | invalide le cache des droits quand l'état du locataire bouge |
| ✅ | crée votre locataire quand un client apparaît (si vous avez une fabrique) |
| ❌ | **ne décide rien** de votre métier |
| ❌ | ne fait aucun appel réseau dans un écouteur |
| ❌ | n'envoie rien au Core en votre nom |

## Vérifier que ça marche

```bash
# 1. Enregistrez votre point d'entrée (une fois)
curl -X POST https://core.exemple.com/v1/webhook-endpoints \
  -H "Authorization: Bearer pk_…" \
  -H "Content-Type: application/json" \
  -d '{"url":"https://mon-app.exemple.com/webhooks/core","events":["*"]}'

# 2. Demandez un test
curl -X POST https://core.exemple.com/v1/webhook-endpoints/{id}/test \
  -H "Authorization: Bearer pk_…"
```

Un `webhook.test` signé arrive sur votre route : si votre application le
journalise, le branchement est bon.
