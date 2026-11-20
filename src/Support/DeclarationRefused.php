<?php

declare(strict_types=1);

namespace CoreAdapter\Support;

use RuntimeException;

/**
 * La déclaration ne peut pas être envoyée au Core telle qu'elle est écrite.
 *
 * Le Core refuse une source `declared.<x>` qui ne nomme pas la clé du champ
 * (ADR-0130 §2 côté Core) : le paquet le dit **avant** l'envoi, pour que
 * l'erreur ne soit pas un `422` sans explication.
 */
final class DeclarationRefused extends RuntimeException
{
    public static function becauseSourceDoesNotNameTheField(string $cle, string $source): self
    {
        return new self(sprintf(
            'La source « %s » du champ « %s » ne peut pas être envoyée au Core : '.
            'une source `declared.<clé>` doit nommer la clé du champ — ici « declared.%s ». '.
            'Le Core refuse la déclaration (422 invalid-tenant-schema), et la valeur '.
            'serait de toute façon introuvable.',
            $source,
            $cle,
            $cle,
        ));
    }
}
