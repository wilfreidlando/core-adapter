<?php

declare(strict_types=1);

namespace CoreAdapter\Laravel\Middleware;

use Closure;
use CoreAdapter\Webhooks\Signature;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Le garde des lectures que le Core vient chercher.
 *
 * C'est le **même** schéma de signature que celui des événements : le Core
 * signe le corps brut — `HMAC-SHA256(timestamp + "." + corps)` — et pose
 * `X-Signature` et `X-Timestamp`. Rien de nouveau n'est inventé pour ces
 * routes : le secret est celui du point d'entrée, que les deux parties ont
 * déjà.
 *
 * La différence avec le garde des événements : ici, la charge utile n'est pas
 * une enveloppe d'événement. C'est **rien** (une lecture sans paramètre) ou
 * **un corps JSON** (la page demandée). Le garde vérifie donc la signature
 * seule, et laisse la charge utile à qui la lit.
 *
 * Pendant une rotation de secret, le Core envoie deux signatures :
 * `X-Signature` et `X-Signature-Previous`. Les deux sont acceptées, comme
 * pour les événements : la rotation ne coupe pas le branchement.
 *
 * - `401` signature invalide — le seul 4xx à renvoyer ;
 * - `403` le secret n'est pas configuré : le paquet ne peut rien vérifier,
 *   et il ne laisse pas passer.
 */
final class VerifyCoreReadSignature
{
    public function __construct(
        private readonly Signature $signature,
        private readonly string $secret,
    ) {}

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Sans secret configuré, toute signature serait acceptée : on ferme.
        if ($this->secret === '') {
            return new Response('signature not configured', 403);
        }

        $headers = $this->headers($request);

        $timestamp = $headers['x-timestamp'] ?? '';
        $signatures = array_values(array_filter([
            $headers['x-signature'] ?? '',
            $headers['x-signature-previous'] ?? '',
        ], static fn (string $valeur): bool => $valeur !== ''));

        if (! $this->signature->verify($request->getContent(), $timestamp, $signatures, $this->secret)) {
            return new Response('invalid signature', 401);
        }

        return $next($request);
    }

    /**
     * @return array<string, string>
     */
    private function headers(Request $request): array
    {
        $headers = [];

        foreach ($request->headers->all() as $name => $values) {
            $first = $values[0] ?? null;

            $headers[strtolower((string) $name)] = $first === null ? '' : $first;
        }

        return $headers;
    }
}
