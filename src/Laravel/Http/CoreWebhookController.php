<?php

declare(strict_types=1);

namespace CoreAdapter\Laravel\Http;

use CoreAdapter\Laravel\Events\CoreEventReceived;
use CoreAdapter\Webhooks\Event;
use CoreAdapter\Webhooks\InvalidSignatureException;
use CoreAdapter\Webhooks\Receiver;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Le point d'entrée unique de tout ce que le Core dit de votre locataire.
 *
 * Il ne décide **rien** de votre métier : il vérifie, dédoublonne, puis
 * transmet à VOS écouteurs via l'événement `CoreEventReceived`.
 *
 * - `204` traité (ou déjà traité) ;
 * - `401` signature invalide — le seul 4xx à renvoyer : les autres font
 *   abandonner le Core (file des lettres morts) ;
 * - une exception levée par un écouteur devient un `500`, et le Core
 *   réessaiera plus tard.
 */
final class CoreWebhookController
{
    /** L'attribut de requête où le middleware dépose l'événement vérifié. */
    public const EVENT_ATTRIBUTE = 'core_adapter.core_event';

    public function __construct(
        private readonly Receiver $receiver,
        private readonly Dispatcher $events,
    ) {}

    public function __invoke(Request $request): Response
    {
        $attribute = $request->attributes->get(self::EVENT_ATTRIBUTE);

        if ($attribute === false) {
            // La signature a été vérifiée, mais la charge utile n'est pas une
            // enveloppe : il n'y a rien à traiter.
            return new Response('', 204);
        }

        if ($attribute instanceof Event) {
            $event = $attribute;
        } else {
            // Le middleware n'a pas été appliqué : on vérifie ici.
            try {
                $event = $this->receiver->accept($request->getContent(), $this->headers($request));
            } catch (InvalidSignatureException) {
                return new Response('invalid signature', 401);
            }
        }

        if (! $event instanceof Event) {
            return new Response('', 204);
        }

        // Livraison au moins une fois : le même `id` ne doit être traité
        // qu'une fois.
        if ($this->receiver->isDuplicate($event)) {
            return new Response('', 204);
        }

        $this->events->dispatch(new CoreEventReceived($event->id, $event->type, $event->data));

        // Marqué **après** les écouteurs : une erreur de traitement doit être
        // rejouée par le Core, pas avalée.
        $this->receiver->markProcessed($event);

        return new Response('', 204);
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
