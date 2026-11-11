<?php

declare(strict_types=1);

namespace CoreAdapter\Laravel\Tenant;

use CoreAdapter\CoreException;
use CoreAdapter\Dto\Customer;
use CoreAdapter\Services\Customers;
use DomainException;
use Illuminate\Bus\Queueable;
use Illuminate\Container\Container;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * Crée le locataire que le Core vient de créer.
 *
 * Le Core est le maître : c'est lui qui crée le client (l'opérateur, depuis la
 * console du Core) et qui le pousse (`customer.created`). L'application crée
 * alors son propre locataire, avec sa fabrique.
 *
 * **Pourquoi une tâche de fond, et pas l'écouteur lui-même** : l'écouteur ne
 * fait aucun appel réseau — c'est une règle du paquet : « l'événement est
 * traité, le travail part en file » — et le Core attend une réponse en moins
 * de dix secondes. Lire le client chez le Core, écrire le locataire et
 * envoyer un message ne rentrait pas dans ce délai.
 *
 * **L'idempotence est métier, pas transport** : la référence du Core est
 * unique, et l'application doit faire de même. Un événement rejoué, ou deux
 * événements portant la même référence, ne donnent qu'un locataire.
 */
final class ProvisionsTenant implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $backoff = 30;

    /** Un seul provisionnement par référence à la fois. */
    public int $uniqueFor = 600;

    public function __construct(
        public readonly string $reference,
        public readonly string $eventId,
    ) {}

    /**
     * Met la tâche en file.
     *
     * Le paquet ne demande que le **contrat** du repartiteur de commandes
     * (`Illuminate\Contracts\Bus\Dispatcher`), que toute application Laravel
     * fournit : il n'exige ni `laravel/framework`, ni `illuminate/queue`.
     *
     * @return mixed ce que fait le repartiteur — sans importance ici
     */
    public static function dispatch(string $reference, string $eventId): mixed
    {
        $dispatcher = Container::getInstance()->make(Dispatcher::class);

        return $dispatcher->dispatch(new self($reference, $eventId));
    }

    /**
     * La référence est l'identifiant du provisionnement : deux provisionnements
     * de la même référence ne tournent pas en parallèle.
     */
    public function uniqueId(): string
    {
        return $this->reference;
    }

    public function handle(
        Customers $clients,
        TenantFactory $fabrique,
        TenantDeclaration $declaration,
    ): void {
        // 1. Le locataire existe déjà : on le lie, et c'est tout.
        $existant = $fabrique->trouver($this->reference);

        if ($existant !== null) {
            $fabrique->lier($existant, $this->reference);

            return;
        }

        // 2. La référence est-elle une clé que l'application peut employer ?
        //    Sinon le locataire ne serait pas joignable — et l'erreur doit
        //    arriver à l'opérateur, pas dans la file du worker.
        $cle = $declaration->cleUnique();

        if (@preg_match($cle['motif'], $this->reference) !== 1) {
            Log::warning('Référence de locataire hors format : locataire non créé', [
                'evenement' => $this->eventId,
                'reference' => $this->reference,
                'attribut' => $cle['attribut'],
            ]);

            return;
        }

        // 3. Lire l'identité du locataire chez le Core — le seul appel réseau,
        //    et il est ici, dans le worker, jamais dans un écouteur.
        try {
            $client = $clients->get($this->reference);
        } catch (CoreException $e) {
            if ($e->isTransient()) {
                // Le Core n'a pas répondu : la tâche est rejouée plus tard.
                throw $e;
            }

            Log::warning('Le Core ne connaît pas ce locataire : locataire non créé', [
                'evenement' => $this->eventId,
                'reference' => $this->reference,
                'statut' => $e->status,
                'type' => $e->type,
            ]);

            return;
        }

        // 4. Créer le locataire.
        try {
            $locataire = $fabrique->creer(
                $this->reference,
                $this->attributs($declaration, $client)
            );
        } catch (DomainException $e) {
            // Les données sont inexploitables — pas de raison sociale, pas
            // d'e-mail : la situation ne changera pas d'elle-même. La tâche
            // s'arrête ici ; un échec de base, lui, est rejoué.
            Log::warning('Le locataire n’a pas pu être créé : données inexploitables', [
                'evenement' => $this->eventId,
                'reference' => $this->reference,
                'raison' => $e->getMessage(),
            ]);

            return;
        }

        Event::dispatch(new TenantProvisioned($this->reference, $locataire, $client->id));
    }

    /**
     * La valeur de chaque clé déclarée.
     *
     * Une source inconnue ne vaut rien : la clé est simplement absente des
     * attributs, et l'application décide de ce qu'elle en fait.
     *
     * @return array<string, mixed>
     */
    private function attributs(TenantDeclaration $declaration, Customer $client): array
    {
        $attributs = [];

        foreach ($declaration->champs() as $champ) {
            $valeur = $this->valeur($champ['source'], $client);

            if ($valeur !== null) {
                $attributs[$champ['cle']] = $valeur;
            }
        }

        return $attributs;
    }

    /**
     * La valeur d'une source.
     */
    private function valeur(string $source, Customer $client): mixed
    {
        return match ($source) {
            'core_identity.display_name' => $client->displayName,
            'core_identity.email' => $client->email,
            'core_identity.phone' => $client->phone,
            'core_identity.locale' => $client->locale,
            // `declared.<clé>` : la valeur vient des attributs déclarés par le
            // projet au Core. Tant que le Core ne les connaît pas, il n'y en a
            // pas — l'application les réclame alors ailleurs.
            default => null,
        };
    }
}
