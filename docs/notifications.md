# Notifications

Ce que fait cette page : envoyer un WhatsApp (un texte, une image, un
fichier) par le Core, et savoir ce qu'il est devenu.

---

## Le parcours

```
1. Votre application demande l'envoi  → POST /v1/notifications/text
2. Le Core envoie le message à WhatsApp, et répond : sent / failed
3. WhatsApp livre (ou non) le message
4. Le Core pousse l'événement : notification.delivered / .read / .failed
5. Votre application réagit
```

**L'envoi est synchrone, la livraison ne l'est pas.** À l'étape 2, le
prestataire a **accepté** le message — `sent` — ou l'a **refusé** — `failed`,
avec la raison dans `error_message`. Ce que le Core ne sait pas encore, c'est
si WhatsApp l'a **remis** au destinataire : cela arrive par l'événement.

> ⚠️ **La réponse est un `201` même quand l'envoi a échoué.** Un `201` ne
> veut pas dire « envoyé » : lisez `status` et `errorMessage`. C'est
> `$notification->hasFailed()` qui dit la vérité.

```php
$notification = $notifications->text(...);

if ($notification->hasFailed()) {
    // rien n'a été envoyé : $notification->errorMessage dit pourquoi
}
```

Si vous devez savoir l'état **à un moment donné** — parce qu'un événement a
été perdu, ou parce que vous affichez un historique — relisez la
notification :

```php
use CoreAdapter\Services\Notifications;

$notifications = app(Notifications::class);

$notification = $notifications->status('ntf_01H…');

$notification->id;             // 'ntf_01H…'
$notification->channel;       // 'whatsapp'
$notification->status;        // 'queued' | 'sent' | 'delivered' | 'read' | 'failed'
$notification->contentType;   // 'text'
$notification->errorMessage;  // null, ou la raison de l'échec
```

## Envoyer un texte

```php
use CoreAdapter\Services\Notifications;

$notification = app(Notifications::class)->text(
    toPartyRef: 'AE1234',                    // VOTRE référence du destinataire
    phoneNumber: '+237690000000',            // en E.164
    text: 'Votre code de conduite est disponible.',
    clientReference: 'code-conduite:AE1234:2026-11-20',
);

$notification->id;             // 'ntf_01H…'
$notification->channel;       // 'whatsapp'
$notification->status;        // 'sent' | 'failed'
$notification->hasFailed();   // false
$notification->errorMessage;  // null
```

### Les deux règles du champ

**`to_party_ref` n'est jamais un numéro.** C'est **votre** référence du
destinataire — celle que le Core connaît pour le client. Le numéro voyage
dans `variables.phone_number`.

```
POST /v1/notifications/text
{
  "channel": "whatsapp",
  "to_party_ref": "AE1234",
  "variables": { "phone_number": "+237690000000" },
  "text": "Votre code de conduite est disponible.",
  "client_reference": "code-conduite:AE1234:2026-11-20"
}
```

**Le contenu est à vous.** Le Core n'a aucun modèle pré-enregistré : pas de
« confirmation de commande », pas de « rappel de rendez-vous ». Vous écrivez
le texte, le Core l'envoie. C'est votre marque, votre ton, vos données.

## Envoyer une image

```php
$notification = app(Notifications::class)->image(
    toPartyRef: 'AE1234',
    phoneNumber: '+237690000000',
    mediaUrl: 'https://cdn.exemple.com/permis/42.png',
    caption: 'Votre permis',
    filename: 'permis.png',
    mimetype: 'image/png',
);
```

> **L'URL doit être directement téléchargeable.** Le prestataire la récupère
> **lui-même**, sans authentification, sans protection anti-hotlink : une
> URL signée à durée courte suffit, une URL qui exige un cookie échoue.

## Envoyer un fichier

```php
$notification = app(Notifications::class)->file(
    toPartyRef: 'AE1234',
    phoneNumber: '+237690000000',
    mediaUrl: 'https://cdn.exemple.com/attestations/42.pdf',
    filename: 'attestation.pdf',
    mimetype: 'application/pdf',
    caption: 'Votre attestation de réussite',
);
```

## `client_reference` — votre protection contre le double envoi

Sur ces trois routes, **le Core ne porte pas de clé d'idempotence en
en-tête**. Le champ `client_reference`, dans le corps, joue ce rôle.

> **La même `client_reference` sur le même projet = un seul envoi.** La
> seconde requête ne renvoie pas un nouveau message : elle rend **la
> notification d'origine**, et rien n'est renvoyé au destinataire.

