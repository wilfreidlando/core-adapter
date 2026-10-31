<?php

declare(strict_types=1);

namespace SaasCommerce\Core\Contracts;

/**
 * L'endroit où l'on retient les événements déjà traités.
 *
 * Le Core livre **au moins une fois** : le même événement peut arriver deux
 * fois (un `2xx` perdu sur le réseau, un rejeu par l'opérateur). L'`id` de
 * l'événement est la clé de dédoublonnage — il faut donc pouvoir le
 * remember.
 */
interface EventStore
{
    /** L'événement a-t-il déjà été traité ? */
    public function has(string $eventId): bool;

    /** Retient l'événement comme traité. */
    public function remember(string $eventId): void;
}
