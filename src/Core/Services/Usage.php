<?php

declare(strict_types=1);

namespace SaasCommerce\Core\Services;

use SaasCommerce\Core\CoreClient;
use SaasCommerce\Core\Dto\UsageDecision;

/**
 * Le comptage de l'usage.
 *
 * Votre application **déclare** ce qu'elle a consommé ; le Core compte et
 * applique la limite. Vous n'avez pas de compteur à tenir.
 *
 * La clé d'idempotence voyage **dans le corps** (`idempotency_key`) : c'est
 * la seule route du Core où elle n'est pas un en-tête, et le paquet rend la
 * confusion impossible — `Usage::consume()` construit lui-même le corps, et
 * le client refuse d'envoyer une clé en en-tête quand le corps en porte
 * déjà une.
 */
final class Usage
{
    public function __construct(private readonly CoreClient $client) {}

    /**
     * `POST /v1/usage` — compter des unités.
     *
     * @param string $idempotencyKey une valeur **stable** qui identifie le
     *                               fait (`inscription:STUDENT-2026-0042`), jamais une valeur
     *                               aléatoire : rejouer la même clé ne compte pas deux fois. Une clé
     *                               par fait, jamais par requête.
     * @param int $quantity les unités dépensées ; une quantité négative
     *                      relâche un compteur (un élève part), zéro n'a pas de sens
     * @param string|null $occurredAt quand l'usage a eu lieu (RFC 3339) ; par défaut, maintenant
     * @param string $mode `enforce` (défaut) refuse au-delà du plan ;
     *                     `record` constate un fait déjà passé, et l'écrit même au-delà
     */
    public function consume(
        string $customerRef,
        string $meter,
        string $idempotencyKey,
        int $quantity = 1,
        ?string $occurredAt = null,
        string $mode = 'enforce',
    ): UsageDecision {
        $body = [
            'customer_ref' => $customerRef,
            'meter' => $meter,
            'quantity' => $quantity,
            CoreClient::IDEMPOTENCY_KEY_FIELD => $idempotencyKey,
        ];

        if ($occurredAt !== null) {
            $body['occurred_at'] = $occurredAt;
        }

        if ($mode !== 'enforce') {
            $body['mode'] = $mode;
        }

        $payload = $this->client->post('usage', $body);

        return UsageDecision::fromArray($payload);
    }
}
