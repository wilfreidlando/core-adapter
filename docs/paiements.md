# Paiements

Ce que fait cette page : encaisser, et **savoir quand l'argent est arrivé**.

C'est la page la plus importante du dossier : un encaissement a trois issues
possibles (réussi, échoué, expiré) et une seule question à poser — *« c'est
fait ? »*. Elle a deux réponses possibles : l'**événement** (le canal
fiable) et la **relecture** (le filet de sécurité).

---

## Les deux flux

| Flux | Route | Qui mène l'interface | Le payeur |
|---|---|---|---|
| `paylink` | `POST /v1/payments/paylink` | le Core, sur une page hébergée | redirigé vers `payment_url` |
| `payin` | `POST /v1/payments/payin` | **vous** | reste dans votre application |

Les deux rendent la même chose : un objet `Payment`.

### Le flux `payin`, en une phrase de plus

En `payin`, **c'est votre application qui mène l'interface** : le payeur
reçoit une invite sur son téléphone, et vous lui affichez ce qu'il doit
faire. Le Core ne renvoie pas de `payment_url` — il renvoie un `action_hint`
(le code USSD à composer, si l'invite ne s'ouvre pas).

```php
$paiement = app(Payments::class)->payin([
    'channel' => 'mtn_momo',
    'amount' => 5000,
    'currency' => 'XAF',
    'reason' => 'Abonnement mars 2026',
    'customer_phone_number' => '+237690000000',
    'callback_url' => 'https://mon-app.exemple.com/webhooks/paiement',
], 'commande-2026-0042');

$paiement->actionHint;   // 'redirect' | 'prompt' | null
$paiement->paymentUrl;   // null en payin
```

