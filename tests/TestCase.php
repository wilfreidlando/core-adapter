<?php

declare(strict_types=1);

namespace CoreAdapter\Tests;

use CoreAdapter\CoreClient;
use CoreAdapter\CoreConfig;
use CoreAdapter\Tests\Fakes\FakeTransport;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Le socle des tests : un client branché sur un faux transport.
 */
abstract class TestCase extends BaseTestCase
{
    protected FakeTransport $transport;

    protected CoreClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transport = new FakeTransport;
        $this->client = new CoreClient(
            new CoreConfig('https://core.test', 'pk_test.0123456789abcdef'),
            $this->transport,
        );
    }

    /**
     * Le corps JSON de la dernière requête, décodé.
     *
     * @return array<string, mixed>
     */
    protected function sentBody(): array
    {
        $decoded = json_decode((string) $this->transport->lastRequest()['body'], true);

        if (! is_array($decoded)) {
            return [];
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
