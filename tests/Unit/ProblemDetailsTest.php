<?php

declare(strict_types=1);

namespace CoreAdapter\Tests\Unit;

use CoreAdapter\CoreException;
use CoreAdapter\Http\TransportResponse;
use CoreAdapter\Services\Usage;
use CoreAdapter\Tests\TestCase;

/**
 * Le refus du Core, en RFC 7807, devient une `CoreException` qui dit ce qu'il
 * faut savoir : le type, le détail, l'identifiant de corrélation.
 */
final class ProblemDetailsTest extends TestCase
{
    public function test_a_problem_json_becomes_a_core_exception(): void
    {
        $this->transport->push(new TransportResponse(
            429,
            ['content-type' => ['application/problem+json']],
            (string) json_encode([
                'type' => 'https://errors.core-system.internal/quota-exceeded',
                'title' => 'Quota exceeded',
                'status' => 429,
                'detail' => 'The plan allows 50 unit(s) of "students" in this window; 50 already used.',
                'correlation_id' => '01a101a1-7b88-7275-962b-935f19a9126d',
            ]),
        ));

        try {
            (new Usage($this->client))->consume('AE0001', 'students', 'inscription:1');

            self::fail('Le refus du Core doit lever une exception.');
        } catch (CoreException $e) {
            self::assertSame(429, $e->status);
            self::assertSame('quota-exceeded', $e->type);
            self::assertStringContainsString('The plan allows 50 unit(s)', $e->detail);
            self::assertSame('01a101a1-7b88-7275-962b-935f19a9126d', $e->correlationId);
            self::assertTrue($e->is('quota-exceeded'));
            self::assertFalse($e->is('insufficient-funds'));
            // 429 est rejouable — mais avec la même clé.
            self::assertTrue($e->isTransient());
        }
    }

    public function test_a_refusal_that_is_not_transient_is_not_transient(): void
    {
        $this->transport->push(new TransportResponse(
            403,
            [],
            (string) json_encode([
                'type' => 'https://errors.core-system.internal/forbidden',
                'title' => 'Forbidden',
                'status' => 403,
                'detail' => 'The usage:write scope is required.',
            ]),
        ));

        try {
            (new Usage($this->client))->consume('AE0001', 'students', 'inscription:1');

            self::fail('Le refus du Core doit lever une exception.');
        } catch (CoreException $e) {
            self::assertSame(403, $e->status);
            self::assertSame('forbidden', $e->type);
            self::assertSame('The usage:write scope is required.', $e->detail);
            self::assertFalse($e->isTransient());
        }
    }

    public function test_an_unreachable_core_is_transient(): void
    {
        $exception = CoreException::unreachable('Could not resolve host');

        self::assertSame(0, $exception->status);
        self::assertSame('unreachable', $exception->type);
        self::assertTrue($exception->isTransient());
    }
}
