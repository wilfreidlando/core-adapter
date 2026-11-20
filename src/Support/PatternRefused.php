<?php

declare(strict_types=1);

namespace CoreAdapter\Support;

use RuntimeException;

/**
 * Le motif de la clé unique ne peut pas être envoyé au Core.
 *
 * L'échec est **local** et **nommé** : le paquet refuse d'envoyer un motif
 * que le Core refuse, plutôt que d'attendre un `422` muet. Le message dit
 * ce qui ne va pas et ce qu'il faut faire.
 */
final class PatternRefused extends RuntimeException
{
    /**
     * @param string $applicatif le motif de l'application, tel qu'il est écrit
     * @param string $raison ce qui empêche le Core de l'accepter
     */
    public static function because(string $applicatif, string $raison): self
    {
        return new self(sprintf(
            'Le motif « %s » ne peut pas être envoyé au Core : %s. '.
            'Le Core n\'accepte qu\'un motif **nu**, dans l\'alphabet A-Za-z0-9._:-, '.
            'avec `^` en tête et `$` en fin ; le motif de l\'application, lui, reste intact '.
            'pour la validation locale de la référence.',
            $applicatif,
            $raison,
        ));
    }
}
