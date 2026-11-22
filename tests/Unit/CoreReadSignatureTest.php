<?php

declare(strict_types=1);

namespace CoreAdapter\Tests\Unit;

use CoreAdapter\Laravel\Middleware\VerifyCoreReadSignature;
use CoreAdapter\Webhooks\Signature;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Le garde des lectures que le Core vient chercher.
 *
 * Le schéma est celui des événements : `X-Signature = HMAC-SHA256(timestamp +
 * "." + corps)`. Une lecture sans paramètre signe un corps vide ; une lecture
 * paginée signe le corps qui porte la page.
 */
final class CoreReadSignatureTest extends TestCase
{
    private const SECRET = 'whsec_test_0123456789';

    public function test_a_read_without_parameter_is_accepted(): void
    {
        $timestamp = (string) time();

        $request = Request::create('/core/tenant-schema', 'GET');
        $request->headers->set('X-Timestamp', $timestamp);
        $request->headers->set('X-Signature', $this->sign($timestamp, ''));

        $response = $this->guard()->handle($request, fn (): Response => new Response('passé', 200));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('passé', $response->getContent());
    }

    public function test_a_read_with_a_body_is_accepted_when_the_body_is_the_signed_one(): void
    {
        $timestamp = (string) time();
        $corps = '{"page":2,"per_page":50}';

        $request = Request::create('/core/tenants', 'POST', [], [], [], [], $corps);
        $request->headers->set('X-Timestamp', $timestamp);
        $request->headers->set('X-Signature', $this->sign($timestamp, $corps));

        $response = $this->guard()->handle($request, fn (): Response => new Response('passé', 200));

        self::assertSame(200, $response->getStatusCode());
    }

    public function test_a_read_signed_with_another_body_is_refused(): void
    {
        $timestamp = (string) time();

        $request = Request::create('/core/tenants', 'POST', [], [], [], [], '{"page":2}');
        $request->headers->set('X-Timestamp', $timestamp);
        $request->headers->set('X-Signature', $this->sign($timestamp, '{"page":1}'));

        $response = $this->guard()->handle($request, fn (): Response => new Response('passé', 200));

        self::assertSame(401, $response->getStatusCode());
    }

    public function test_a_read_without_signature_is_refused(): void
    {
        $request = Request::create('/core/tenant-schema', 'GET');
        $request->headers->set('X-Timestamp', (string) time());

        $response = $this->guard()->handle($request, fn (): Response => new Response('passé', 200));

        self::assertSame(401, $response->getStatusCode());
    }

    public function test_the_previous_secret_is_accepted_during_a_rotation(): void
    {
        $timestamp = (string) time();

        $request = Request::create('/core/tenant-schema', 'GET');
        $request->headers->set('X-Timestamp', $timestamp);
        $request->headers->set('X-Signature', $this->sign($timestamp, '', 'whsec_ancien'));
        $request->headers->set('X-Signature-Previous', $this->sign($timestamp, '', self::SECRET));

        $response = $this->guard()->handle($request, fn (): Response => new Response('passé', 200));

        self::assertSame(200, $response->getStatusCode());
    }

    public function test_a_read_that_is_too_old_is_refused(): void
    {
        $timestamp = (string) (time() - 3600);

        $request = Request::create('/core/tenant-schema', 'GET');
        $request->headers->set('X-Timestamp', $timestamp);
        $request->headers->set('X-Signature', $this->sign($timestamp, ''));

        $response = $this->guard()->handle($request, fn (): Response => new Response('passé', 200));

        self::assertSame(401, $response->getStatusCode());
    }

    public function test_without_a_configured_secret_nothing_passes(): void
    {
        $timestamp = (string) time();

        $request = Request::create('/core/tenant-schema', 'GET');
        $request->headers->set('X-Timestamp', $timestamp);
        $request->headers->set('X-Signature', $this->sign($timestamp, ''));

        $guard = new VerifyCoreReadSignature(new Signature, '');

        $response = $guard->handle($request, fn (): Response => new Response('passé', 200));

        self::assertSame(403, $response->getStatusCode());
    }

    private function guard(): VerifyCoreReadSignature
    {
        return new VerifyCoreReadSignature(new Signature, self::SECRET);
    }

    private function sign(string $timestamp, string $body, string $secret = self::SECRET): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }
}
