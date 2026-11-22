# Le locataire

Ce que fait cette page : comprendre **qui** est votre locataire, comment le
Core vous le fait connaître, et comment le créer chez vous sans jamais
écrire un client chez le Core.

---

## Le parcours, en six lignes

```
1. L'opérateur crée un client dans la console du Core.
2. Le Core pousse l'événement « customer.created » vers POST /webhooks/core.
3. Le paquet vérifie la signature, dédouble, et émet CoreEventReceived.
4. L'écouteur du paquet confie le travail à une file : ProvisionsTenant.
5. Le worker lit le client chez le Core (GET /v1/customers/{ref}).
6. Votre fabrique crée votre locataire, avec les valeurs déclarées.
```

Deux choses à retenir de ce parcours :

- **Le Core crée le client.** Jamais vous. Votre application ne possède que
  sa **copie locale**, qui n'est qu'une projection du client du Core.
- **Le travail part en file.** L'écouteur ne fait aucun appel réseau : le
  Core attend une réponse en moins de dix secondes, et lire un client puis
  écrire une ligne ne tient pas dans ce délai.

## La référence : votre clé unique

Le Core connaît votre client par **votre** référence (`external_ref`) : c'est
votre code d'auto-école, votre numéro de dossier, votre `slug`. Cette
référence doit respecter un **motif** — c'est votre déclaration qui le dit,
et c'est le Core qui l'applique.

```php
final readonly class MaDeclaration implements TenantDeclaration
{
    public function cleUnique(): array
    {
        return [
            'attribut' => 'code',
            'motif' => '/^AE[0-9]{4}(?:[0-9]{2}){0,2}$/',
        ];
    }

    public function champs(): array
    {
        return [
            ['cle' => 'localisation', 'source' => 'declared.localisation', 'requis' => true,  'libelle' => 'Ville',            'type' => 'string', 'longueur' => 80],
            ['cle' => 'annee',      'source' => 'declared.annee',      'requis' => false, 'libelle' => 'Année de création', 'type' => 'integer'],
            ['cle' => 'email',      'source' => 'core_identity.email', 'requis' => true,  'libelle' => 'E-mail',            'type' => 'email'],
        ];
    }
}
```

## Les sources : d'où vient la valeur

Une source dit **où** le paquet doit prendre la valeur. Deux familles :

| Source | La valeur vient de… |
|---|---|
| `core_identity.display_name` | la raison sociale tenue par le Core |
| `core_identity.email` | l'e-mail du client |
| `core_identity.phone` | le téléphone du client |
| `core_identity.locale` | la langue du client (`fr` par défaut) |
| `declared.<clé>` | les attributs que le projet a déclarés au Core |

> **La règle à ne pas manquer.** Une source `declared.<x>` doit nommer la
> **clé du champ** `cle`. Le Core stocke la valeur sous la clé du champ, et le
> paquet la relit sous le **suffixe** de la source : les deux ne se
> rencontrent que si la source nomme la clé.
>
> `['cle' => 'localisation', 'source' => 'declared.localisation']` → correct.
> `['cle' => 'localisation', 'source' => 'declared.emplacement']` → **refusé**
> (`422 invalid-tenant-schema`), et la valeur resterait introuvable.

Le paquet **vérifie** cette règle avant l'envoi : une source mal nommée est
refusée **localement**, avec un message qui dit quoi faire, plutôt qu'un `422`
muet du Core.

## Dire sa forme au Core

Le Core ne devine pas votre modèle métier. Votre application **propose** une
forme — sa clé unique, son motif, ses clés — et **l'opérateur accepte**,
dans la console du Core.

```bash
php artisan core:declare-tenant-schema
```

```
Déclaration proposée au projet auto-ecole (version 4) : elle attend la lecture
de l'opérateur, dans la console du Core.
```

Tant que l'opérateur n'a pas accepté, **aucun** attribut déclaré n'est
collecté : le formulaire du Core ne montre pas vos champs, et l'application
ne peut ni les voir, ni les dire.

### Le motif : deux formats, une seule chaîne

Votre motif est un motif `preg` — il sert à valider la référence **chez
vous**, avec `preg_match()`. Le Core n'accepte qu'un motif **nu**, dans
l'alphabet d'une référence (`A-Za-z0-9._:-`), sans délimiteurs, sans `\d`, sans
groupes étendus, avec `^` en tête et `$` en fin.

Le paquet **traduit** avant d'envoyer :

| Votre motif (applicatif) | Ce qui part au Core |
|---|---|
| `/^AE[0-9]{4}(?:[0-9]{2}){0,2}$/` | `^AE[0-9]{4}([0-9]{2}){0,2}$` |
| `/^AE[0-9]{4,8}$/i` | `^AE[0-9]{4,8}$` |
| `/\AAE[0-9]{4}\z/` | `^AE[0-9]{4}$` |
| `/^\d+$/` | `^[0-9]+$` |

