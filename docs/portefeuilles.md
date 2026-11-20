# Portefeuilles

Ce que fait cette page : détenir de la valeur chez le Core, la déplacer
entre parties, et enregistrer un versement que vous avez fait vous-même.

---

## Le parcours

```
1. Un paiement CONFIRMÉ nomme un portefeuille → le portefeuille est crédité.
2. Votre application lit le solde, les mouvements.
3. Elle TRANSFÈRE de la valeur entre parties (tout ou rien).
4. Elle ENREGISTRE un retrait qu'elle a fait elle-même (mobile money, banque).
```

## La « partie » — de qui parle-t-on ?

Une partie est **qui que vous nommez** : un utilisateur, un partenaire, un
compte de séquestre. Vous la désignez par **votre** référence — la même que
celle du client du Core.

```php
$wallets = app(Wallets::class)->ofParty('AE1234');
```

## Lire

### Les portefeuilles d'une partie

```php
use CoreAdapter\Services\Wallets;

$wallets = app(Wallets::class)->ofParty('AE1234');

foreach ($wallets as $wallet) {
    $wallet->walletId;       // 'wal_01H…'
    $wallet->party;          // 'AE1234'
    $wallet->currency;       // 'XAF'
    $wallet->availableMinor; // 125000  (1 250 FCFA disponibles)
    $wallet->heldMinor;      // 0       (montants en séquestre)
    $wallet->status;         // 'active'
}
```

**Un portefeuille par devise.** Une partie qui n'a jamais rien reçu **n'en a
aucun** : la liste est vide. Lire ne crée rien — c'est un choix délibéré, une
lecture n'a pas d'effet de bord.

### Le solde

```php
$wallet = app(Wallets::class)->balance('wal_01H…');

$wallet->availableMinor; // 125000
$wallet->heldMinor;      // 0
```

### Les mouvements

```php
$mouvements = app(Wallets::class)->transactions('wal_01H…', page: 2, perPage: 50);

foreach ($mouvements as $mouvement) {
    $mouvement->id;           // 'wtx_01H…'
    $mouvement->occurredAt;   // '2026-11-20T09:12:44+00:00'
    $mouvement->direction;    // 'credit' | 'debit'
    $mouvement->amountMinor;  // 50000
    $mouvement->currency;     // 'XAF'
    $mouvement->type;         // 'payment' | 'transfer' | 'withdrawal' | 'debit'
    $mouvement->operationId;  // 'wop_01H…'
    $mouvement->paymentId;    // 'pay_01H…' (sur un mouvement de paiement)
    $mouvement->reference;    // votre référence
    $mouvement->description;  // 'Abonnement mensuel'
    $mouvement->metadata;     // ['commande' => '2026-0042']
}
```

Du **plus récent** au plus ancien. `page` commence à 1, `per_page` est borné
à 100.

## Transférer

```php
$operation = app(Wallets::class)->transfer(
    idempotencyKey: 'transfer:2026-11-20:0042',
    currency: 'XAF',
    legs: [
        ['from' => 'AE1234', 'to' => 'PARTENAIRE-01', 'amount' => 50000],
    ],
    description: 'Commission de mars',
    metadata: ['contrat' => '2026-014'],
);
```

**Les jambes.** Une à vingt, une seule devise, et c'est **tout ou rien** :
si une source n'a pas assez, **aucune** jambe ne bouge et le Core répond
`409 insufficient-funds`.

**La clé d'idempotence.** Elle est **votre** identifiant du transfert :
`transfer:2026-11-20:0042`. Rejoué, il ne déplace la valeur qu'une fois.
Mettez-y ce qui identifie le transfert dans **votre** métier — un UUID, un
numéro de commande — jamais un aléatoire.

**Le retour.**

```php
$operation->id;           // 'wop_01H…'
$operation->type;         // 'transfer'
$operation->reference;    // votre référence
$operation->currency;     // 'XAF'
$operation->totalMinor;   // 50000
$operation->description;  // 'Commission de mars'
$operation->metadata;     // ['contrat' => '2026-014']
$operation->legs;         // les jambes, une par une
$operation->createdAt;
$operation->wallets;      // les portefeuilles concernés, après coup
```

