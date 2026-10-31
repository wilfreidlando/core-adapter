<?php

declare(strict_types=1);

namespace CoreAdapter\Http;

/**
 * La porte de sortie HTTP du paquet — la seule.
 *
 * Le noyau ne sait pas *comment* une requête part : il sait qu'il y en a une.
 * L'implémentation par défaut (curl) est `CurlTransport` ; l'adaptateur
 * Laravel fournit la sienne, pour benefiting de la configuration HTTP de
 * l'application.
 */
interface Transport
{
    /**
     * @param array<string, string> $headers les en-têtes, déjà complets
     * @param string|null $body le corps BRUT (JSON encodé), ou null
     */
    public function send(string $method, string $url, array $headers, ?string $body): TransportResponse;
}
