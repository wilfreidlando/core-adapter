<?php

declare(strict_types=1);

namespace SaasCommerce\Core;

/**
 * Les noms de méthodes que le paquet **ne doit jamais** exposer.
 *
 * Le Core est le maître de la relation commerciale (ADR-0107) : une
 * application cliente lit ses droits, compte son usage, ouvre son portail et
 * reçoit ses événements — elle ne fait **aucun** commerce. Cette liste est
 * la forme vérifiable de cette interdiction : la suite de tests parcourt
 * toutes les classes du paquet et échoue si l'une d'elles expose un de ces
 * noms.
 *
 * ⚠️ Ces noms n'apparaissent **que** ici, comme liste d'interdiction. Le
 * paquet ne contient ni route, ni appel, ni charge utile de commerce : les
 * offres, les abonnements, les factures et les points d'entrée webhook se
 * gèrent depuis la console du Core, ou dans le portail, par le propriétaire.
 */
final class ForbiddenMethods
{
    /**
     * @var list<string>
     */
    public const NAMES = [
        'createCustomer',
        'deleteCustomer',
        'subscribe',
        'createInvoice',
        'issueInvoice',
        'createPlan',
        'publishPlan',
        'registerWebhookEndpoint',
    ];
}
