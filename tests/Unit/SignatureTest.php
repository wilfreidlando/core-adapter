<?php

declare(strict_types=1);

namespace SaasCommerce\Tests\Unit;

use SaasCommerce\Core\Webhooks\Signature;
use SaasCommerce\Tests\TestCase;

/**
 * La signature d'un événement : HMAC-SHA256 de `timestamp.body`, avec une
 * tolérance de cinq minutes, et deux signatures pendant une rotation.
 */
final class SignatureTest extends TestCase
{
    private const SECRET = 'whsec_test';

    private const BODY = '{"id":"evt_1","type":"customer.created","data":{"external_ref":"AE0001"}}';

    private const TIMESTAMP = '1791028722';

    public function test_it_verifies_a_valid_signature(): void
    {
        $signature = hash_hmac('sha256', self::TIMESTAMP.'.'.self::BODY, self::SECRET);

        self::assertTrue(
            (new Signature)->verify(self::BODY, self::TIMESTAMP, [$signature], self::SECRET, 1791028722)
        );
    }

    public function test_it_refuses_a_signature_that_does_not_match(): void
    {
        self::assertFalse(
            (new Signature)->verify(self::BODY, self::TIMESTAMP, ['deadbeef'], self::SECRET, 1791028722)
        );
    }

    public function test_it_refuses_a_timestamp_that_is_too_old(): void
    {
        $signature = hash_hmac('sha256', self::TIMESTAMP.'.'.self::BODY, self::SECRET);

        // 301 secondes d'écart : au-delà de la tolérance de 300.
        self::assertFalse(
            (new Signature)->verify(self::BODY, self::TIMESTAMP, [$signature], self::SECRET, 1791029023)
        );

        // 299 secondes : encore acceptées.
        self::assertTrue(
            (new Signature)->verify(self::BODY, self::TIMESTAMP, [$signature], self::SECRET, 1791029021)
        );
    }

    public function test_it_refuses_an_empty_secret(): void
    {
        $signature = hash_hmac('sha256', self::TIMESTAMP.'.'.self::BODY, '');

        self::assertFalse(
            (new Signature)->verify(self::BODY, self::TIMESTAMP, [$signature], '', 1791028722)
        );
    }

    public function test_a_body_signed_with_the_previous_secret_still_verifies_during_a_rotation(): void
    {
        // Le client a encore l'ancien secret ; le Core envoie les deux
        // signatures : la nouvelle (`X-Signature`) et l'ancienne
        // (`X-Signature-Previous`).
        $current = hash_hmac('sha256', self::TIMESTAMP.'.'.self::BODY, 'whsec_new');
        $previous = hash_hmac('sha256', self::TIMESTAMP.'.'.self::BODY, self::SECRET);

        self::assertTrue(
            (new Signature)->verify(self::BODY, self::TIMESTAMP, [$current, $previous], self::SECRET, 1791028722)
        );
    }

    public function test_a_body_signed_with_the_new_secret_verifies_after_the_rotation(): void
    {
        $current = hash_hmac('sha256', self::TIMESTAMP.'.'.self::BODY, 'whsec_new');

        self::assertTrue(
            (new Signature)->verify(self::BODY, self::TIMESTAMP, [$current], 'whsec_new', 1791028722)
        );
    }
}
