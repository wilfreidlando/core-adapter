<?php

declare(strict_types=1);

namespace CoreAdapter\Tests\Unit;

use CoreAdapter\Services\Payments;
use CoreAdapter\Tests\TestCase;
use InvalidArgumentException;

/**
 * La porte HTTP : le jeton, le `Accept`, et la clé d'idempotence.
 */
final class CoreClientTest extends TestCase
{
    public function test_it_sends_the_bearer_token_and_accepts_json(): void
    {
        $this->transport->json(200, ['project_id' => '01a101a1', 'scopes' => []]);

        $this->client->get('whoami');

        $request = $this->transport->lastRequest();

        self::assertSame('GET', $request['method']);
        self::assertSame('https://core.test/v1/whoami', $request['url']);
        self::assertSame('Bearer pk_test.0123456789abcdef', $request['headers']['Authorization']);
        self::assertSame('application/json', $request['headers']['Accept']);
    }

    public function test_a_read_carries_no_idempotency_key(): void
    {
        $this->transport->json(200, ['id' => 'pay_1']);

        $this->client->get('payments/pay_1');

        self::assertArrayNotHasKey('Idempotency-Key', $this->transport->lastRequest()['headers']);
    }

    public function test_a_money_route_carries_the_idempotency_key_in_the_header(): void
    {
        $this->transport->json(201, [
            'id' => 'pay_1',
            'status' => 'pending',
            'is_final' => false,
            'amount' => 5000,
            'currency' => 'XAF',
            'channel' => 'mtn_momo',
            'created_at' => '2026-10-31T09:00:00+00:00',
        ]);

        $payment = (new Payments($this->client))->paylink([
            'channel' => 'mtn_momo',
            'amount' => 5000,
            'currency' => 'XAF',
            'reason' => 'Commande #42',
        ], 'order-42');

        $request = $this->transport->lastRequest();

        self::assertSame('order-42', $request['headers']['Idempotency-Key']);
        self::assertSame('application/json', $request['headers']['Content-Type']);
        self::assertArrayNotHasKey('idempotency_key', $this->sentBody());
        self::assertSame('pay_1', $payment->id);
    }

    public function test_the_client_refuses_to_mix_the_two_idempotency_mechanisms(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('travels in the "Idempotency-Key" header');

        // Une clé en en-tête ET dans le corps : la confusion est impossible,
        // elle est refusée.
        $this->client->postIdempotent('payments/paylink', ['idempotency_key' => 'order-42'], 'order-42');
    }
}
