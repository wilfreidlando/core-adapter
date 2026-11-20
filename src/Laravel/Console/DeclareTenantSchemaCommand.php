<?php

declare(strict_types=1);

namespace CoreAdapter\Laravel\Console;

use CoreAdapter\CoreClient;
use CoreAdapter\CoreException;
use CoreAdapter\Laravel\Tenant\TenantDeclaration;
use CoreAdapter\Laravel\Tenant\TenantSchemaDeclarer;
use CoreAdapter\Support\DeclarationRefused;
use CoreAdapter\Support\PatternRefused;
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
 *
 * **La traduction est faite avant l'envoi** : le motif de la clé unique est
 * un motif `preg` chez l'application (`/^AE[0-9]{4}(?:[0-9]{2}){0,2}$/`), et
 * le Core n'en veut qu'un motif nu (`^AE[0-9]{4}([0-9]{2}){0,2}$`). Le motif
 * de l'application reste intact pour la validation locale de la référence ;
 * c'est la **copie** envoyée au Core qui est normalisée. Ce qui n'a pas de
 * traduction honnête est refusé **ici**, avec un message qui dit quoi
 * faire — pas par un `422` muet du Core.
 */
final class DeclareTenantSchemaCommand extends Command
{
    protected $signature = 'core:declare-tenant-schema';

    protected $description = 'Propose au Core la forme du locataire de cette application';

    public function handle(TenantSchemaDeclarer $declarer, TenantDeclaration $declaration, CoreClient $client): int
    {
        // Le Core sait quel projet la clé représente : il le dit.
        $projectId = Payload::string($client->get('whoami'), 'project_id') ?? '';

        if ($projectId === '') {
            $this->components->error(
                'Le Core n\'a pas identifié le projet de cette application : rien n\'a été proposé.'
            );

            return self::FAILURE;
        }

        try {
            $reponse = $declarer->declare($projectId, $declaration);
        } catch (PatternRefused|DeclarationRefused $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        } catch (CoreException $e) {
            $this->components->error('Le Core a refusé la déclaration : '.$e->detail);

            return self::FAILURE;
        }

        $statut = is_string($reponse['status'] ?? null) ? $reponse['status'] : 'pending';
        $version = $reponse['version'] ?? '?';

        if ($statut === 'accepted') {
            $this->components->info(sprintf(
                'La forme du locataire du projet %s est en force (version %s).',
                $projectId,
                is_scalar($version) ? (string) $version : '?',
            ));

            return self::SUCCESS;
        }

        $this->components->info(sprintf(
            'Déclaration proposée au projet %s (version %s) : elle attend la lecture de l\'opérateur, dans la console du Core.',
            $projectId,
            is_scalar($version) ? (string) $version : '?',
        ));

        return self::SUCCESS;
    }
}
