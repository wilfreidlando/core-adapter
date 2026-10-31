<?php

declare(strict_types=1);

namespace CoreAdapter\Tests\Unit;

use CoreAdapter\Tests\TestCase;
use CoreAdapter\Webhooks\Event;
use CoreAdapter\Webhooks\InMemoryEventStore;
use CoreAdapter\Webhooks\InvalidSignatureException;
use CoreAdapter\Webhooks\Receiver;
use CoreAdapter\Webhooks\Signature;

/**
 * La réception : la signature d'abord, le dédoublonnage ensuite.
 */
final class ReceiverTest extends TestCase
{
    private const SECRET = 'whsec_test';

    private const BODY = '{"id":"evt_1","type":"customer.created","data":{"external_ref":"AE0001","version":1}}';

    public function test_the_same_event_is_delivered_only_once(): void
    {
        $receiver = new Receiver(new Signature, new InMemoryEventStore, self::SECRET);

        $event = $receiver->accept(self::BODY, $this->headers(self::BODY, self::SECRET));

        self::assertInstanceOf(Event::class, $event);
        self::assertSame('evt_1', $event->id);
        self::assertSame('customer.created', $event->type);
        self::assertSame('AE0001', $event->customerRef());

        self::assertFalse($receiver->isDuplicate($event));

        $receiver->markProcessed($event);

        self::assertTrue($receiver->isDuplicate($event));

        // Le Core rejoue la même livraison : elle est reconnue comme déjà vue.
        $again = $receiver->accept(self::BODY, $this->headers(self::BODY, self::SECRET));

        self::assertInstanceOf(Event::class, $again);
        self::assertTrue($receiver->isDuplicate($again));
    }

    public function test_an_event_signed_with_the_previous_secret_is_accepted_during_a_rotation(): void
    {
        $receiver = new Receiver(new Signature, new InMemoryEventStore, self::SECRET);

        $timestamp = (string) time();
        $current = hash_hmac('sha256', $timestamp.'.'.self::BODY, 'whsec_new');
        $previous = hash_hmac('sha256', $timestamp.'.'.self::BODY, self::SECRET);

        $event = $receiver->accept(self::BODY, [
            'x-timestamp' => $timestamp,
            'x-signature' => $current,
            'x-signature-previous' => $previous,
        ]);

        self::assertInstanceOf(Event::class, $event);
    }

    public function test_an_event_with_a_bad_signature_is_refused(): void
    {
        $receiver = new Receiver(new Signature, new InMemoryEventStore, self::SECRET);

        $this->expectException(InvalidSignatureException::class);

        $receiver->accept(self::BODY, [
            'x-timestamp' => (string) time(),
            'x-signature' => 'deadbeef',
        ]);
    }

    public function test_a_body_that_is_not_an_envelope_is_ignored(): void
    {
        $receiver = new Receiver(new Signature, new InMemoryEventStore, self::SECRET);

        $body = '{"pas":"une enveloppe"}';
        $timestamp = (string) time();

        $event = $receiver->accept($body, [
            'x-timestamp' => $timestamp,
            'x-signature' => hash_hmac('sha256', $timestamp.'.'.$body, self::SECRET),
        ]);

        self::assertNull($event);
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $body, string $secret): array
    {
        $timestamp = (string) time();

        return [
            'x-timestamp' => $timestamp,
            'x-signature' => hash_hmac('sha256', $timestamp.'.'.$body, $secret),
        ];
    }
}
