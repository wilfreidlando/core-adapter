<?php

declare(strict_types=1);

namespace CoreAdapter\Tests\Fakes;

/**
 * Un journal pour les tests : il note ce que le paquet signale.
 */
final class FakeLogger
{
    /** @var list<array{message: string, context: array<string, mixed>}> */
    public array $avertissements = [];

    /**
     * @param array<string, mixed> $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->avertissements[] = ['message' => $message, 'context' => $context];
    }
}
