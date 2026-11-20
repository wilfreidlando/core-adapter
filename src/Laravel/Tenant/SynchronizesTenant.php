<?php

declare(strict_types=1);

namespace CoreAdapter\Laravel\Tenant;

use CoreAdapter\Dto\Customer;

/**
 * La synchronisation **optionnelle** d'un locataire déjà connu.
 *
 * Les valeurs déclarées apparaissent souvent **après** la création du
 * client : l'opérateur renseigne l'e-mail, la localisation ou l'année de
 * création plus tard. Le locataire existe déjà chez l'application — et elle
 * ne voit jamais ces valeurs.
 *
 * Une application qui veut les voir implémente cette interface **en plus**
 * de `TenantFactory` : le paquet relit alors le client chez le Core et
 * appelle `synchroniser()`.
 *
 * ```php
 * final class MonLocataire implements TenantFactory, SynchronizesTenant
 * {
 *     public function synchroniser(object $locataire, string $reference, Customer $client): void
 *     {
 *         // Ne remplir que ce qui manque : le Core reste le maître.
 *     }
 * }
 * ```
 *
 * Une application qui ne l'implémente pas **ne change rien** : c'est le
 * paquet qui décide, et il ne demande rien de plus.
 *
 * @see TenantFactory
 */
interface SynchronizesTenant
{
    /**
     * Reprend le locataire local à partir du client que le Core connaît.
     *
     * Le client est relu **au Core** : la source de vérité reste le Core,
     * l'application ne fait que recopier ce qu'il dit.
     *
     * @param object $locataire le locataire local, déjà relié à la référence
     * @param string $reference la référence que le Core connaît
     * @param Customer $client le client, tel que le Core le rend
     */
    public function synchroniser(object $locataire, string $reference, Customer $client): void;
}