Votre motif **applicatif** n'est jamais modifié : c'est lui que la validation
locale utilise.

Ce qui n'a **pas** de traduction honnête est refusé **localement**, avant
l'envoi, avec un message qui dit quoi faire : le joker `.`, les classes
négatives `[^…]`, les espaces `\s`, les groupes étendus `(?i)`, et tout
caractère hors de l'alphabet.

```
Le motif « /^AE.+$/ » ne peut pas être envoyé au Core : le joker « . » est
interdit : il matcherait un caractère hors de l'alphabet du Core. Le Core
n'accepte qu'un motif **nu**, dans l'alphabet A-Za-z0-9._:-, avec `^` en tête
et `$` en fin ; le motif de l'application, lui, reste intact pour la
validation locale de la référence.
```

### Les types que le Core connaît

Le Core rend vos champs avec l'un de ces dix types, et la console en tire un
contrôle HTML :

| Type | Contrôle dans la console du Core |
|---|---|
| `string` | une ligne de texte |
| `text` | une zone de texte |
| `integer` | un nombre |
| `decimal` | un nombre |
| `boolean` | **une case à cocher** |
| `date` | une date |
| `datetime` | une date et une heure |
| `email` | un e-mail |
| `phone` | un téléphone |
| `url` | une adresse web |

Une case à cocher **dit toujours** sa valeur : un champ caché porte le « 0 »,
la case porte le « 1 ». Une case décochée est un « non », jamais un silence.

### Relire la forme en vigueur

```php
use CoreAdapter\Services\TenantSchemas;

$schemas = app(TenantSchemas::class);

$enForce = $schemas->accepted('auto-ecole');   // ?array
$enAttente = $schemas->pending('auto-ecole');  // ?array
```

`core:check` fait la même chose en une ligne.

> Une proposition **identique** à une forme déjà connue ne crée pas une
> version de plus : le Core répond `200` et rend la forme existante. Les
> versions ne s'empilent pas sur la même forme.

## La fabrique : votre locataire

C'est **tout** ce que le paquet demande à votre application : trois
méthodes.

```php
use CoreAdapter\Laravel\Tenant\TenantFactory;

final class MonLocataire implements TenantFactory
{
    public function trouver(string $reference): ?object
    {
        return Locataire::where('code', $reference)->first();
    }

    public function lier(object $locataire, string $reference): void
    {
        // Le Core vient de confirmer qu'un client correspond à cet
        // établissement : on y rattache le compte local.
        $locataire->core_reference = $reference;
        $locataire->save();
    }

    public function creer(string $cle, array $attributs): object
    {
        // $cle     : la clé unique (votre 'code')
        // $attributs : la valeur de chaque clé déclarée
        //
        // L'opération doit être atomique et idempotente : le même appel ne
        // doit pas créer deux fois le même locataire.
        $locataire = Locataire::create([
            'code' => $cle,
            'localisation' => $attributs['localisation'] ?? null,
            'annee' => $attributs['annee'] ?? null,
            'email' => $attributs['email'] ?? null,
        ]);

        return $locataire;
    }
}
```

Le paquet se charge de tout le reste : recevoir l'événement, vérifier la
référence contre votre motif, lire le client chez le Core, mapper les
valeurs, rejouer en cas de panne, ne pas créer deux fois le même locataire.

### Ce que le paquet fait de votre fabrique

| Situation | Ce qui se passe |
|---|---|
| Le locataire n'existe pas | `creer($cle, $attributs)` est appelé, avec les valeurs mappées |
| Le locataire existe déjà | `lier($locataire, $reference)` est appelé, et **rien d'autre** |
| La référence ne respecte pas votre motif | rien n'est créé, un avertissement est journalisé |
| Le Core ne connaît pas ce client | rien n'est créé, un avertissement est journalisé |
| Les données sont inexploitables (pas de raison sociale) | `creer()` lève `DomainException` : la tâche s'arrête, un avertissement est journalisé |

