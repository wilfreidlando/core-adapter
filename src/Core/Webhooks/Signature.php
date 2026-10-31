<?php

declare(strict_types=1);

namespace SaasCommerce\Core\Webhooks;

/**
 * La vérification de la signature d'un événement.
 *
 * Le Core signe **le corps brut** — `HMAC-SHA256(timestamp + "." + corps)`,
 * en hexadécimal minuscule — et l'envoie dans `X-Signature`. L'horodatage
 * (`X-Timestamp`) est signé avec, et borné à une tolérance de 300 secondes :
 * sans cela, un message intercepté pourrait être rejoué indéfiniment.
 *
 * Pendant une rotation de secret, le Core envoie **deux** signatures :
 * `X-Signature` (le secret nouveau) et `X-Signature-Previous` (l'ancien, encore
 * valable le temps du recouvrement). Les deux sont acceptées, et la bascule
 * se fait sans rejet.
 */
final class Signature
{
    public function __construct(private readonly int $toleranceSeconds = 300) {}

    /**
     * @param list<string> $signatures les signatures reçues (`X-Signature`, et `X-Signature-Previous` pendant une rotation)
     */
    public function verify(string $rawBody, string $timestamp, array $signatures, string $secret, ?int $now = null): bool
    {
        // À secret vide, n'importe qui calculerait une signature valide :
        // on ferme au lieu d'ouvrir.
        if ($secret === '' || ! ctype_digit($timestamp)) {
            return false;
        }

        $current = $now ?? time();

        if (abs($current - (int) $timestamp) > $this->toleranceSeconds) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);

        foreach ($signatures as $signature) {
            // `hash_equals` compare en temps constant : la durée de la
            // comparaison ne doit rien révéler de la signature attendue.
            if ($signature !== '' && hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }
}
