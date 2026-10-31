<?php

declare(strict_types=1);

namespace CoreAdapter\Laravel\Http;

use CoreAdapter\Http\Transport;
use CoreAdapter\Http\TransportException;
use CoreAdapter\Http\TransportResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;

/**
 * Le transport du paquet sur le client HTTP de Laravel.
 *
 * L'application garde la main sur sa configuration HTTP — proxy, TLS,
 * intergiciels globaux, `Http::fake()` dans les tests : le paquet ne
 * s'ouvre pas une porte à côté de la sienne.
 */
final class LaravelTransport implements Transport
{
    public function __construct(
        private readonly Factory $factory,
        private readonly int $timeout = 30,
        private readonly int $connectTimeout = 5,
    ) {}

    public function send(string $method, string $url, array $headers, ?string $body): TransportResponse
    {
        $pending = $this->factory
            ->withHeaders($headers)
            ->connectTimeout($this->connectTimeout)
            ->timeout($this->timeout);

        if ($body !== null) {
            // `withBody` envoie le corps **tel quel** : le paquet a déjà
            // encodé le JSON, et le ré-encoder changerait la charge utile.
            $pending = $pending->withBody($body, 'application/json');
        }

        try {
            $response = $pending->send($method, $url);
        } catch (ConnectionException $e) {
            throw TransportException::unreachable($e->getMessage());
        }

        return new TransportResponse($response->status(), $this->headers($response), $response->body());
    }

    /**
     * @return array<string, list<string>>
     */
    private function headers(Response $response): array
    {
        $headers = [];

        foreach ($response->headers() as $name => $values) {
            $list = [];

            foreach (is_array($values) ? $values : [$values] as $value) {
                $list[] = is_scalar($value) ? (string) $value : '';
            }

            $headers[strtolower((string) $name)] = $list;
        }

        return $headers;
    }
}
