<?php

declare(strict_types=1);

namespace CoreAdapter\Laravel\Console;

use CoreAdapter\Laravel\Tenant\TenantLister;
use Illuminate\Console\Command;
use Illuminate\Contracts\Container\Container;

/**
 * Exporte les locataires que l'application a déjà.
 *
 * Le Core ne les connaît pas encore. L'opérateur importe le fichier dans la
 * console du Core : le Core crée les clients, pousse `customer.created`, et
 * l'application **lie** ses locataires existants — aucun doublon, aucune
 * écriture de l'application chez le Core.
 *
 * ```
 * php artisan core:export-tenants > tenants.csv
 * ```
 *
 * La commande est **en lecture seule** : elle ne parle pas au Core, elle ne
 * demande aucun droit, elle ne modifie rien.
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

        $lignes = [];

        foreach ($lister->tenants() as $locataire) {
            $lignes[] = [
                $locataire['external_ref'],
                $locataire['display_name'],
                $locataire['email'] ?? '',
                $locataire['phone'] ?? '',
            ];
        }

        $csv = $this->csv($lignes);

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
     * Le CSV, tel que la console du Core l'attend.
     *
     * @param list<array{0: string, 1: string, 2: string, 3: string}> $lignes
     */
    private function csv(array $lignes): string
    {
        $sortie = fopen('php://temp', 'r+');

        if ($sortie === false) {
            return '';
        }

        fputcsv($sortie, ['external_ref', 'display_name', 'email', 'phone']);

        foreach ($lignes as $ligne) {
            fputcsv($sortie, $ligne);
        }

        rewind($sortie);

        $csv = stream_get_contents($sortie);

        fclose($sortie);

        return $csv === false ? '' : $csv;
    }
}
