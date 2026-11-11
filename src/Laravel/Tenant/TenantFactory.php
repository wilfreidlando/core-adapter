<?php

declare(strict_types=1);

namespace CoreAdapter\Laravel\Tenant;

/**
 * Comment votre application fabrique son locataire.
 *
 * C'est **tout** ce que le paquet demande à une application : trois
 * méthodes. Le paquet se charge de tout le reste — recevoir l'événement,
 * vérifier la référence, lire le client chez le Core, mapper les valeurs,
 * rejouer en cas de panne, ne pas créer deux fois le même locataire.
 *
 * ```php
 * final class MonLocataire implements TenantFactory
 * {
 *     public function trouver(string $reference): ?object { /* … *\/ }
 *     public function lier(object $locataire, string $reference): void { /* … *\/ }
 *     public function creer(string $cle, array $attributs): object { /* … *\/ }
 * }
 * ```
 */
interface TenantFactory
{
    /**
     * Le locataire local que porte cette référence, s'il existe.
     *
     * La référence est celle que le Core connaît : l'application la
     * reconnaît par son propre identifiant (par exemple son code).
     */
    public function trouver(string $reference): ?object;

    /**
     * Relie le locataire local à la référence du Core.
     *
     * Appelé quand le locataire existait déjà : le Core vient de confirmer
     * qu'un client correspond à cet établissement.
     */
    public function lier(object $locataire, string $reference): void;

    /**
     * Crée le locataire local.
     *
     * L'implémentation est libre : c'est votre modèle, vos règles, vos
     * messages. Le paquet ne demande qu'une chose — que l'opération soit
     * **atomique** et **idempotente** : le même appel ne doit pas créer
     * deux fois le même locataire.
     *
     * @param array<string, mixed> $attributs la clé unique et la valeur de chaque clé déclarée
     */
    public function creer(string $cle, array $attributs): object;
}
