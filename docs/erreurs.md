# Erreurs

Ce que fait cette page : ce que le Core refuse, et **ce qu'on fait de chaque
refus**.

La règle qui gouverne toute cette page : *un refus se corrige, il ne se
rejoue pas*. Seuls les refus **transitoires** méritent un nouvel essai.

---

## L'exception unique

Toutes les routes lèvent la même exception :

```php
use CoreAdapter\CoreException;

try {
    $paiement = app(Payments::class)->paylink($commande, 'commande-42');
} catch (CoreException $e) {
    $e->status;         // 0 (pas de réponse), 401, 409, 422…
    $e->type;           // 'insufficient-funds', 'quota-exceeded'…
    $e->detail;         // le message du Core
    $e->body;           // le problem+json décodé
    $e->retryAfter;     // null, ou un nombre de secondes
    $e->correlationId;  // à mettre dans vos logs
}
```

| Méthode | Ce qu'elle dit |
|---|---|
| `$e->is('insufficient-funds')` | le refus porte ce slug |
| `$e->isTransient()` | le refus vaut un nouvel essai |
| `$e->status` | le code HTTP, ou `0` si le Core n'a pas répondu |

### Le slug

Le Core répond en RFC 7807 (`application/problem+json`) :

```json
{
  "type": "https://errors.core-system.internal/insufficient-funds",
  "title": "Insufficient funds",
  "status": 409,
  "detail": "…",
  "correlation_id": "…"
}
```

Le paquet ne garde que le **dernier segment** du `type` : c'est
`insufficient-funds`. Comparez avec `is()`, jamais avec une URL.

### Transitoire ou définitif ?

```php
public function isTransient(): bool
{
    return in_array($this->status, [0, 408, 429, 503, 504], true);
}
```

| Statut | Verdict | Ce qu'on fait |
|---|---|---|
| `0` | le Core n'a pas répondu | rejouer **avec la même clé** |
| `408` | délai dépassé | rejouer **avec la même clé** |
| `429` | trop de requêtes / quota atteint | rejouer **après** `Retry-After` |
| `503` | Core ou prestataire indisponible | rejouer **avec la même clé** |
| `504` | délai du Core dépassé | rejouer **avec la même clé** |
| `401`, `403` | clé ou portée | **corriger** la configuration |
| `404` | la ressource n'existe pas | **corriger** l'identifiant |
| `409` | conflit | **corriger** la clé ou l'état |
| `4xx` | tout le reste | **corriger** l'appel |
| `502` | le prestataire a refusé la demande | **corriger** ou proposer un autre canal |

> Un `502` (`provider-error`) est **définitif** : le prestataire a rejeté la
> requête, elle ne marchera pas en la répétant. Un `503`
> (`provider-unavailable`) est **transitoire** : le prestataire est tombé, il
> reviendra.

### La clé d'idempotence, dans tous les cas

Quand vous rejouez, rejouez avec **la même clé**. C'est le seul moyen
d'éviter le double effet. Une clé neuve, c'est un second paiement.

