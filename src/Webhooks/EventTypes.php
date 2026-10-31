<?php

declare(strict_types=1);

namespace CoreAdapter\Webhooks;

/**
 * Les événements du Core que le paquet connaît par leur nom.
 *
 * Le Core en émet bien d'autres (`payment.status_changed`, `wallet.credited`…) :
 * ils sont listés dans la documentation du Core. Le paquet n'en nomme ici que
 * ceux dont une application cliente a besoin pour vivre — en particulier
 * `customer.created`, le signal qui dit « ton locataire existe ».
 */
final class EventTypes
{
    /** Le locataire existe : l'application crée (ou lie) son compte local. */
    public const CUSTOMER_CREATED = 'customer.created';

    /** L'identité du locataire a changé. */
    public const CUSTOMER_UPDATED = 'customer.updated';

    /** Le locataire est bloqué. */
    public const CUSTOMER_SUSPENDED = 'customer.suspended';

    /** Le locataire est rétabli. */
    public const CUSTOMER_REACTIVATED = 'customer.reactivated';

    /** Le locataire a été effacé définitivement. */
    public const CUSTOMER_ERASED = 'customer.erased';

    /** Un abonnement commence, change ou se termine. */
    public const SUBSCRIPTION = 'subscription';

    /** Une facture est émise, payée, échouée ou annulée. */
    public const INVOICE = 'invoice';

    /** Un quota est bientôt atteint, ou atteint. */
    public const USAGE = 'usage';
}
