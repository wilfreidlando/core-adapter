<?php

declare(strict_types=1);

namespace CoreAdapter\Laravel\Tenant;

/**
 * La ligne d'un locataire, dans les colonnes que le Core attend.
 *
 * Le Core lit une liste de clients comme il lit un fichier d'import : une
 * ligne par client, **une colonne par clé**. La ligne qu'il attend n'est pas
 * celle que l'application connaît : elle porte la **référence** (la clé
 * unique de l'application), l'identité du Core (le nom, l'e-mail, le
 * téléphone), et — pour chaque clé déclarée — la valeur que l'application
 * connaît déjà.
 *
 * Cette classe fait la traduction. Elle est **pure** : elle ne lit rien, elle
 * ne sait rien de l'application, elle ne fait qu'y ranger des valeurs.
 *
 * Deux règles :
 *
 * - une clé déclarée dont la source est `core_identity.<champ>` reçoit la
 *   valeur de l'identité du Core : c'est cette clé qui **remplace** la
 *   colonne correspondante chez le Core (ADR-0122 §5) ;
 * - une clé déclarée dont la source est `declared.<clé>` reçoit la valeur que
 *   l'application a sous **le nom de la clé** : c'est le contrat de
 *   `TenantLister`.
 */
final class TenantRows
{
    /**
     * La ligne d'un locataire.
     *
     * @param array<string, mixed> $tenant le locataire, tel que l'application le rend
     * @param list<array{cle: string, source: string, requis?: bool, libelle?: string, type?: string, longueur?: int}> $champs les clés déclarées par l'application
     * @return array<string, mixed> la ligne, dans les colonnes du Core
     */
    public static function build(array $tenant, array $champs): array
    {
        $ligne = [
            'external_ref' => self::texte($tenant['external_ref'] ?? null),
            'display_name' => self::texte($tenant['display_name'] ?? null),
            'email' => self::texte($tenant['email'] ?? null),
            'phone' => self::texte($tenant['phone'] ?? null),
            'locale' => self::texte($tenant['locale'] ?? null),
        ];

        foreach ($champs as $champ) {
            $cle = $champ['cle'];

            $ligne[$cle] = self::valeur($tenant, $champ['source'], $cle);
        }

        return $ligne;
    }

    /**
     * La valeur d'une clé déclarée, selon sa source.
     *
     * @param array<string, mixed> $tenant
     */
    private static function valeur(array $tenant, string $source, string $cle): mixed
    {
        $source = trim($source);

        // `declared.<clé>` : la valeur vient de l'application, sous le nom
        // de la clé. La source **doit** nommer la clé (ADR-0130 §2) — mais on
        // lit quand même les deux, pour qu'une déclaration encore
        // .rectificative reste lisible.
        if (str_starts_with($source, 'declared.')) {
            $suffixe = substr($source, strlen('declared.'));

            return self::scalaire($tenant[$cle] ?? $tenant[$suffixe] ?? null);
        }

        return match ($source) {
            'core_identity.display_name' => self::texte($tenant['display_name'] ?? null),
            'core_identity.email' => self::texte($tenant['email'] ?? null),
            'core_identity.phone' => self::texte($tenant['phone'] ?? null),
            'core_identity.locale' => self::texte($tenant['locale'] ?? null),
            default => null,
        };
    }

    /**
     * Une valeur textuelle, ou `null`.
     */
    private static function texte(mixed $valeur): ?string
    {
        if ($valeur === null) {
            return null;
        }

        if (is_string($valeur)) {
            return $valeur;
        }

        if (is_int($valeur) || is_float($valeur) || is_bool($valeur)) {
            return (string) $valeur;
        }

        return null;
    }

    /**
     * Une valeur simple, telle que l'application l'a rendue.
     *
     * Une valeur déclarée peut être un nombre — une année de création, un
     * effectif : on la garde telle quelle, et le Core la validera selon le
     * type qu'il a annoncé pour cette clé.
     */
    private static function scalaire(mixed $valeur): mixed
    {
        if ($valeur === null || is_string($valeur) || is_int($valeur) || is_float($valeur) || is_bool($valeur)) {
            return $valeur;
        }

        return null;
    }
}
