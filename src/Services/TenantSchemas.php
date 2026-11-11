<?php

declare(strict_types=1);

namespace CoreAdapter\Services;

use CoreAdapter\CoreClient;

/**
 * La déclaration de la forme du locataire.
 *
 * L'application **propose** au Core la forme de son locataire : la clé
 * unique qu'elle désigne, le motif que ses références doivent respecter, et
 * les clés d'attribut dont elle a besoin. La proposition n'est pas la forme
 * en force — elle attend que l'opérateur la lise et l'accepte, dans la
 * console du Core.
 *
 * Ce geste ne touche ni au commerce, ni aux clients (ADR-0122 §8) : il
 * décrit une forme.
 */
final class TenantSchemas
{
    public function __construct(private readonly CoreClient $client) {}

    /**
     * Propose la déclaration d'un projet.
     *
     * @param array{unique_key: string, unique_key_pattern: string, fields: list<array{key: string, label: string, type: string, required: bool, max_length: int|null, source: string}>} $declaration
     * @return array<string, mixed> la déclaration proposée
     */
    public function propose(string $projectId, array $declaration): array
    {
        return $this->client->put(
            'projects/'.rawurlencode($projectId).'/tenant-schema',
            $declaration,
        );
    }
}
