<?php

declare(strict_types=1);

namespace CoreAdapter\Laravel\Listeners;

use CoreAdapter\Laravel\Events\CoreEventReceived;
use CoreAdapter\Laravel\Tenant\ProvisionsTenant;
use Illuminate\Support\Facades\Log;

/**
 * Un client existe chez le Core : l'application crée son locataire.
 *
 * L'événement `customer.created` dit « ce client vient d'exister » ;
 * `customer.updated` dit « ce client existe toujours » — c'est la reprise :
 * un client créé sans e-mail (le Core l'autorise) n'a pu donner aucun
 * administrateur, et l'opérateur qui renseigne l'e-mail plus tard doit
 * voir l'application rattraper le pas.
 *
 * L'écouteur ne fait rien d'autre que **confier le travail** : il ne touche
 * ni la base, ni le réseau. La création est une tâche de fond — le Core
 * attend une réponse en moins de dix secondes, et lire le client chez le Core
 * puis écrire le locataire n'y tient pas.
 *
 * C'est le seul endroit où le paquet décide qu'un événement client a une
 * conséquence : une application qui ne veut pas de ce comportement n'a qu'à
 * ne pas fournir de `TenantFactory` — la tâche échoue, et rien n'est créé.
 */
final class CustomerEventProvisionsTenant
{
    public function __invoke(CoreEventReceived $event): void
    {
        if (! in_array($event->type, ['customer.created', 'customer.updated'], true)) {
            return;
        }

        $reference = $event->customerRef();

        if ($reference === null) {
            Log::warning('Événement du Core sans référence de locataire', [
                'evenement' => $event->id,
                'type' => $event->type,
            ]);

            return;
        }

        ProvisionsTenant::dispatch($reference, $event->id);
    }
}
