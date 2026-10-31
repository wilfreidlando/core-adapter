<?php

declare(strict_types=1);

namespace CoreAdapter\Services;

use CoreAdapter\CoreClient;
use CoreAdapter\Dto\Notification;

/**
 * L'envoi de messages — WhatsApp aujourd'hui.
 *
 * Le contenu est à vous : le Core n'a aucun modèle pré-enregistré, et il y a
 * un point d'entrée par type de contenu. Le numéro du destinataire voyage
 * dans `variables.phone_number`, **jamais** dans `to_party_ref` — qui n'est
 * qu'une référence opaque de VOTRE système.
 *
 * L'envoi est asynchrone : la réponse dit `sent` ou `failed`, et l'état
 * final arrive par l'événement `notification.delivered`, `notification.read`
 * ou `notification.failed`.
 *
 * ⚠️ Ces routes ne portent **pas** de clé d'idempotence en en-tête : le Core
 * les déduplique sur `client_reference`, un champ du corps. Rejouer la même
 * référence ne renvoie pas le premier envoi, elle en fait un nouveau.
 */
final class Notifications
{
    public function __construct(
        private readonly CoreClient $client,
        private readonly ?string $whatsappSession = null,
    ) {}

    /**
     * `POST /v1/notifications/text` — un message.
     *
     * @param string $toPartyRef VOTRE référence du destinataire (jamais un numéro)
     * @param string $phoneNumber le numéro du destinataire, en E.164
     * @param string|null $clientReference votre référence : rejouée, elle ne
     *                                     renvoie pas le premier envoi, elle en fait un nouveau
     */
    public function text(
        string $toPartyRef,
        string $phoneNumber,
        string $text,
        ?string $clientReference = null,
    ): Notification {
        return $this->send('notifications/text', $toPartyRef, $phoneNumber, ['text' => $text], $clientReference);
    }

    /**
     * `POST /v1/notifications/image` — une image.
     *
     * @param string $mediaUrl une URL HTTP(S) **directement téléchargeable** :
     *                         le prestataire la récupère lui-même, sans authentification ni
     *                         protection anti-hotlink
     */
    public function image(
        string $toPartyRef,
        string $phoneNumber,
        string $mediaUrl,
        ?string $clientReference = null,
        ?string $caption = null,
        ?string $filename = null,
        ?string $mimetype = null,
    ): Notification {
        $content = array_filter([
            'media_url' => $mediaUrl,
            'caption' => $caption,
            'filename' => $filename,
            'mimetype' => $mimetype,
        ], static fn (?string $value): bool => $value !== null);

        /** @var array<string, string> $content */
        return $this->send('notifications/image', $toPartyRef, $phoneNumber, $content, $clientReference);
    }

    /**
     * `POST /v1/notifications/file` — un fichier.
     *
     * @param string $mediaUrl une URL HTTP(S) **directement téléchargeable**
     * @param string $filename le nom que le destinataire voit
     * @param string $mimetype le type MIME (`application/pdf`, `image/png`…)
     */
    public function file(
        string $toPartyRef,
        string $phoneNumber,
        string $mediaUrl,
        string $filename,
        string $mimetype,
        ?string $clientReference = null,
        ?string $caption = null,
    ): Notification {
        $content = array_filter([
            'media_url' => $mediaUrl,
            'filename' => $filename,
            'mimetype' => $mimetype,
            'caption' => $caption,
        ], static fn (?string $value): bool => $value !== null);

        /** @var array<string, string> $content */
        return $this->send('notifications/file', $toPartyRef, $phoneNumber, $content, $clientReference);
    }

    /**
     * `GET /v1/notifications/{id}` — l'état courant d'une notification.
     */
    public function status(string $notificationId): Notification
    {
        $payload = $this->client->get('notifications/'.rawurlencode($notificationId));

        return Notification::fromArray($payload);
    }

    /**
     * @param array<string, string> $content
     */
    private function send(
        string $path,
        string $toPartyRef,
        string $phoneNumber,
        array $content,
        ?string $clientReference,
    ): Notification {
        $body = array_merge([
            'channel' => 'whatsapp',
            'to_party_ref' => $toPartyRef,
            'variables' => $this->variables($phoneNumber),
        ], $content);

        if ($clientReference !== null) {
            $body['client_reference'] = $clientReference;
        }

        $payload = $this->client->post($path, $body);

        return Notification::fromArray($payload);
    }

    /**
     * Les variables de canal. La session WhatsApp est celle que l'opérateur
     * du Core a liée au projet : sans elle, le Core répond `422
     * channel-session-not-found` et n'envoie rien.
     *
     * @return array<string, string>
     */
    private function variables(string $phoneNumber): array
    {
        $variables = ['phone_number' => $phoneNumber];

        if ($this->whatsappSession !== null && $this->whatsappSession !== '') {
            $variables['session'] = $this->whatsappSession;
        }

        return $variables;
    }
}
