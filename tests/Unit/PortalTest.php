<?php

declare(strict_types=1);

namespace CoreAdapter\Tests\Unit;

use CoreAdapter\Services\Portal;
use CoreAdapter\Tests\TestCase;

/**
 * Le portail s'ouvre, et **rien d'autre**.
 *
 * ⚠️ Les deux noms Testés ici — `customer` et `plan_code` — n'apparaissent
 * que dans ce test, comme **interdits** : ce sont les deux champs que le
 * portail du Core accepte pour déclarer un client ou l'abonner, et qui
 * demandent des portées d'écriture (`customers:write`,
 * `subscriptions:write`) que la clé d'une application cliente n'a pas. Le
 * paquet ne les envoie donc jamais — le commerce se fait dans le portail,
 * par le propriétaire.
 */
final class PortalTest extends TestCase
{
    public function test_the_portal_call_carries_neither_the_customer_nor_the_plan(): void
    {
        $this->transport->json(201, [
            'url' => 'https://core.test/portal/enter/7QwStnG15qHCvfR4Xfw7-RInTeDheEPUm6LiSKOqJmQ',
            'expires_at' => '2026-10-31T12:10:00+00:00',
            'customer_created' => false,
            'subscription_created' => false,
        ]);

        $session = (new Portal($this->client))->open('AE0001');

        $body = $this->sentBody();

        self::assertSame(['customer_ref' => 'AE0001'], $body);
        self::assertArrayNotHasKey('customer', $body);
        self::assertArrayNotHasKey('plan_code', $body);

        self::assertStringStartsWith('https://core.test/portal/enter/', $session->url);
    }

    public function test_the_portal_call_carries_no_idempotency_key(): void
    {
        $this->transport->json(201, ['url' => 'https://core.test/portal/enter/x']);

        (new Portal($this->client))->open('AE0001', 'pay_invoice', '01a190b8-53be-70d1-9c46-a5b715b4ad80');

        $request = $this->transport->lastRequest();

        self::assertArrayNotHasKey('Idempotency-Key', $request['headers']);
        self::assertSame('pay_invoice', $this->sentBody()['flow']);
        self::assertSame('01a190b8-53be-70d1-9c46-a5b715b4ad80', $this->sentBody()['invoice_id']);
    }
}
