# Droits, usage et portail

Ce que fait cette page : savoir **si** un client a le droit de faire quelque
chose, **compter** ce qu'il consomme, et l'envoyer dans **son** portail.

---

## Les droits — « ai-je le droit ? »

C'est la question que votre application pose à chaque page sensible. Une
seconde question suit : « qu'est-ce qu'il me reste ? ».

```php
use CoreAdapter\Services\Entitlements;

$droits = app(Entitlements::class)->get('AE1234');

$droits->active;              // true
$droits->planCode;            // 'premium'
$droits->subscriptionId;      // 'sub_01H…'
$droits->allows('export_pdf');// true / false
$droits->remaining('lessons');// 12 (null si le droit n'est pas un compteur)
```

### Les deux formes de droit

| Type | Ce que ça dit | Comment le lire |
|---|---|---|
| `boolean` | un droit **ouvert ou fermé** | `allows('clé')` → `true`/`false` |
| `meter` | un droit **compté** | `remaining('clé')` → le nombre restant |

```php
$droits->entitlement('lessons');
// ['key' => 'lessons', 'type' => 'meter', 'enabled' => true,
//  'meter' => 'lessons', 'limit' => 50, 'used' => 38, 'remaining' => 12,
//  'overage' => 'allowed', 'threshold_bps' => 8000,
//  'window_start' => '2026-11-01T00:00:00+00:00',
//  'window_end'   => '2026-12-01T00:00:00+00:00']
```

`threshold_bps` est un seuil en **millièmes de pour cent** : `8000` = 80 %.
`overage` dit ce qui se passe au-delà du plafond (`allowed`, `blocked`,
`priced`…).

### Le cache, et sa durée de vie

Les droits sont lus à chaque page sensible : les mettre en cache évite une
requête par page. Le cache vit **`CORE_ENTITLEMENTS_TTL` secondes** (60 par
défaut), et il est **vidé** dès qu'un événement `subscription.*`, `invoice.*`,
`usage.*` ou `customer.*` arrive : un abonnement suspendu s'applique vite.

```dotenv
CORE_ENTITLEMENTS_TTL=60   # 0 désactive le cache
```

> Si votre application affiche un compteur de « places restantes » mis à jour
> en direct, mettez le TTL à `0` : la valeur doit venir du Core, pas d'un
> cache à vous.

### Le patron recommandé

```php
final class LessonController
{
    public function __invoke(Request $request, Entitlements $droits)
    {
        $client = $request->user()->core_reference;

        if (! $droits->get($client)->allows('lessons')) {
            abort(403, 'Votre abonnement ne couvre pas ce cours.');
        }

        // … votre métier
    }
}
```

## L'usage — « j'ai consommé, compte-le »

Votre application **déclare** ce qu'elle a consommé ; le Core compte et
applique la limite. **Vous n'avez pas de compteur à tenir.**

```php
use CoreAdapter\Services\Usage;

$decision = app(Usage::class)->consume(
    customerRef: 'AE1234',
    meter: 'lessons',
    idempotencyKey: 'lecon:2026-11-20:STUDENT-0042',
    quantity: 1,
);

$decision->status;       // 'admitted' | 'replayed' | 'refused'
$decision->isCounted();  // true quand le fait a été compté
$decision->used;         // 39
$decision->limit;        // 50
$decision->remaining;    // 11
$decision->windowStart;  // '2026-11-01T00:00:00+00:00'
$decision->windowEnd;    // '2026-12-01T00:00:00+00:00'
```

### La clé d'idempotence : une par **fait**, jamais par requête

```php
// ✅ une clé par fait consommé
'lecon:2026-11-20:STUDENT-0042'

// ❌ une clé aléatoire : le fait sera compté deux fois
uniqid()
```

Rejouer la **même** clé ne compte pas deux fois : le Core répond `replayed`.
C'est ce qui rend l'appel sûr dans une file, où un job peut être rejoué.

> Particularité du Core : sur `POST /v1/usage`, la clé d'idempotence voyage
> **dans le corps** (`idempotency_key`), et non dans un en-tête. C'est la
> seule route dans ce cas — le paquet vous l'évite : `Usage::consume()`
> construit le corps, et le client refuse d'envoyer une clé en en-tête quand
> le corps en porte déjà une.

### `enforce` ou `record`

| Mode | Sens |
|---|---|
| `enforce` (défaut) | le Core **refuse** de compter au-delà du plan |
| `record` | le Core **constate** un fait déjà passé, et l'écrit même au-delà |

```php
// Un fait déjà passé (un cours donné hier, un import) : on constate.
$decision = $usage->consume('AE1234', 'lessons', 'cours:2026-11-19:0042', 1, mode: 'record');

// Un fait en cours : on applique le plan.
$decision = $usage->consume('AE1234', 'lessons', 'cours:2026-11-20:0042', 1);
```

