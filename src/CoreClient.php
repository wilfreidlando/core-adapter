<?php

declare(strict_types=1);

namespace CoreAdapter;

use CoreAdapter\Http\Transport;
use CoreAdapter\Http\TransportException;
use CoreAdapter\Http\TransportResponse;
use CoreAdapter\Support\Payload;
use InvalidArgumentException;

/**
 * La porte unique vers le Core : une clé, du JSON, une seule erreur.
 *
 * - Chaque appel porte le jeton (`Authorization: Bearer`) et
 *   `Accept: application/json`.
 * - Les routes qui touchent à l'argent portent une clé d'idempotence **en
 *   en-tête** (`Idempotency-Key`). `POST /v1/usage` est **la seule exception** :
 *   sa clé voyage **dans le corps**, sous `idempotency_key`, et nulle part
 *   ailleurs. La distinction est rendue impossible à confondre par la forme
 *   des méthodes : `post()` n'envoie jamais de clé en en-tête, et
 *   `postIdempotent()` l'exige et **refuse** un corps qui en porte déjà une.
 * - Seuls les appels rejouables — ceux qui portent une clé — sont rejoués
 *   après une panne de connexion : rejouer les autres pourrait faire la
 *   chose deux fois.
 */
final class CoreClient
{
    /** Le champ qui porte la clé d'idempotence dans le corps de `POST /v1/usage`. */
    public const IDEMPOTENCY_KEY_FIELD = 'idempotency_key';

    /** Le nombre total d'essais d'un appel rejouable. */
    private const MAX_ATTEMPTS = 3;

    /** L'attente entre deux essais, en microsecondes (multipliée par l'essai). */
    private const RETRY_DELAY_US = 200_000;

    public function __construct(
        private readonly CoreConfig $config,
        private readonly Transport $transport,
    ) {}

    /**
     * Un GET. Les paramètres de requête sont encodés dans l'URL.
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        $url = $this->config->endpoint($path);

        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        return $this->send('GET', $url, null, []);
    }

    /**
     * Un POST **sans** clé d'idempotence en en-tête.
     *
     * C'est la forme des routes qui n'en portent pas : `POST /v1/usage` (qui
     * porte la sienne dans le corps) et `POST /v1/portal/sessions` (qui ne
     * touche à rien).
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function post(string $path, array $body = []): array
    {
        return $this->send('POST', $this->config->endpoint($path), $this->encode($body), []);
    }

    /**
     * Un PUT — la forme des routes qui remplacent une ressource.
     *
     * La déclaration de la forme du locataire passe par là : elle décrit
     * une forme, elle ne touche ni à l'argent ni au commerce, donc sans clé
     * d'idempotence.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function put(string $path, array $body = []): array
    {
        return $this->send('PUT', $this->config->endpoint($path), $this->encode($body), []);
    }

    /**
     * Un POST **avec** la clé d'idempotence en en-tête — la forme des routes
     * qui touchent à l'argent.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function postIdempotent(string $path, array $body, string $idempotencyKey): array
    {
        if (array_key_exists(self::IDEMPOTENCY_KEY_FIELD, $body)) {
            throw new InvalidArgumentException(sprintf(
                'The idempotency key of "%s" travels in the "Idempotency-Key" header, not in the body.',
                $path,
            ));
        }

        $headers = ['Idempotency-Key' => $idempotencyKey];

        // Le Core n'accepte qu'un identifiant « sûr » en `Correlation-Id` ; une
        // clé d'idempotence n'en est pas forcément un (elle peut être longue,
        // ou porter des caractères exotiques). Dans ce cas on n'envoie rien :
        // le Core en génère un.
        if (preg_match('/^[A-Za-z0-9._:-]{1,64}$/', $idempotencyKey) === 1) {
            $headers['Correlation-Id'] = $idempotencyKey;
        }

        return $this->send('POST', $this->config->endpoint($path), $this->encode($body), $headers);
    }

    /**
     * @param array<string, string> $extraHeaders
     * @return array<string, mixed>
     */
    private function send(string $method, string $url, ?string $body, array $extraHeaders): array
    {
        $headers = array_merge([
            'Authorization' => 'Bearer '.$this->config->apiKey,
            'Accept' => 'application/json',
        ], $extraHeaders);

        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
        }

        // Seuls les appels qui portent une clé d'idempotence sont rejouables :
        // les autres feraient la chose deux fois.
        $retryable = isset($headers['Idempotency-Key']);
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                $response = $this->transport->send($method, $url, $headers, $body);
            } catch (TransportException $e) {
                if (! $retryable || $attempt >= self::MAX_ATTEMPTS) {
                    throw CoreException::unreachable($e->getMessage());
                }

                usleep(self::RETRY_DELAY_US * $attempt);

                continue;
            }

            if ($response->status >= 200 && $response->status < 300) {
                return $this->decode($response->body, $response->status);
            }

            throw $this->failure($response);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $body, int $status): array
    {
        if (trim($body) === '') {
            return [];
        }

        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            throw new CoreException(
                $status,
                'invalid-response',
                'The Core answered with a body that is not a JSON object.',
            );
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * Le refus du Core, en RFC 7807 (`application/problem+json`).
     */
    private function failure(TransportResponse $response): CoreException
    {
        $decoded = json_decode($response->body, true);

        if (! is_array($decoded)) {
            $decoded = [];
        }

        /** @var array<string, mixed> $decoded */
        $body = $decoded;

        $rawType = $body['type'] ?? null;
        $type = is_string($rawType) && $rawType !== ''
            ? (string) preg_replace('#^.*/#', '', $rawType)
            : 'http-'.$response->status;

        $detail = Payload::string($body, 'detail')
            ?? Payload::string($body, 'title')
            ?? sprintf('The Core answered %d.', $response->status);

        $retryAfter = $response->header('Retry-After');

        return new CoreException(
            $response->status,
            $type,
            $detail,
            $body,
            is_numeric($retryAfter) ? (int) $retryAfter : null,
            Payload::string($body, 'correlation_id'),
        );
    }

    /**
     * @param array<string, mixed> $body
     */
    private function encode(array $body): string
    {
        $encoded = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (! is_string($encoded)) {
            throw new InvalidArgumentException('The body cannot be encoded as JSON: '.json_last_error_msg());
        }

        return $encoded;
    }
}
