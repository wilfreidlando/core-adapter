<?php

declare(strict_types=1);

namespace CoreAdapter\Tests\Fakes;

use CoreAdapter\Http\Transport;
use CoreAdapter\Http\TransportResponse;
use LogicException;

/**
 * Un transport qui ne sort jamais de la machine : il enregistre les requêtes
 * et rend les réponses qu'on lui a préparées.
 *
 * Les tests du paquet ne touchent donc **aucun réseau** — c'est ce qui les
 * rend rapides, et ce qui les rend fiables.
 */
final class FakeTransport implements Transport
{
    /** @var list<array{method: string, url: string, headers: array<string, string>, body: ?string}> */
    private array $requests = [];

    /** @var list<TransportResponse> */
    private array $responses = [];

    /**
     * @param list<TransportResponse> $responses
     */
    public function __construct(array $responses = [])
    {
        $this->responses = $responses;
    }

    public function push(TransportResponse $response): self
    {
        $this->responses[] = $response;

        return $this;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, list<string>> $headers
     */
    public function json(int $status, array $payload, array $headers = []): self
    {
        return $this->push(new TransportResponse(
            $status,
            $headers,
            (string) json_encode($payload, JSON_UNESCAPED_UNICODE),
        ));
    }

    public function send(string $method, string $url, array $headers, ?string $body): TransportResponse
    {
        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
        ];

        $response = array_shift($this->responses);

        if (! $response instanceof TransportResponse) {
            return new TransportResponse(200, [], '{}');
        }

        return $response;
    }

    /**
     * @return array{method: string, url: string, headers: array<string, string>, body: ?string}
     */
    public function lastRequest(): array
    {
        $index = count($this->requests) - 1;

        if ($index < 0) {
            throw new LogicException('No request has been sent.');
        }

        return $this->requests[$index];
    }

    /**
     * @return list<array{method: string, url: string, headers: array<string, string>, body: ?string}>
     */
    public function requests(): array
    {
        return $this->requests;
    }
}
