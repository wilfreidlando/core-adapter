<?php

declare(strict_types=1);

namespace CoreAdapter\Laravel\Tenant;

/**
 * Les locataires que votre application a **déjà**.
 *
 * Une application qui existait avant le Core a déjà ses locataires — et le
 * Core ne les connaît pas. Il faut qu'il les **reconnaisse**, sans que
 * l'application n'écrive jamais un client chez le Core (le Core reste le seul
 * écrivain de son état commercial).
 *
 * Le chemin est celui d'une **lecture** : le Core vient chercher cette liste
 * (ADR-0133), l'opérateur, depuis la console du Core, la relit, et le Core
 * crée les clients et pousse `customer.created` — et votre application, qui
 * connaît déjà ces locataires, **les lie** au lieu d'en créer un second.
 *
 * Ce contrat est **facultatif** : une application qui naît avec le Core n'a
 * rien à faire connaître, et n'implémente donc pas cette interface.
 *
 * ```php
 * final class LocatairesAutoEcole implements TenantLister
 * {
 *     public function tenants(): iterable
 *     {
 *         foreach (AutoEcole::withoutGlobalScopes()->cursor() as $autoEcole) {
 *             yield [
 *                 'external_ref' => $autoEcole->code,
 *                 'display_name' => $autoEcole->nom,
 *                 'email' => $autoEcole->email,
 *                 'phone' => $autoEcole->telephone,
 *                 // Et les valeurs que vous avez déclarées, sous le nom
 *                 // de vos clés :
 *                 'localisation' => $autoEcole->localisation,
 *             ];
 *         }
 *     }
 * }
 * ```
 */
interface TenantLister
{
    /**
     * Les locataires de l'application.
     *
     * Chaque élément est un tableau :
     *
     * - `external_ref` — votre clé unique (votre code, votre matricule) ;
     * - `display_name`, `email`, `phone` — l'identité que le Core attend ;
     * - **et, sous le nom de chaque clé déclarée**, la valeur que vous
     *   connaissez déjà (`localisation`, `nom_respo`, `annee_creation`…).
     *
     * Sans ces dernières valeurs, le Core ne saurait rien de ces clients :
     * il les créerait avec la seule identité, et votre application recevrait
     * des clients à moitié renseignés.
     *
     * @return iterable<array<string, mixed>>
     */
    public function tenants(): iterable;
}