C'est exactement la garantie que donne l'en-tête `Idempotency-Key` sur les
paiements — avec une différence de forme, pas de fond.

```php
// Envoie, et rend la notification créée.
$notification = $notifications->text(
    toPartyRef: 'AE1234',
    phoneNumber: '+237690000000',
    text: 'Votre code de conduite est disponible.',
    clientReference: 'code-conduite:AE1234:2026-11-20',
);

// Rejoue (le job a été relancé) : rend la MÊME notification,
// et le destinataire ne reçoit rien de plus.
$notification = $notifications->text(
    toPartyRef: 'AE1234',
    phoneNumber: '+237690000000',
    text: 'Votre code de conduite est disponible.',
    clientReference: 'code-conduite:AE1234:2026-11-20',
);
```

### Les deux règles

**1. Mettez toujours une `client_reference`.** Sans elle, le Core génère un
UUID **par requête** : chaque appel est un envoi neuf. Un job rejoué enverrait
donc deux messages. La déduplication n'existe que si **vous** fournissez la
clé.

**2. Elle doit identifier le *fait*, pas la *requête*.** Comme pour les
paiements :

| ✅ | ❌ |
|---|---|
| `'code-conduite:AE1234:2026-11-20'` | `Str::uuid()` à chaque appel |
| `'rappel:rdv:2026-11-20:0042'` | `'notification'` |

Une `client_reference` **stable** par fait est ce qui rend l'envoi sûr dans
une file, où un job peut être relancé.

> Le message **n'est pas** renvoyé au destinataire en double, mais votre
> base peut, elle, enregistrer deux lignes. Dédupliquez de votre côté sur
> la `client_reference` (c'est une clé unique, elle se prête à une contrainte
> d'unicité).

> **Un écart, un seul** : le paquet envoie la `client_reference` telle quelle
> dans le corps, et le Core déduplique en amont de l'appel au prestataire. Ce
> n'est pas un rejeu de réponse HTTP mémorisé comme sur les paiements : le
> Core retrouve la notification existante en base. Le résultat est le même
> pour vous — un envoi, un effet.

## La session WhatsApp

Le Core envoie par le canal que **l'opérateur** a lié à votre projet. La
session se configure côté Core :

```dotenv
CORE_WHATSAPP_SESSION=ma-session
```

Si elle est vide ou inconnue, le Core répond :

```
422 channel-session-not-found
```

…et **rien n'est envoyé**. Les sessions sont créées et liées par
l'administrateur du Core, jamais par le client.

## Les événements

| Événement | Ce qu'il dit |
|---|---|
| `notification.delivered` | le message est arrivé chez le destinataire |
| `notification.read` | le destinataire l'a lu |
| `notification.failed` | l'envoi a échoué — le message porte la raison |

```php
use CoreAdapter\Laravel\Events\CoreEventReceived;

Event::listen(CoreEventReceived::class, function (CoreEventReceived $e): void {
    if (! $e->is('notification')) {
        return;
    }

    // $e->data['notification_id'] : la notification concernée
    // $e->data['channel']         : 'whatsapp'
    // $e->data['status']          : 'delivered' | 'read' | 'failed'
    // $e->data['reason']          : la raison, quand il y en a une
});
```

Pour un échec, relisez la notification : `error_message` porte la raison
précise (numéro invalide, session fermée, modèle refusé…).

## Le patron recommandé

```php
final class SendCodeDeConduite
{
    public function __invoke(string $reference, string $telephone): void
    {
        $notifications = app(Notifications::class);

        $notification = $notifications->text(
            toPartyRef: $reference,
            phoneNumber: $telephone,
            text: 'Votre code de conduite est disponible.',
            clientReference: (string) Str::uuid(),
        );

        if ($notification->hasFailed()) {
            Log::warning('Envoi WhatsApp refusé', [
                'reference' => $reference,
                'raison' => $notification->errorMessage,
            ]);

            return;
        }

        // L'état final viendra par l'événement.
    }
}
```

## Erreurs à connaître

| Type | HTTP | Cause | Ce qu'on fait |
|---|---|---|---|
| `channel-session-not-found` | 422 | aucune session WhatsApp liée, ou mauvais nom | le dire à l'opérateur |
| `validation-failed` | 422 | numéro mal formé, `media_url` absente… | corriger l'appel |
| `notification-not-found` | 404 | la notification n'existe pas | vérifier l'identifiant |
| `unreachable` | 0 | le Core n'a pas répondu | rejouer **avec la même** `client_reference` |

> Le rejoue, ici, se fait avec la **même** `client_reference` : c'est elle
> qui empêche le second envoi. Une clé neuve enverrait un second message.
