<?php

declare(strict_types=1);

namespace CoreAdapter\Http;

use CurlHandle;

/**
 * Le transport par défaut : curl, sans une seule dépendance.
 *
 * `ext-curl` est la seule extension requise — elle est présente dans la
 * plupart des installations de PHP. Une application Laravel n'utilise pas ce
 * transport : l'adaptateur fournit le sien, et l'application garde ainsi la
 * main sur sa configuration HTTP.
 */
final class CurlTransport implements Transport
{
    public function __construct(
        private readonly int $timeout = 30,
        private readonly int $connectTimeout = 5,
    ) {}

    public function send(string $method, string $url, array $headers, ?string $body): TransportResponse
    {
        $handle = curl_init();

        if (! $handle instanceof CurlHandle) {
            throw TransportException::unreachable('curl could not be initialised.');
        }

        /** @var array<string, list<string>> $responseHeaders */
        $responseHeaders = [];

        if ($url === '' || $method === '') {
            throw TransportException::unreachable('The Core URL and the HTTP method are required.');
        }

        curl_setopt($handle, CURLOPT_URL, $url);
        curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        // Une redirection n'est pas suivie : le Core ne redirige pas, et un
        // `POST` suivi en `GET` perdrait le corps.
        curl_setopt($handle, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($handle, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($handle, CURLOPT_HTTPHEADER, $this->headerLines($headers));
        curl_setopt(
            $handle,
            CURLOPT_HEADERFUNCTION,
            function (CurlHandle $handle, string $line) use (&$responseHeaders): int {
                $length = strlen($line);
                $parts = explode(':', $line, 2);

                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))][] = trim($parts[1]);
                }

                return $length;
            },
        );

        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($handle);
        $errno = curl_errno($handle);
        $error = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        curl_close($handle);

        if (! is_string($raw) || $errno !== 0) {
            throw TransportException::unreachable($error !== '' ? $error : 'The Core did not answer.');
        }

        return new TransportResponse($status, $responseHeaders, $raw);
    }

    /**
     * @param array<string, string> $headers
     * @return list<string>
     */
    private function headerLines(array $headers): array
    {
        $lines = [];

        foreach ($headers as $name => $value) {
            $lines[] = $name.': '.$value;
        }

        return $lines;
    }
}