`quantity` peut être **négative** : cela relâche un compteur (un élève part).
Zéro n'a pas de sens.

### Refuser soi-même, ou laisser le Core refuser

Deux politiques sont possibles :

```php
// Politique A — le Core est le juge : on compte et on regarde.
$decision = $usage->consume('AE1234', 'lessons', $clé);

if (! $decision->isCounted()) {
    abort(402, 'Votre quota est atteint.');
}
```

```php
// Politique B — on consulte d'abord (droits), puis on compte.
if ($droits->get('AE1234')->remaining('lessons') <= 0) {
    abort(402, 'Votre quota est atteint.');
}

$usage->consume('AE1234', 'lessons', $clé);
```

La politique A est la plus simple et la plus juste : le Core compte, il refuse,
et le compteur est le sien. La politique B coûte une lecture de plus, mais
permet d'afficher un message plus fin.

## Le portail — « allez régler votre compte vous-même »

Le commerce se fait dans le portail, **par le propriétaire**. Votre
application ne souscrit rien, ne modifie aucun plan, ne crée aucune facture :
ce sont des droits d'écriture sur le commerce, qu'une clé d'application
cliente n'a pas — et c'est voulu.

Un seul appel :

```php
use CoreAdapter\Services\Portal;

$session = app(Portal::class)->open(
    customerRef: 'AE1234',
    flow: 'manage',            // 'manage' | 'pay_invoice' | 'read'
    invoiceId: null,           // requis avec le flux 'pay_invoice'
    returnUrl: 'https://mon-app.exemple.com/retour',
);

$session->url;                 // lien à usage unique
$session->expiresAt;           // valable 10 minutes
$session->customerCreated;     // false
$session->subscriptionCreated; // false
```

Puis vous redirigez :

```php
return redirect($session->url);
```

### Les deux flux

| Flux | Ce qu'il ouvre |
|---|---|
| `manage` | la page d'accueil du portail : abonnements, factures, moyens de paiement |
| `pay_invoice` | une facture précise, pour la payer |
| `read` | le même portail, en **lecture seule** — pour un opérateur de support qui doit voir ce que voit le client sans rien toucher |

Le flux par défaut est `manage` : si vous ne dites rien, c'est lui.

### La liste blanche des adresses de retour

`return_url` doit être dans la **liste blanche du projet**, configurée dans la
console du Core. Sinon le Core répond `422`. C'est une protection contre
l/open redirect : sans elle, un lien de portail pourrait emmener le
propriétaire vers n'importe quel site.

> Le paquet n'envoie **jamais** `customer` ni `plan_code` : ces deux champs
> demandent des portées d'écriture (`customers:write`,
> `subscriptions:write`) que votre clé n'a pas, et le Core répondrait `403`.

## Le parcours complet, en un scenario

```php
// 1. Le client arrive (événement) — voir locataire.md
// 2. On ouvre le portail, il s'abonne
$session = app(Portal::class)->open('AE1234');

// 3. Il revient : l'événement « subscription » arrive
//    → vos droits sont invalidés, le cache est vidé

// 4. À chaque page sensible
if (! app(Entitlements::class)->get('AE1234')->allows('lessons')) {
    // 5. On lui montre la porte du portail
    return redirect(app(Portal::class)->open('AE1234')->url);
}

// 6. Il consomme
$decision = app(Usage::class)->consume('AE1234', 'lessons', $cléDuFait);

if (! $decision->isCounted()) {
    abort(402, 'Quota atteint.');
}
```

## Erreurs à connaître

| Type | HTTP | Cause | Ce qu'on fait |
|---|---|---|---|
| `validation-failed` | 422 | un champ manquant ou mal formé | corriger l'appel |
| `quota-exceeded` | 429 | le compteur dépasse la limite en `enforce` | vendre un supplement, ou passer en `record` |
| `entitlement-inactive` | 403 | le client n'a aucun contrat actif | le client doit s'abonner |
| `feature-not-included` | 403 | l'offre ne porte pas ce compteur | changez d'offre, ou ne comptez pas |
| `usage-underflow` | 422 | vous rendez plus que ce qui a été compté | corrigez la quantité |
| `idempotency-key-reused` | 409 | la clé a servi pour un autre client ou une autre quantité | une clé par fait mesuré |
| `customer-not-found` | 404 | la référence n'est pas connue du Core | vérifier la référence |
| `unreachable` | 0 | le Core n'a pas répondu | rejouer plus tard, **avec la même clé** |

Voir [Erreurs](erreurs.md) pour le catalogue complet.
