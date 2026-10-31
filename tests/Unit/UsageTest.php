<?php

declare(strict_types=1);

namespace CoreAdapter\Tests\Unit;

use CoreAdapter\Services\Usage;
use CoreAdapter\Tests\TestCase;

/**
 * L'usage est la seule route dont la clé d'idempotence voyage **dans le
 * corps**. C'est la distinction la plus facile à confondre — le paquet la
 * rend impossible.
 */
final class UsageTest extends TestCase
{
    public function test_the_idempotency_key_travels_in_the_body_and_nowhere_else(): void
    {
        $this->transport->json(201, [
            'status' => 'admitted',
            'meter' => 'students',
            'used' => 13,
            'limit' => 50,
            'remaining' => 37,
            'window_start' => '2026-10-01T00:00:00+00:00',
            'window_end' => '2026-11-01T00:00:00+00:00',
        ]);

        $decision = (new Usage($this->client))->consume('AE0001', 'students', 'inscription:STUDENT-2026-0042');

        $request = $this->transport->lastRequest();

        self::assertSame('https://core.test/v1/usage', $request['url']);
        self::assertArrayNotHasKey('Idempotency-Key', $request['headers']);

        $body = $this->sentBody();

        self::assertSame('inscription:STUDENT-2026-0042', $body['idempotency_key']);
        self::assertSame('AE0001', $body['customer_ref']);
        self::assertSame('students', $body['meter']);
        self::assertSame(1, $body['quantity']);

        self::assertSame('admitted', $decision->status);
        self::assertTrue($decision->isCounted());
        self::assertSame(37, $decision->remaining);
    }

    public function test_a_negative_quantity_releases_a_unit(): void
    {
        $this->transport->json(201, ['status' => 'admitted', 'meter' => 'students', 'used' => 12, 'limit' => 50]);

        (new Usage($this->client))->consume('AE0001', 'students', 'depart:STUDENT-2026-0042', -1);

        self::assertSame(-1, $this->sentBody()['quantity']);
    }
}
