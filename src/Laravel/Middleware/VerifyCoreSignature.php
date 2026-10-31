<?php

declare(strict_types=1);

namespace SaasCommerce\Laravel\Middleware;

use Closure;
use Illuminate\Http\Request;
use SaasCommerce\Core\Webhooks\InvalidSignatureException;
use SaasCommerce\Core\Webhooks\Receiver;
use SaasCommerce\Laravel\Http\CoreWebhookController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Le garde du point d'entrée : une requête dont la signature ne tient pas
 * n'atteint rien.
 *
 * Le contrôleur sait déjà vérifier seul — le middleware n'est qu'une défense
 * supplémentaire. Il attache l'événement vérifié à la requête, et le
 * contrôleur le récupère.
 */
final class VerifyCoreSignature
{
    public function __construct(private readonly Receiver $receiver) {}

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $event = $this->receiver->accept($request->getContent(), $this->headers($request));
        } catch (InvalidSignatureException) {
            // Le **seul** 4xx à renvoyer : un autre 4xx fait abandonner le
            // Core (file des lettres mortes).
            return new Response('invalid signature', 401);
        }

        // `false` : la signature est bonne, mais la charge utile n'est pas une
        // enveloppe d'événement — le contrôleur répondra `204` sans rien faire.
        $request->attributes->set(CoreWebhookController::EVENT_ATTRIBUTE, $event ?? false);

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
