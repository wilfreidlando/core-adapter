<?php

declare(strict_types=1);

namespace CoreAdapter\Laravel\Console;

use CoreAdapter\Laravel\Tenant\TenantDeclaration;
use CoreAdapter\Laravel\Tenant\TenantLister;
use CoreAdapter\Laravel\Tenant\TenantRows;
use Illuminate\Console\Command;
use Illuminate\Contracts\Container\Container;

/**
 * Exporte les locataires que l'application a déjà.
 *
 * **C'est le chemin de secours.** Le chemin ordinaire, c'est la console du
 * Core : elle vient chercher la liste, l'opérateur la relit, et le Core crée
 * les clients (ADR-0133). Cette commande reste pour le cas où l'application
 * est injoignable, ou pour un lot hors bande.
 *
 * ```
 * php artisan core:export-tenants > tenants.csv
 * ```
 *
 * La commande est **en lecture seule** : elle ne parle pas au Core, elle ne
 * demande aucun droit, elle ne modifie rien.
 *
 * **Le fichier porte les colonnes de la forme en vigueur** — celles que
 * l'application a déclarées : la référence, les colonnes du Core que la
 * déclaration ne remplace pas, puis les clés déclarées, dans l'ordre de la
 * déclaration. Le Core refuse un fichier dont l'en-tête ne porte pas
 * exactement ces colonnes (ADR-0132) : l'export les écrit donc, au lieu de
 * laisser l'opérateur les deviner.
 */
final class ExportTenantsCommand extends Command
{
    protected $signature = 'core:export-tenants
                            {--output= : Le fichier où écrire le CSV (par défaut : la sortie standard)}';

    protected $description = 'Exporte les locataires existants, pour que le Core les reconnaisse';

    public function handle(Container $container): int
    {
        if (! $container->bound(TenantLister::class)) {
            $this->components->error(
                'Cette application ne déclare pas ses locataires existants : rien à exporter.'
            );

            return self::FAILURE;
        }

        /** @var TenantLister $lister */
        $lister = $container->make(TenantLister::class);

        $champs = $this->champs($container);
        $colonnes = $this->colonnes($champs);

        $lignes = [];

        foreach ($lister->tenants() as $locataire) {
            $ligne = TenantRows::build(self::ligne($locataire), $champs);

            $sortie = [];

            foreach ($colonnes as $colonne) {
                $sortie[] = $this->cellule($ligne[$colonne] ?? null);
            }

            $lignes[] = $sortie;
        }

        $csv = $this->csv($lignes, $colonnes);

        $fichier = $this->option('output');

        if (is_string($fichier) && $fichier !== '') {
            file_put_contents($fichier, $csv);

            $this->components->info(sprintf(
                '%d locataire(s) exporté(s) vers %s.',
                count($lignes),
                $fichier,
            ));

            return self::SUCCESS;
        }

        $this->output->write($csv);

        return self::SUCCESS;
    }

    /**
     * Les clés déclarées par l'application, si elle en déclare.
     *
     * @return list<array{cle: string, source: string, requis?: bool, libelle?: string, type?: string, longueur?: int}>
     */
    private function champs(Container $container): array
    {
        if (! $container->bound(TenantDeclaration::class)) {
            return [];
        }

        /** @var TenantDeclaration $declaration */
        $declaration = $container->make(TenantDeclaration::class);

        return array_values($declaration->champs());
    }

    /**
     * Les colonnes du fichier, dans l'ordre.
     *
     * La référence d'abord, puis les colonnes du Core que la déclaration ne
     * remplace pas, puis les clés déclarées — l'ordre du modèle que le Core
     * propose au téléchargement (ADR-0122 §5).
     *
     * @param list<array{cle: string, source: string, requis?: bool, libelle?: string, type?: string, longueur?: int}> $champs
     * @return list<string>
     */
    private function colonnes(array $champs): array
    {
        $remplacees = [];

        foreach ($champs as $champ) {
            if (! str_starts_with($champ['source'], 'core_identity.')) {
                continue;
            }

            $remplacees[substr($champ['source'], strlen('core_identity.'))] = true;
        }

        $colonnes = ['external_ref'];

        foreach (['display_name', 'email', 'phone'] as $coeur) {
            if (isset($remplacees[$coeur])) {
                continue;
            }

            $colonnes[] = $coeur;
        }

        foreach ($champs as $champ) {
            $colonnes[] = $champ['cle'];
        }

        return $colonnes;
    }

    /**
     * Le locataire rendu par l'application.
     *
     * @return array<string, mixed>
     */
    private static function ligne(mixed $locataire): array
    {
        if (! is_array($locataire)) {
            return [];
        }

        /** @var array<string, mixed> $locataire */
        return $locataire;
    }

    /**
     * Une valeur, comme le CSV l'attend : du texte, ou rien.
     */
    private function cellule(mixed $valeur): string
    {
        if ($valeur === null) {
            return '';
        }

        if (is_string($valeur)) {
            return $valeur;
        }

        if (is_int($valeur) || is_float($valeur) || is_bool($valeur)) {
            return (string) $valeur;
        }

        return '';
    }

    /**
     * Le CSV, tel que la console du Core l'attend.
     *
     * @param list<list<string>> $lignes
     * @param list<string> $colonnes
     */
    private function csv(array $lignes, array $colonnes): string
    {
        $sortie = fopen('php://temp', 'r+');

        if ($sortie === false) {
            return '';
        }

        fputcsv($sortie, $colonnes);

        foreach ($lignes as $ligne) {
            fputcsv($sortie, $ligne);
        }

        rewind($sortie);

        $csv = stream_get_contents($sortie);

        fclose($sortie);

        return $csv === false ? '' : $csv;
    }
}
