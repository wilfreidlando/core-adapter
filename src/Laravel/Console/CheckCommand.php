<?php

declare(strict_types=1);

namespace CoreAdapter\Laravel\Console;

use CoreAdapter\CoreClient;
use CoreAdapter\CoreConfig;
use CoreAdapter\CoreException;
use CoreAdapter\Scopes;
use CoreAdapter\Services\TenantSchemas;
use Illuminate\Console\Command;

/**
 * `php artisan core:check` — la commande qui dit si l'application est
 * branchée.
 *
 * Elle vérifie ce qui peut l'être, et dit **ce qui manque** :
 * l'adresse du Core, la clé, les portées dont le paquet a besoin, et le
 * secret de signature des événements. Elle sort en code `1` dès qu'un
 * élément requis manque.
 *
 * Elle dit aussi où en est la **forme du locataire** : tant que la
 * déclaration n'est pas acceptée dans la console du Core, aucun attribut
 * déclaré n'est collecté — et l'application ne peut ni le voir, ni le dire.
 *
 * C'est le premier geste à faire après l'installation du paquet.
 */
final class CheckCommand extends Command
{
    /** @var string */
    protected $signature = 'core:check';

    /** @var string */
    protected $description = "Vérifie que l'application est bien branchée au Core (adresse, clé, portées, secret d'événements).";

    public function handle(CoreClient $client, CoreConfig $config, TenantSchemas $schemas): int
    {
        $this->line('Core — <options=bold>'.($config->baseUrl !== '' ? $config->baseUrl : '(adresse non configurée)').'</>');
        $this->newLine();

        $ok = true;

        if (! $this->check('L\'adresse du Core est configurée', $config->baseUrl !== '', 'CORE_BASE_URL')) {
            $ok = false;
        }

        if (! $this->check('La clé du projet est configurée', $config->apiKey !== '', 'CORE_API_KEY')) {
            $ok = false;
        }

        if (! $this->check('Le secret des événements est configuré', ($config->webhookSecret ?? '') !== '', 'CORE_WEBHOOK_SECRET')) {
            $ok = false;
        }

        $identity = $this->identity($client);

        if ($identity === null) {
            $ok = false;
        } else {
            if (! $this->checkScopes($identity['scopes'])) {
                $ok = false;
            }

            $this->declaration($schemas, $identity['project_id'], $identity['scopes']);
        }

        $this->newLine();

        if ($ok) {
            $this->line('  <fg=green>Tout est branché.</>');

            return self::SUCCESS;
        }

        $this->line('  <fg=red>Ce qui manque est listé ci-dessus.</>');

        return self::FAILURE;
    }

    /**
     * L'identité du projet, et les portées de la clé.
     *
     * @return array{project_id: string|null, scopes: list<string>}|null null quand le Core n'a pas répondu
     */
    private function identity(CoreClient $client): ?array
    {
        try {
            $payload = $client->get('whoami');
        } catch (CoreException $e) {
            if ($e->status === 0) {
                $this->line('  <fg=red>✗</> Le Core ne répond pas : '.$e->detail);
            } else {
                $this->line('  <fg=red>✗</> Le Core refuse la clé : '.$e->detail);
            }

            return null;
        }

        $projectId = $payload['project_id'] ?? null;
        $this->line('  <fg=green>✓</> Le Core répond (projet '.(is_string($projectId) ? $projectId : '?').')');

        $scopes = [];
        $raw = $payload['scopes'] ?? [];

        if (is_array($raw)) {
            foreach ($raw as $scope) {
                if (is_string($scope)) {
                    $scopes[] = $scope;
                }
            }
        }

        return [
            'project_id' => is_string($projectId) ? $projectId : null,
            'scopes' => $scopes,
        ];
    }

    /**
     * @param list<string> $scopes
     */
    private function checkScopes(array $scopes): bool
    {
        if (in_array('*', $scopes, true)) {
            $this->line('  <fg=yellow>!</> La clé porte la portée « * » : demandez une clé « Application cliente », elle est plus sûre et ne vous empêche de rien.');
        }

        $missing = array_values(array_diff(Scopes::REQUIRED, $scopes));

        if ($missing === []) {
            $this->line('  <fg=green>✓</> Les portées du paquet sont présentes ('.implode(', ', Scopes::REQUIRED).')');

            return true;
        }

        $this->line('  <fg=red>✗</> Il manque des portées : '.implode(', ', $missing));

        return false;
    }

    /**
     * Où en est la forme du locataire.
     *
     * Ce n'est pas un élément **requis** : une application qui ne déclare
     * aucune forme n'a rien à signaler. C'est donc une information, pas un
     * échec.
     *
     * @param list<string> $scopes
     */
    private function declaration(TenantSchemas $schemas, ?string $projectId, array $scopes): void
    {
        if (! in_array(Scopes::TENANT_SCHEMA_READ, $scopes, true)) {
            $this->line('  <fg=yellow>!</> La clé ne porte pas la portée « '.Scopes::TENANT_SCHEMA_READ.' » : la forme du locataire ne peut pas être relue.');

            return;
        }

        if ($projectId === null || $projectId === '') {
            return;
        }

        try {
            $state = $schemas->state($projectId);
        } catch (CoreException $e) {
            $this->line('  <fg=yellow>!</> Le Core n\'a pas répondu sur la forme du locataire : '.$e->detail);

            return;
        }

        $accepted = $state['accepted'];
        $pending = $state['pending'];

        if (is_array($accepted)) {
            $version = $accepted['version'] ?? '?';

            $this->line('  <fg=green>✓</> La forme du locataire est en force (version '.(is_scalar($version) ? (string) $version : '?').')');

            return;
        }

        if (is_array($pending)) {
            $this->line('  <fg=yellow>!</> Votre déclaration de la forme du locataire est en attente d\'acceptation dans la console du Core.');

            return;
        }

        $this->line('  <fg=yellow>!</> Aucune forme du locataire n\'est déclarée au Core (php artisan core:declare-tenant-schema).');
    }

    private function check(string $label, bool $ok, string $hint = ''): bool
    {
        if ($ok) {
            $this->line('  <fg=green>✓</> '.$label);

            return true;
        }

        $this->line('  <fg=red>✗</> '.$label.($hint !== '' ? ' — mettez '.$hint.' dans votre .env' : ''));

        return false;
    }
}
