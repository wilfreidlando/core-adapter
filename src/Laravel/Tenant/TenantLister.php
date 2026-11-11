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
 * Le chemin est donc celui d'un **import fait par l'opérateur** : votre
 * application exporte ses locataires, l'opérateur importe le fichier dans la
 * console du Core, le Core crée les clients et pousse `customer.created` —
 * et votre application, qui connaît déjà ces locataires, **les lie** au lieu
 * d'en créer un second.
 *
 * Ce contrat est **facultatif** : une application qui naît avec le Core n'a
 * rien à exporter, et n'implémente donc pas cette interface.
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
 *             ];
 *         }
 *     }
 * }
 * ```
 */
interface TenantLister
{
    /**
     * Les locataires de l'application, prêts à être exportés.
     *
     * La forme est celle que le Core connaît d'un client : la **référence**
     * (votre clé unique), la raison sociale, l'e-mail et le téléphone.
     *
     * @return iterable<array{external_ref: string, display_name: string, email: ?string, phone: ?string}>
     */
    public function tenants(): iterable;
}
