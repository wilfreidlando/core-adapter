# La documentation du paquet

`wilfreidlando/core-adapter` est le client du Core : votre application
**reçoit** son locataire, **lit** ses droits, **compte** son usage, **ouvre**
son portail, **encaisse**, **écrit** des notifications et **gère** des
portefeuilles — sans jamais faire de commerce elle-même.

Ces pages sont le parcours d'un développeur qui branche une application (Laravel
ou non) sur le Core. Chaque page est **autonome** : on peut la lire seule, et
elle donne le code à écrire.

## Par où commencer

| Vous voulez… | Lisez |
|---|---|
| Installer le paquet et le rendre opérationnel en 15 minutes | [Installation](installation.md) |
| Savoir qui est votre locataire, et le créer chez vous | [Le locataire](locataire.md) |
| Savoir si l'abonné a le droit de faire quelque chose | [Droits, usage et portail](droits-usage-portail.md) |
| Encaisser (lien de paiement, paiement par téléphone) | [Paiements](paiements.md) |
| Envoyer un WhatsApp | [Notifications](notifications.md) |
| Détenir de la valeur, la transférer, l'enregistrer | [Portefeuilles](portefeuilles.md) |
| Réagir à ce que le Core vous dit | [Événements](evenements.md) |
| Comprendre une erreur du Core | [Erreurs](erreurs.md) |
| Utiliser le paquet **sans** Laravel | [Hors Laravel](hors-laravel.md) |

## Les trois choses à savoir avant de commencer

**1. Le Core est le maître.** Votre application ne crée **jamais** un client
chez le Core, et ne modifie **jamais** un abonnement ni une facture. Elle
déclare ce qu'elle consomme, elle lit ce qui la concerne, et elle reçoit les
événements. La clé d'une application cliente ne porte, par construction,
**aucun** droit d'écriture sur le commerce.

**2. Un événement ne porte pas de donnée personnelle.** La charge utile d'un
événement est `{id, type, data}`, et `data` ne contient **ni e-mail, ni
téléphone, ni nom**. Le locataire y est nommé par **votre** référence. Quand
vous avez besoin de son identité, vous la **demandez** :
`Customers::get('auto-ecole-42')`.

**3. L'argent se compte en plus petite unité.** `5000` XAF vaut 5 000 FCFA ;
`100` EUR vaut 1 €. Tous les montants du Core sont des **entiers**, jamais des
décimaux : c'est la seule façon de ne pas perdre un centime en traversant un
`float`.

## La règle du temps

Le Core répond en moins de dix secondes. Un **écouteur** d'événement ne fait
donc **jamais** d'appel réseau : il confie le travail à une file
(`ProvisionsTenant` le fait). Le seul appel réseau du paquet, hors vos propres
appels métier, est la relecture du client — et elle se fait dans le worker.

## Le vocabulaire

| Mot | Ce que ça veut dire |
|---|---|
| **le Core** | le service qui tient le commerce : clients, abonnements, factures, encaissements, portefeuilles |
| **le client** (Core) | une personne/société connue du Core. Créé par l'opérateur, **jamais** par vous |
| **le locataire** (votre application) | votre propre objet, créé quand le Core dit qu'un client existe |
| **la référence** | l'identifiant **votre** d'un client. Le Core la connaît (`external_ref`), c'est votre clé unique |
| **les droits** (`entitlements`) | ce que l'abonné a le droit de faire, et ce qu'il lui reste |
| **le portail** | la page du Core où le propriétaire gère son abonnement et ses factures |
| **la forme du locataire** | la déclaration que votre application fait au Core : sa clé unique, et les clés dont elle a besoin |

## Le dépôt

- Le paquet : <https://github.com/wilfreidlando/core-adapter>
- Le Core : le dépôt `vcam-core-system` (privé) — c'est là que se trouve la
  console de l'opérateur et le contrat OpenAPI complet.
