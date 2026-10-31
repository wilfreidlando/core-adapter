<?php

declare(strict_types=1);

namespace SaasCommerce\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use SaasCommerce\Core\CoreClient;
use SaasCommerce\Core\CoreConfig;
use SaasCommerce\Tests\Fakes\FakeTransport;

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
