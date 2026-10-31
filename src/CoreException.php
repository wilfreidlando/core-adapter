<?php

declare(strict_types=1);

namespace CoreAdapter;

use RuntimeException;

/**
 * Le Core a refusé la requête, ou n'a pas répondu. Une seule exception pour
 * tous les appels : le code pose des questions au lieu d'analyser des statuts.
 *
 * ```php
 * try {
 *     $payments->paylink($order, 'order-42');
 * } catch (CoreException $e) {
 *     if ($e->isTransient()) {
 *         // rejouer plus tard, avec la MÊME clé d'idempotence
 *     } elseif ($e->is('insufficient-funds')) {
 *         // le portefeuille n'a pas assez
 *     } else {
 *         // afficher $e->detail
 *     }
 * }
 * ```
 *
 * Le Core répond en RFC 7807 (`application/problem+json`) : son champ `type`
 * est une URL (`https://errors.core-system.internal/quota-exceeded`). On ne
 * garde que le dernier segment — c'est lui que `is()` compare.
 */
final class CoreException extends RuntimeException
{
    /**
     * @param array<string, mixed> $body le `problem+json` décodé (ou [])
     */
    public function __construct(
        public readonly int $status,
        public readonly string $type,
        public readonly string $detail,
        public readonly array $body = [],
        public readonly ?int $retryAfter = null,
        public readonly ?string $correlationId = null,
    ) {
        parent::__construct(sprintf('Core %d %s: %s', $status, $type, $detail));
    }

    /**
     * Le Core n'a pas répondu du tout (DNS, TLS, connexion refusée, délai).
     * Le statut `0` n'existe pas en HTTP : il dit « pas de réponse ».
     */
    public static function unreachable(string $reason): self
    {
        return new self(0, 'unreachable', $reason);
    }

    /**
     * Vaut la peine d'être rejoué plus tard **avec la même clé d'idempotence** :
     * le Core n'a pas répondu (0), était occupé (429, 503, 504) ou a dépassé
     * son délai (408). Un refus de prestataire (502) et tout autre 4xx sont
     * définitifs.
     */
    public function isTransient(): bool
    {
        return in_array($this->status, [0, 408, 429, 503, 504], true);
    }

    /** `is('quota-exceeded')`, `is('validation-failed')`… le dernier segment du `type`. */
    public function is(string $type): bool
    {
        return $this->type === $type;
    }
}
