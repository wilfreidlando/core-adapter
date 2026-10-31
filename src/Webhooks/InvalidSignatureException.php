<?php

declare(strict_types=1);

namespace CoreAdapter\Webhooks;

use RuntimeException;

/**
 * La signature d'un événement ne tient pas : absente, fausse, ou trop
 * vieille. C'est le **seul** cas où l'application doit répondre `401` — un
 * autre 4xx fait abandonner le Core (file des lettres mortes).
 */
final class InvalidSignatureException extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
