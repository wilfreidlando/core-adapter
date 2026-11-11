<?php

declare(strict_types=1);

namespace CoreAdapter\Tests\Fakes;

/**
 * Un repartiteur d'événements pour les tests : il note ce qui est émis.
 */
final class FakeEventDispatcher
{
    /** @var list<object> */
    public array $dispatches = [];

    public function dispatch(object $event): object
    {
        $this->dispatches[] = $event;

        return $event;
    }
}
