<?php

declare(strict_types=1);

namespace CoreAdapter\Http;

use RuntimeException;

/**
 * La requête n'est pas partie, ou la réponse n'est pas revenue : c'est un
 * incident de transport, pas un refus du Core.
 *
 * Le client le transforme en `CoreException` de statut `0` — rejouable.
 */
final class TransportException extends RuntimeException
{
    public static function unreachable(string $reason): self
    {
        return new self($reason);
    }
}