> ⚠️ **Le `payin` attend 15 à 20 secondes.** Le prestataire ne répond
> qu'une fois l'opérateur du payeur interrogé. Le délai HTTP du paquet doit
> donc être **d'au moins 60 secondes** en payin — see
> [Installation](installation.md#les-trois-clés). Un délai de 30 s coupe
> l'appel à mi-chemin : le Core, lui, aura déjà enregistré le paiement, et
> votre rejoue (avec la **même** clé) le retrouvera.

## Encaisser

```php
use CoreAdapter\Services\Payments;

$paiement = app(Payments::class)->paylink([
    'channel' => 'mtn_momo',            // mtn_momo | orange_money | card
    'amount' => 5000,                   // ENTIER, plus petite unité
    'currency' => 'XAF',                // ISO 4217, 3 lettres
    'reason' => 'Abonnement mars 2026', // 255 caractères max
    'customer_phone_number' => '+237690000000',
    'customer_name' => 'Marie Dupont',
    'customer_email' => 'marie@exemple.com',
    'customer_lang' => 'fr',            // 'fr' | 'en'
    'return_url' => 'https://mon-app.exemple.com/paiement/retour',
    'callback_url' => 'https://mon-app.exemple.com/webhooks/paiement',
    'wallet_recipient_external_ref' => null,
], 'commande-2026-0042');              // ← la clé d'idempotence
```

Le retour :

```php
$paiement->id;              // '0199…' (un UUID)
$paiement->status;          // 'pending'
$paiement->isFinal;         // false
$paiement->amount;          // 5000
$paiement->currency;        // 'XAF'
$paiement->channel;         // 'mtn_momo'
$paiement->paymentUrl;      // l'URL à envoyer au payeur (paylink)
$paiement->actionHint;      // 'redirect' | 'prompt' | null
$paiement->callbackSecret;  // le secret du callback — À SAUVER ICI
$paiement->failureReason;    // null tant que ce n'est pas final
$paiement->createdAt;
$paiement->updatedAt;
```

### Les règles du corps

| Champ | Règle |
|---|---|
| `channel` | **`mtn_momo`, `orange_money` ou `card`**. Jamais le nom d'un prestataire : le Core route, vous ne choisissez pas le prestataire. |
| `amount` | un **entier** en plus petite unité. `5000` XAF = 5 000 FCFA ; `100` EUR = 1 €. Jamais `50.00`. Jamais `0` ni négatif. |
| `currency` | ISO 4217, 3 lettres. Le prestataire doit la supporter, sinon `422 unsupported-currency`. |
| `reason` | obligatoire, 255 caractères max. C'est le libellé **vu par le payeur**. Il n'est pas renvoyé dans la réponse : gardez-en une copie. |
| `customer_phone_number` | E.164 (`+237690000000`). Indispensable sur les canaux mobile money : les invites USSD et les OTP doivent arriver au bon numéro. |
| `customer_lang` | `fr` ou `en` seulement — `fr-CM` est **refusé**. |
| `return_url` | redirection **navigateur**, `https://` obligatoire. *Best-effort* : elle peut ne jamais arriver (onglet fermé, JS désactivé). |
| `callback_url` | notification **serveur à serveur**. C'est le canal fiable. `http://` ou `https://`. |
| `wallet_recipient_external_ref` | le portefeuille à créditer **si** le paiement réussit. Absent → le paiement est un encaissement **pur** : l'argent est détenu, aucun portefeuille n'est crédité. |

### La clé d'idempotence — obligatoire

Les trois routes d'encaissement exigent l'en-tête `Idempotency-Key`. Le
paquet l'exige aussi : `postIdempotent()` **refuse** un corps qui en
porterait déjà une.

```php
// ✅ ce que le paquet envoie
POST /v1/payments/paylink
Idempotency-Key: commande-2026-0042
```

La clé doit identifier **le fait**, dans **votre** métier :

| ✅ | ❌ |
|---|---|
| `'commande-2026-0042'` | `uniqid()` |
| `'abonnement:AE1234:2026-03'` | `'payment-' . time()` |

Ce que fait le Core avec la clé :

| Situation | Réponse |
|---|---|
| clé inconnue | la route s'exécute, la réponse est **mémorisée** |
| clé connue, **même** corps | la réponse mémorisée est **rejouée**, la route ne s'exécute pas |
| clé connue, **corps différent** | `409 idempotency-key-conflict` |
| clé connue, **requête encore en vol** | `409 idempotency-key-conflict` (attendez) |
| la requête a **échoué** | la clé est **libérée** : vous pouvez réessayer avec |

> ⚠️ **Le secret du callback n'est renvoyé qu'une fois.** La réponse
> mémorisée ne contient **pas** `callback_secret` (un secret montré une fois
> n'est jamais stocké). Si votre job rejoue l'appel, il ne le reverra pas :
> **sauvegardez-le à la première réponse**, dans votre base, avec votre
> commande.

## `return_url` ou `callback_url` ?

**Les deux font des choses différentes.**

| | `return_url` | `callback_url` |
|---|---|---|
| Qui est appelé | le navigateur du payeur | votre serveur |
| Fiabilité | best-effort : peut ne jamais arriver | fiable |
| Ce qu'il faut en faire | rediriger le payeur, afficher « merci » | **mettre à jour votre commande** |
| Signature | aucune | HMAC-SHA256 (`X-Signature`, `X-Timestamp`) |

**La règle : l'état de votre commande se met à jour sur le `callback_url`, ou
sur l'événement ; jamais sur le `return_url`.** Un payeur qui ferme l'onglet
avant la redirection ne doit pas laisser votre commande en suspens.

Le `callback_url` reçoit **exactement** la même enveloppe qu'un événement :

```json
{
  "id": "0199…",
  "type": "payment.status_changed",
  "data": {
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
}
```

et il est signé comme un événement : `HMAC-SHA256(timestamp + "." + corps)`,
en hexadécimal minuscule, dans `X-Signature`, avec le timestamp dans
`X-Timestamp`. **Le secret, c'est le `callback_secret` de la réponse**, pas
le secret du webhook.

> **Le callback n'est pas instantané.** Le Core relaie ses événements une
> fois **par minute** : le callback part donc **jusqu'à une minute** après
> le changement d'état. Ce n'est pas un retard de votre côté — et c'est
> pour ça que la relecture existe.

### Les rejoues du callback

Le callback est une livraison à part entière, avec ses propres rejoues :

| Tentatives | Délais |
|---|---|
| 8 au maximum | 30 s, 60 s, 2 min, 5 min, 15 min, 30 min, 1 h |

Un `4xx` autre que `408`/`429` est **définitif** : le callback est
abandonné **immédiatement**, sans lettre morte (contrairement au plan des
webhooks).

> ⚠️ **Le callback n'a pas de lettre morte.** S'il est abandonné, personne
> ne vous le dira : c'est votre job qui échoue, en silence. C'est la raison
> de plus pour garder `GET /v1/payments/{id}` comme filet.

> `reference` porte **votre** clé d'idempotence : c'est par là que vous
> rapprochez l'événement de votre commande. `payment_id` est l'identifiant
> Core du paiement.

> ⚠️ **Un paiement qui a un `callback_url` ne passe pas par vos points
> d'entrée webhook.** Pour ce paiement-là, l'événement est delivered **au
> `callback_url` et à lui seul** : la diffusion vers les endpoints
> enregistrés est écartée. C'est volontaire — un paiement a un seul
> destinataire déclaré.
>
> Donc : si vous utilisez `callback_url`, **`/webhooks/core` ne recevra pas
> vos `payment.status_changed`**. Traitez-les sur la route du callback, et
> sur la route du webhook pour les *autres* familles d'événements. Si vous
> préférez un seul point d'entrée, **n'utilisez pas `callback_url`** : le
> webhook diffusera alors l'événement normalement — mais il vous faut alors
> gérer le secret de signature global, et non le `callback_secret` d'un
> paiement.

### Vérifier le callback avec le paquet

Le `Receiver` du paquet vérifie exactement ce schéma — il suffit de lui
donner le secret du callback :

```php
use CoreAdapter\Webhooks\Receiver;
use CoreAdapter\Webhooks\Signature;

$receiver = new Receiver(new Signature(300), $eventStore, $callbackSecret);

$event = $receiver->accept($rawBody, $headers);   // InvalidSignatureException
```

## Comment savoir que c'est fait ?

C'est **la** question. Deux réponses, et il faut les deux.

### 1. L'événement — le canal fiable

```php
use CoreAdapter\Laravel\Events\CoreEventReceived;

Event::listen(CoreEventReceived::class, function (CoreEventReceived $e): void {
    if ($e->type !== 'payment.status_changed') {
        return;
    }

    $data = $e->data;

    $data['payment_id'];      // '0199…'
    $data['reference'];       // 'commande-2026-0042' — VOTRE clé d'idempotence
    $data['status'];          // 'succeeded'
    $data['is_final'];        // true
    $data['amount'];          // 5000
    $data['failure_reason'];  // null, ou la raison
    $data['confirmed_amount'];// ce que le prestataire a confirmé
    $data['exception'];       // 'amount_mismatch' | 'currency_mismatch' | null
});
```

**Traitez l'événement, pas l'URL de retour.** Le Core le livre **au moins une
fois** : dédupliquez sur `$e->id`, et traitez la commande **une seule fois**
(idempotence côté base, sur `reference`).

### 2. La relecture — le filet de sécurité

Quand faut-il relire ? Quand vous **devez** savoir tout de suite, sans
attendre l'événement : l'utilisateur revient sur sa page de paiement, vous
voulez afficher « en cours de traitement », ou vous soupçonnez qu'un
événement a été perdu.

```php
use CoreAdapter\Services\Payments;

$paiement = app(Payments::class)->get('0199…');

$paiement->status;    // 'succeeded'
$paiement->isFinal;   // true
```

`GET /v1/payments/{paymentId}` — portée `payments:read`.

> **Ce que cette lecture est, et ce qu'elle n'est pas.** Elle rend **l'état
> que le Core connaît** — pas une interrogation en direct du prestataire.
> Le Core ne reconcile pas à la demande : c'est lui qui rattrape les
> webhooks manqués, tout seul (tâche planifiée, une fois par minute, plus
> les réconciliations déclenchées par un opérateur).
>
> Concrètement : si le prestataire a encaissé mais que son webhook n'est
> jamais arrivé, le Core le découvre **à sa rythme**. Votre relecture
> rendra `pending` jusque-là. **Ce n'est pas à vous de réconcilier** : c'est
> le rôle du Core, et il le fait mieux que vous (il connaît le prestataire,
> ses règles, ses quotas).
>
> La conséquence pratique : **relisez pour l'affichage, pas pour décider.**
> Ne déclenchez jamais « le payeur a payé → je livre » sur une relecture
> toute seule : préférez l'événement, ou attendez que `is_final` soit vrai.

### Le patron de relecture — avec un intervalle qui respire

```php
use CoreAdapter\Services\Payments;

final class RelitLePaiement
{
    /** Les délais, en secondes, entre deux relectures. */
    private const INTERVALLES = [3, 5, 10, 20, 30];

    public function __construct(private Payments $paiements) {}

    public function __invoke(string $paymentId): Payment
    {
        foreach (self::INTERVALLES as $attente) {
            sleep($attente);

            $paiement = $this->paiements->get($paymentId);

            if ($paiement->isFinal) {
                return $paiement;
            }
        }

        // Ce n'est pas final : le paiement est peut-être encore en cours
        // chez le prestataire. On rend l'état courant ; l'événement, lui,
        // arrivera.
        return $paiement;
    }
}
```

En pratique :

- **3 à 5 relectures** suffisent dans 95 % des cas ;
- n'allez **pas** au-delà d'une trentaine de secondes d'attente cumulée —
  au-delà, c'est un job de fond, pas une requête HTTP ;
- **jamais** de boucle infinie : le statut peut être `expired` ;
- en production, la relecture est un **job en file**, jamais un `sleep()`
  dans une requête web.

### La décision, en une phrase

| Question | Réponse |
|---|---|
| Le payeur a-t-il payé ? | l'**événement** `payment.status_changed` avec `status = succeeded` |
| Et si l'événement est perdu ? | la **relecture** `GET /v1/payments/{id}`, et `is_final` |
| Puis-je livrer la commande sur une relecture ? | seulement si `is_final` **et** `status = succeeded` — mais préférez l'événement |
| Puis-je le faire sur le `return_url` ? | **jamais** |

## Les statuts

Sept statuts canoniques, et **rien d'autre** : le vocabulaire du prestataire
ne sort jamais du Core.

| Statut | Ce que ça veut dire | Final ? | Que faire |
|---|---|---|---|
| `pending` | accepté par le prestataire, le client n'a pas encore payé | non | attendre |
| `processing` | le prestataire confirme que le client a payé, en attente de règlement | non | attendre |
| `succeeded` | réglé. L'argent est là. | **oui** | livrer |
| `failed` | échec **définitif** (réseau, requête invalide, prestataire indisponible au-delà du budget de rejoues) | **oui** | ne pas réessayer ; proposer une autre tentative |
| `expired` | la fenêtre du prestataire s'est refermée avant le paiement | **oui** | **on peut repartir** : c'est une nouvelle tentative, pas un échec |
| `refunded` | l'argent a été rendu au client après un `succeeded` | **oui** | (le seul événement possible après `succeeded`) |
| `canceled` | annulé par le client **avant** de payer | **oui** | ce n'est pas un échec : ne dégradez pas votre taux de conversion |

La distinction `failed` / `expired` / `canceled` est volontaire :
seul `failed` est un échec. `expired` et `canceled` autorisent une nouvelle
tentative, et `canceled` ne doit pas compter comme un échec dans vos
statistiques.

> **Les transitions sont verrouillées.** `pending → processing → succeeded`,
> `succeeded → refunded`, et rien d'autre. Un `pending` tardif après un
> `succeeded` est **refusé** : un webhook rejoué ne fait jamais reculer un
> paiement réglé. C'est votre filet : le jeu d'événements peut être
> dupliqué, il ne peut pas désordre.

### Les exceptions d'encaissement

Deux champs à regarder sur un `succeeded` :

- `confirmed_amount` — ce que le prestataire a **confirmé** avoir collecté.
  `null` quand la source n'a pas pu confirmer un montant.
- `exception` — `amount_mismatch` ou `currency_mismatch` quand le
  prestataire a confirmé **autre chose** que ce qui était demandé. Le
  paiement est alors réglé mais **pas imputé** : aucun portefeuille n'est
  crédité, et l'argent part en exception d'argent.

Ne lisez pas `exception` comme une liste fermée : une valeur inconnue
s'ignore.

## Créditer un portefeuille — ou pas

**Un paiement réussi ne crédite aucun portefeuille.** C'est une décision
écrite (ADR-0037) : le crédit est une **instruction explicite**, donnée au
moment de l'initiation.

```php
$paiement = app(Payments::class)->paylink([
    'channel' => 'mtn_momo',
    'amount' => 50000,
    'currency' => 'XAF',
    'reason' => 'Provision du compte',
    'wallet_recipient_external_ref' => 'AE1234',   // ← l'instruction
], 'commande-2026-0042');
```

- **avec** `wallet_recipient_external_ref` : le portefeuille `AE1234` est
  crédité **dans la même transaction** que la confirmation du paiement. Il
  n'y a pas de fenêtre, pas d'appel supplémentaire, pas de course.
- **sans** : le paiement est un encaissement pur. L'argent est détenu par le
  Core, aucun portefeuille n'est crédité.

Si la référence désigne un portefeuille qui n'est **pas** à vous, le crédit
échoue — et l'échec n'apparaît **qu'à la confirmation**, pas à
l'initiation. Le Core répond alors `404 wallet-not-found` : un portefeuille
d'un autre projet est indiscernable d'un portefeuille inexistant, par
volonté.

## Erreurs à connaître

| Type | HTTP | Cause | Ce qu'on fait |
|---|---|---|---|
| `idempotency-key-required` | 400 | l'en-tête `Idempotency-Key` manque | le paquet l'envoie toujours : ne le perdez pas |
| `idempotency-key-conflict` | 409 | la clé est déjà en vol, ou réutilisée pour un autre corps | attendez, ou changez de clé si c'est un autre fait |
| `validation-failed` | 422 | un champ manquant ou mal formé | corrigez l'appel |
| `unsupported-currency` | 422 | le prestataire ne gère pas cette devise | changez de devise ou de canal |
| `wallet-project-mismatch` | 422 | le portefeuille visé n'est pas à vous | corrigez la référence |
| `payment-not-found` | 404 | paiement inconnu **ou** d'un autre projet | vérifiez l'identifiant |
| `provider-error` | 502 | le prestataire a **rejeté** la demande | refus **définitif** : proposez un autre canal |
| `provider-unavailable` | 503 | le prestataire est **tombé** | rejouez avec la **même** clé |
| `unreachable` | 0 | le Core n'a pas répondu | rejouez avec la **même** clé |

> Seul `unreachable` (et les statuts 408/429/503/504) vaut la peine d'être
> rejoué — et **toujours avec la même clé**. Un `422` ne se rejoue pas : il se
> corrige.

Le catalogue complet est dans [Erreurs](erreurs.md).

## Le parcours complet

```php
// 1. Le client achète.
$commande = Commandes::findOrFail($commandeId);

// 2. On encaisse — la clé d'idempotence est la commande.
try {
    $paiement = app(Payments::class)->paylink([
        'channel' => 'mtn_momo',
        'amount' => $commande->montant_minor,
        'currency' => $commande->devise,
        'reason' => 'Commande '.$commande->reference,
        'customer_phone_number' => $client->telephone,
        'callback_url' => route('core.paiement.callback'),
        'wallet_recipient_external_ref' => $client->code,   // si on provisionne
    ], $commande->reference);
} catch (CoreException $e) {
    if ($e->isTransient()) {
        // Le job part en file et rejoue avec la MÊME clé.
        throw $e;
    }

    $commande->markFailed($e->detail);

    return;
}

// 3. On sauvegarde le secret du callback — AU PLUS UNE FOIS.
$commande->saveCallbackSecret($paiement->callbackSecret);

// 4. On envoie le payeur sur la page hébergée.
return redirect($paiement->paymentUrl);
```

Puis, dans l'écouteur :

```php
Event::listen(CoreEventReceived::class, function (CoreEventReceived $e): void {
    if ($e->type !== 'payment.status_changed') {
        return;
    }

    $data = $e->data;

    // La commande, retrouvée par VOTRE clé d'idempotence.
    $commande = Commandes::where('reference', $data['reference'])->first();

    if ($commande === null || $commande->payee) {
        return;   // événement rejoué : on ne refait rien
    }

    match ($data['status']) {
        'succeeded' => $commande->markPaid(),
        'failed', 'expired', 'canceled' => $commande->markFailed($data['failure_reason']),
        default => null,   // pending, processing : on attend
    };
});
```

## Ce que le paquet ne fait pas

- Il ne **réconcilie** pas : relire un paiement, c'est relire l'état du
  Core. Le rattrapage des webhooks manqués est le travail du Core, et il le
  fait tout seul.
- Il ne **simule** pas : `POST /v1/payments/{id}/simulate` n'existe qu'en
  staging, et n'est pas exposé par le paquet.
- Il ne **liste** pas : `GET /v1/payments` (pagination par curseur) existe
  dans le Core, mais n'est pas exposé par le paquet. Utilisez
  `Payments::get()` pour un paiement connu.
- Il ne **rembourse** pas : `refunded` est un statut que le Core constate ;
  le geste de remboursement appartient à l'opérateur.