Un échec de **base** est rejoué (5 fois, 30 s d'intervalle) ; un échec de
**données** ne l'est pas : il ne se corrigera pas tout seul.

## La resynchronisation — facultative

Les valeurs déclarées apparaissent souvent **après** la création du client :
l'opérateur renseigne la localisation ou l'année plus tard. Votre locataire
existe déjà — et il ne voit jamais ces valeurs.

Si vous voulez les voir, implémentez `SynchronizesTenant` **en plus** de
`TenantFactory` :

```php
use CoreAdapter\Laravel\Tenant\SynchronizesTenant;
use CoreAdapter\Dto\Customer;

final class MonLocataire implements TenantFactory, SynchronizesTenant
{
    public function synchroniser(object $locataire, string $reference, Customer $client): void
    {
        // Ne remplir que ce qui manque : le Core reste le maître.
        // $client->declaredAttributes['localisation'] porte la valeur
        // déclarée sous la clé « localisation ».
        $locataire->localisation ??= $client->declaredAttributes['localisation'] ?? null;
        $locataire->save();
    }
}
```

Le client est relu **au Core** : la source de vérité reste le Core, votre
application ne fait que recopier ce qu'il dit.

Une application qui n'implémente pas cette interface **ne change rien** :
c'est le paquet qui décide, et il ne demande rien de plus.

## Vos locataires existent déjà

Une application qui existait avant le Core a déjà ses locataires — et le Core
ne les connaît pas. Il faut qu'il les **reconnaisse**, sans que votre
application n'écrive jamais un client chez le Core.

### Le chemin ordinaire : la console du Core

Depuis la version 1.7 du paquet, **l'opérateur n'a plus rien à faire** :

```
1. Dans la console du Core, il ouvre la page « Application cliente ».
2. Il clique sur « Créer les clients depuis l'application ».
3. Le Core vient chercher votre liste (POST /core/tenants), montre un aperçu
   — combien seront créés, combien sont déjà connus, combien seront refusés —
   et il clique sur « Confirmer ».
4. Le Core crée les clients et pousse « customer.created ».
5. Votre application, qui connaît déjà ces locataires, les LIE
   (trouver() renvoie le locataire, lier() est appelé) au lieu d'en créer
   un second.
```

Pour cela, votre application implémente `TenantLister` :

```php
use CoreAdapter\Laravel\Tenant\TenantLister;

final class LocatairesAutoEcole implements TenantLister
{
    public function tenants(): iterable
    {
        foreach (Locataire::withoutGlobalScopes()->cursor() as $locataire) {
            yield [
                'external_ref' => $locataire->code,
                'display_name' => $locataire->nom,
                'email' => $locataire->email,
                'phone' => $locataire->telephone,
                // Et vos valeurs déclarées, sous le nom de vos clés :
                'localisation' => $locataire->localisation,
            ];
        }
    }
}
```

**Les valeurs déclarées comptent.** Sans elles, le Core ne créerait vos
clients qu'avec l'identité — et votre application recevrait des clients à
moitié renseignés.

### Le chemin de secours : le fichier

Le fichier reste possible : pour un lot hors bande, ou quand l'application
est injoignable au moment du branchement.

```
1. php artisan core:export-tenants > tenants.csv
2. L'opérateur importe le fichier dans la console du Core.
```

Le fichier porte **les colonnes de la forme que vous avez déclarée** : la
référence, les colonnes du Core que votre déclaration ne remplace pas, puis
vos clés, dans l'ordre de la déclaration. Le Core refuse un fichier dont
l'en-tête ne porte pas exactement ces colonnes — l'export les écrit donc, à
votre place.

Les deux chemins aboutissent au **même** endroit : les clients sont créés par
le Core, poussés, et liés chez vous. Aucun doublon possible dans les deux cas.

## Lire l'identité d'un client

Quand vous avez besoin de l'e-mail ou du téléphone d'un client, vous le
**demandez** — la charge utile d'un événement n'en porte pas :

```php
use CoreAdapter\Services\Customers;

$client = app(Customers::class)->get('AE1234');

$client->externalRef;           // 'AE1234'
$client->displayName;           // 'Auto École du Centre'
$client->email;                 // 'contact@ae-centre.cm' (null si absent)
$client->phone;                 // '+237690000000'
$client->status;                // 'active'
$client->version;               // 3
$client->declaredAttributes;    // ['localisation' => 'Douala', 'annee' => '2019']
$client->isErased();            // false
```

`declaredAttributes` porte les valeurs **que le projet a déclarées au
Core** : la clé est celle de la déclaration, la valeur celle que l'opérateur a
saisie. Elles sont écrites **avant** l'événement : quand l'application relit
le client depuis l'événement, elle les trouve.

## L'événement que le paquet émet

Quand votre fabrique a créé un locataire, le paquet émet un événement
**local**, que votre application peut écouter :

```php
use CoreAdapter\Laravel\Tenant\TenantProvisioned;

Event::listen(TenantProvisioned::class, function (TenantProvisioned $e): void {
    // $e->reference : la référence que le Core connaît
    // $e->locataire : votre objet, tout juste créé
    // $e->clientId  : l'identifiant interne du client chez le Core
});
```

C'est le moment où votre application peut envoyer un e-mail de bienvenue,
préparer un compte, etc.