Exception : les **notifications**. Ces routes-là n'ont pas de clé en
en-tête, mais leur `client_reference` (dans le corps) joue le même rôle —
voir [Notifications](notifications.md#client_reference--votre-protection-contre-le-double-envoi).

---

## Le catalogue

### Authentification et autorisation

| Type | HTTP | Cause | Ce qu'on fait |
|---|---|---|---|
| `unauthenticated` | 401 | clé absente, inconnue ou révoquée | vérifiez `CORE_API_KEY` et l'environnement (staging ≠ production) |
| `forbidden` | 403 | la clé n'a pas la portée requise | demandez la portée à l'opérateur |
| `key-origin-not-allowed` | 403 | la clé est restreinte à des IP / un CIDR | le réseau de votre application n'y a pas droit |
| `project-suspended` | 403 | le projet est suspendu | le dire à l'opérateur |
| `project-archived` | 403 | le projet est archivé | idem — c'est définitif |

### Idempotence

| Type | HTTP | Cause | Ce qu'on fait |
|---|---|---|---|
| `idempotency-key-required` | 400 | l'en-tête `Idempotency-Key` manque | le paquet l'envoie toujours : c'est un bug chez vous |
| `idempotency-key-conflict` | 409 | la clé est déjà en vol, ou réutilisée pour un autre corps | attendez l'appel en cours, ou changez de clé **si c'est un autre fait** |
| `idempotency-key-reused` | 409 | la clé d'usage a servi pour un autre client ou une autre quantité | une clé par fait mesuré |

> Une clé d'idempotence est **par projet** : deux projets peuvent utiliser la
> même chaîne sans se marcher dessus.

### Validation

| Type | HTTP | Cause | Ce qu'on fait |
|---|---|---|---|
| `validation-failed` | 422 | un champ manque ou est mal formé | corrigez l'appel — **jamais** de rejoue |
| `payload-too-large` | 413 | le corps dépasse la limite | allégez (les métadonnées d'un transfert plafonnent à 4 Ko) |
| `json-too-deep` | 400 | le JSON est trop imbriqué | aplatissez |
| `return-url-not-allowed` | 422 | l'adresse de retour n'est pas dans la liste blanche | demandez à l'opérateur d'ajouter le domaine |
| `invalid-tenant-schema` | 422 | la forme du locataire déclarée est refusée | corrigez la déclaration |
| `invalid-if-match` | 400 | l'en-tête de version optimiste est absent ou faux | relisez puis rejouez avec la bonne version |

### Le locataire et les droits

| Type | HTTP | Cause | Ce qu'on fait |
|---|---|---|---|
| `customer-not-found` | 404 | le client n'existe pas chez le Core | vérifiez la référence |
| `customer-ref-required` | 422 | cet usage mesuré doit nommer son client | passez `customer_ref` |
| `customer-ref-invalid` | 422 | le client mesuré est inconnu | vérifiez la référence |
| `entitlement-inactive` | 403 | le client n'a aucun contrat actif | le client doit s'abonner |
| `feature-not-included` | 403 | l'offre ne porte pas ce compteur | changez d'offre, ou ne comptez pas |
| `quota-exceeded` | 429 | le quota de la fenêtre est atteint | **transitoire** : rejouez après `Retry-After` |
| `usage-underflow` | 422 | vous rendez plus que ce qui a été compté | corrigez la quantité |

### Paiements

| Type | HTTP | Cause | Ce qu'on fait |
|---|---|---|---|
| `payment-not-found` | 404 | paiement inconnu — **ou** d'un autre projet | vérifiez l'identifiant |
| `unsupported-currency` | 422 | le prestataire ne gère pas cette devise | changez de devise ou de canal |
| `unsupported-payment-purpose` | 422 | le règlement serait compté deux fois | corrigez la combinaison usage / portefeuille |
| `no-provider-for-channel` | 422 | aucun prestataire n'est branché sur ce canal | le dire à l'opérateur |
| `no-provider-application` | 422 | aucune application prestataire n'est enregistrée pour votre projet | l'opérateur doit en créer une |
| `provider-error` | 502 | le prestataire a **rejeté** la demande | **définitif** : proposez un autre canal |
| `provider-unavailable` | 503 | le prestataire est **tombé** | **transitoire** : rejouez avec la même clé |
| `payment-not-simulable` | 409 | ce paiement ne peut pas être simulé | il est déjà dans un état final |

> Le statut du paiement **n'est pas** une erreur : `failed`, `expired` et
> `canceled` sont des réponses à la **201** d'initiation. Ce sont des
> **statuts**, à lire dans l'événement — voir [Paiements](paiements.md#les-statuts).

### Notifications

| Type | HTTP | Cause | Ce qu'on fait |
|---|---|---|---|
| `notification-not-found` | 404 | la notification n'existe pas | vérifiez l'identifiant |
| `channel-session-not-found` | 422 | aucune session WhatsApp liée à ce nom | le dire à l'opérateur — **rien n'a été envoyé** |
| `missing-channel-session` | 422 | le canal n'a pas de session | idem |
| `channel-does-not-support-sessions` | 422 | ce canal n'a pas de session à gérer | — |
| `missing-recipient-phone-number` | 422 | le numéro du destinataire manque | mettez `variables.phone_number` |
| `quota-exceeded` | 429 | le quota de notifications est atteint | **transitoire** : rejouez après `Retry-After` |
| `entitlement-inactive` | 403 | le client n'a pas d'abonnement actif | le client doit s'abonner |

> L'envoi d'une notification répond **`201` même quand il a échoué**. Ce
> n'est pas une erreur HTTP : lisez `status` et `error_message` dans la
> réponse. Voir [Notifications](notifications.md).

### Portefeuilles

| Type | HTTP | Cause | Ce qu'on fait |
|---|---|---|---|
| `wallet-not-found` | 404 | le portefeuille n'existe pas — **ou** n'est pas à vous | vérifiez la référence |
| `insufficient-funds` | 409 | une source n'a pas assez | **définitif** : **aucune** jambe n'a bougé |
| `invalid-wallet-operation` | 422 | une jambe est invalide (une partie se payant elle-même, plus de 20 jambes, devises mêlées) | corrigez l'appel |

> `insufficient-funds` est un refus **définitif** : le Core n'a pas pris
> l'argent, et rien n'a bougé. Le rejouer tel quel rendrait le même refus.

### Portefeuilles et paiements — la même règle

Un portefeuille qui n'est pas à vous ne se distingue pas d'un portefeuille
inexistant : les deux répondent `404 wallet-not-found`. C'est volontaire —
l'API ne révèle pas l'existence d'une ressource d'un autre projet.

C'est aussi pour ça que `wallet_recipient_external_ref` **n'est pas
validé** au moment de l'initiation d'un paiement : la référence est résolue
au moment de la **confirmation**, et une partie inconnue est **créée** —
c'est ainsi qu'un portefeuille naît d'un premier encaissement. Un portefeuille
qui n'est pas à vous ne se révèle qu'à cet instant-là, comme un `404`.

---

## Les erreurs que vous ne verz-vous jamais

Le Core **ne publie pas** le vocabulaire du prestataire. Une erreur du
prestataire est traduite en un statut canonique, ou en exception d'argent :

| Ce que le prestataire dit | Ce que le Core rend |
|---|---|
| « SUCCESSFUL », « SUCCESS » | `succeeded` |
| « FAILED », « DECLINED » | `failed` |
| un code que le Core ne connaît pas | `failed` (jamais un statut inventé) |
| le montant confirmé ne vaut pas le montant demandé | `succeeded` **avec** `exception: amount_mismatch` |
| la devise confirmée n'est pas la devise demandée | `succeeded` **avec** `exception: currency_mismatch` |

Un paiement réglé avec une `exception` est réglé mais **pas imputé** : aucun
portefeuille n'est crédité, et l'argent part en **exception d'argent**, que
l'opérateur traite à la main. Ce n'est pas une erreur HTTP : c'est un
`201`/`200` normal, avec deux champs à regarder.

---

## Ce que le paquet ajoute

Le paquet ne change rien au contrat d'erreur. Il ajoute :

- **une seule exception** pour toutes les routes, au lieu d'une exception
  par code HTTP ;
- le **slug** extrait du `type` RFC 7807 ;
- `isTransient()`, qui décide seul du rejoue ;
- un **rejoue automatique** des seuls appels qui portent une clé
  d'idempotence, sur panne de connexion uniquement (3 essais, avec
  recul). Les autres ne sont jamais rejoués : ils pourraient faire la chose
  deux fois.

> Un `422` ne se rejoue **jamais**. Un `409 idempotency-key-conflict` non
> plus. Rejouer un refus, c'est refaire un refus.

## Ce qu'il faut mettre dans vos logs

```php
catch (CoreException $e) {
    Log::warning('Refus du Core', [
        'status' => $e->status,
        'type' => $e->type,
        'detail' => $e->detail,
        'correlation_id' => $e->correlationId,
        'retry_after' => $e->retryAfter,
    ]);

    throw $e;
}
```

Le `correlation_id` est la **clé de contact** avec l'opérateur : il apparaît
dans les journaux du Core, et permet de retrouver la requête exacte. Ne le
jetez pas.

---

*Les `correlation_id` sont l'interface avec l'opérateur : un refus qui
n'est pas journalisé est un refus qu'on ne pourra pas expliquer.*