## Enregistrer un retrait

```php
$operation = app(Wallets::class)->withdraw(
    idempotencyKey: 'retrait:2026-11-20:0042',
    partyRef: 'AE1234',
    amount: 50000,
    currency: 'XAF',
    description: 'Retrait mobile money du 20/11',
    externalReference: 'MM-2026-11-20-8842',   // votre preuve
);
```

> **Le Core n'envoie pas l'argent.** `withdraw()` **enregistre** un versement
> que **vous** avez fait (mobile money, banque, virement). Il débite le
> portefeuille et garde votre preuve. Si vous voulez que le Core envoie
> l'argent, c'est une autre chose — demandez-le à l'opérateur.

`external_reference` est votre preuve : l'identifiant de la transaction
mobile money, la référence du virement. C'est ce qui permet à un opérateur de
réconcilier plus tard.

## L'argent n'entre que par un paiement confirmé

**C'est la règle qui structure tout.** L'argent n'entre dans un portefeuille
que par un paiement **confirmé** qui le nomme :

```php
$payment = app(Payments::class)->paylink([
    'channel' => 'mtn_momo',
    'amount' => 50000,
    'currency' => 'XAF',
    'reason' => 'Provision du portefeuille',
    'wallet_recipient_external_ref' => 'AE1234',   // ← le portefeuille crédité
], 'commande-2026-0042');
```

Il n'y a pas de « crédit libre » : une API qui dirait « ajoute 50 000 à ce
portefeuille » serait une faille. Le chemin est toujours : un paiement réel,
confirmé par le prestataire, qui nomme sa cible.

## Le patron recommandé — payer une commission

```php
final class VerseCommission
{
    public function __construct(private Wallets $portefeuilles) {}

    public function __invoke(string $source, string $cible, int $montant): void
    {
        try {
            $operation = $this->portefeuilles->transfer(
                // Une clé par transfert : le job peut être rejoué, l'argent non.
                idempotencyKey: 'transfer:'.Str::uuid()->toString(),
                currency: 'XAF',
                legs: [['from' => $source, 'to' => $cible, 'amount' => $montant]],
                description: 'Commission',
            );
        } catch (CoreException $e) {
            if ($e->is('insufficient-funds')) {
                // Aucun mouvement n'a eu lieu : rien à compensating.
                throw new RuntimeException('Solde insuffisant sur '.$source);
            }

            if ($e->isTransient()) {
                // Rejouer plus tard, avec la MÊME clé.
                throw $e;
            }

            throw $e;
        }
    }
}
```

## Ce que le portefeuille ne fait pas

- Il ne **paie** pas : c'est le rôle des paiements ([Paiements](paiements.md)).
- Il ne **retire** pas d'argent chez vous : `withdraw()` enregistre, il ne
  débite pas de compte bancaire.
- Il n'a pas de **devise multiple par portefeuille** : un portefeuille, une
  devise. Un portefeuille par devise et par partie.
- Il ne porte pas de **frais** : les montants sont ceux que vous ordonnez.

## Erreurs à connaître

| Type | HTTP | Cause | Ce qu'on fait |
|---|---|---|---|
| `insufficient-funds` | 409 | une source n'a pas assez | **rien n'a bougé** : recharger, ou réduire |
| `validation-failed` | 422 | jambe mal formée, devises mêlées, montant nul | corriger l'appel |
| `wallet-not-found` | 404 | la partie ou le portefeuille n'existe pas — ou n'est pas à vous | vérifier la référence |
| `idempotency-key-conflict` | 409 | la même clé, un contenu différent | c'est un bug : la clé doit identifier le transfert |
| `unreachable` | 0 | le Core n'a pas répondu | rejouer avec la **même** clé |

> `insufficient-funds` est un refus **définitif** : le Core n'a rien
> prélevé, et rien n'a bougé. Il ne faut pas le rejouer tel quel.
