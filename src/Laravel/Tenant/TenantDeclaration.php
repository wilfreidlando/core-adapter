<?php

declare(strict_types=1);

namespace CoreAdapter\Laravel\Tenant;

/**
 * Ce que votre application attend pour créer son locataire.
 *
 * Le Core ne connaît pas le modèle métier d'une application : c'est
 * l'application qui **déclare** ses clés, et le Core qui les collecte et les
 * rend. Une déclaration, c'est :
 *
 *  - la **clé unique** que l'application désigne — l'attribut qui identifie un
 *    locataire chez elle, et le motif qu'il doit respecter pour que la
 *    référence du Core puisse le devenir ;
 *  - les **clés** dont l'application a besoin, et d'où vient chacune.
 *
 * Le vocabulaire des sources est celui du paquet :
 *
 *  - `core_identity.display_name`, `core_identity.email`,
 *    `core_identity.phone`, `core_identity.locale` : la valeur vient de
 *    l'identité que le Core tient du client ;
 *  - `declared.<clé>` : la valeur vient des attributs que le projet a déclarés
 *    au Core (absents tant que le Core ne les connaît pas).
 */
interface TenantDeclaration
{
    /**
     * La clé unique de l'application et le motif qu'elle doit respecter.
     *
     * @return array{attribut: string, motif: string}
     */
    public function cleUnique(): array;

    /**
     * Les clés dont l'application a besoin, et d'où vient chacune.
     *
     * `libelle` est ce que l'opérateur verra devant le champ — c'est le
     * vocabulaire de l'application, pas celui du Core. `type` et `longueur`
     * bornent la saisie ; le Core les rend tels quels.
     *
     * @return array<int, array{cle: string, source: string, requis?: bool, libelle?: string, type?: string, longueur?: int}>
     */
    public function champs(): array;
}
