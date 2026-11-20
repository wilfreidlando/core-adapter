<?php

declare(strict_types=1);

namespace CoreAdapter\Services;

use CoreAdapter\CoreClient;
use CoreAdapter\CoreException;

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
     *
     * @throws CoreException le Core a refusé la déclaration
     */
    public function propose(string $projectId, array $declaration): array
    {
        return $this->client->put(
            'projects/'.rawurlencode($projectId).'/tenant-schema',
            $declaration,
        );
    }

    /**
     * La forme **en vigueur** du locataire, et celle qui attend l'opérateur.
     *
     * Ce que le Core rend est une **forme** — des noms, des types, des
     * obligations — jamais une valeur. C'est la relecture qui permet à
     * l'application de savoir ce que le Core attend d'elle, au lieu de se
     * souvenir de ce qu'elle avait proposé.
     *
     * @return array{accepted: array<string, mixed>|null, pending: array<string, mixed>|null}
     *
     * @throws CoreException le Core n'a pas répondu
     */
    public function state(string $projectId): array
    {
        $payload = $this->client->get('projects/'.rawurlencode($projectId).'/tenant-schema');

        return [
            'accepted' => self::form($payload['accepted'] ?? null),
            'pending' => self::form($payload['pending'] ?? null),
        ];
    }

    /**
     * La forme en vigueur, ou `null` quand le projet n'en a aucune.
     *
     * @return array<string, mixed>|null
     */
    public function accepted(string $projectId): ?array
    {
        return $this->state($projectId)['accepted'];
    }

    /**
     * La proposition qui attend l'opérateur, ou `null`.
     *
     * @return array<string, mixed>|null
     */
    public function pending(string $projectId): ?array
    {
        return $this->state($projectId)['pending'];
    }

    /**
     * Une forme, telle que le Core la rend — ou `null` quand il n'y en a pas.
     *
     * @return array<string, mixed>|null
     */
    private static function form(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $form = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $form[$key] = $item;
            }
        }

        return $form;
    }
}
