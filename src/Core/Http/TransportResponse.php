<?php

declare(strict_types=1);

namespace SaasCommerce\Core\Http;

/**
 * La réponse du Core, telle que le transport l'a reçue.
 */
final readonly class TransportResponse
{
    /**
     * @param array<string, list<string>> $headers les en-têtes, en minuscules
     */
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
    ) {}

    /** La première valeur d'un en-tête, ou null. La comparaison ignore la casse. */
    public function header(string $name): ?string
    {
        foreach ($this->headers as $key => $values) {
            if (strcasecmp($key, $name) === 0) {
                return $values[0] ?? null;
            }
        }

        return null;
    }
}
