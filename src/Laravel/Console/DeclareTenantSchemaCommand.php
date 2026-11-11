<?php

declare(strict_types=1);

namespace CoreAdapter\Laravel\Console;

use CoreAdapter\CoreClient;
use CoreAdapter\Laravel\Tenant\TenantDeclaration;
use CoreAdapter\Services\TenantSchemas;
use CoreAdapter\Support\Payload;
use Illuminate\Console\Command;

/**
 * Propose au Core la forme du locataire de cette application.
 *
 * Le Core ne devine pas le modèle métier d'une application : il ne sait
 * pas ce qu'est un code d'auto-école, ni une localisation. L'application
 * le lui dit — la clé unique qu'elle désigne et les champs dont elle a
 * besoin — et **l'opérateur accepte** ensuite, dans la console.
 *
 * ```
 * php artisan core:declare-tenant-schema
 * ```
 *
 * La commande est sans effet de bord : elle **propose**, elle n'impose
 * rien. Elle n'écrit ni sur le commerce, ni sur les clients (ADR-0122 §8).
 */
final class DeclareTenantSchemaCommand extends Command
{
    protected $signature = 'core:declare-tenant-schema';

    protected $description = 'Propose au Core la forme du locataire de cette application';

    public function handle(TenantSchemas $schemas, TenantDeclaration $declaration, CoreClient $client): int
    {
        // Le Core sait quel projet la clé représente : il le dit.
        $projectId = Payload::string($client->get('whoami'), 'project_id') ?? '';

        if ($projectId === '') {
            $this->components->error(
                'Le Core n\'a pas identifié le projet de cette application : rien n\'a été proposé.'
            );

            return self::FAILURE;
        }

        $cle = $declaration->cleUnique();

        $champs = [];

        foreach ($declaration->champs() as $champ) {
            $champs[] = [
                'key' => $champ['cle'],
                'label' => $champ['libelle'] ?? $champ['cle'],
                'type' => $champ['type'] ?? 'string',
                'required' => (bool) ($champ['requis'] ?? false),
                'max_length' => $champ['longueur'] ?? null,
                'source' => $champ['source'],
            ];
        }

        $schemas->propose($projectId, [
            'unique_key' => $cle['attribut'],
            'unique_key_pattern' => $cle['motif'],
            'fields' => $champs,
        ]);

        $this->components->info(sprintf(
            'Déclaration proposée au projet %s : elle attend la lecture de l\'opérateur, dans la console du Core.',
            $projectId,
        ));

        return self::SUCCESS;
    }
}
