<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Support;

use Switon\Eventing\ListenerProviderInterface;
use Switon\Eventing\PrioritizedListeners;

final class NullListenerProvider implements ListenerProviderInterface
{
    public function on(string $event, callable $handler, int $priority = 0): void
    {
    }

    public function register(string|object $listener): void
    {
    }

    public function getListenersForEvent(object $event): PrioritizedListeners
    {
        return new PrioritizedListeners();
    }

    public function getListenersForWildcard(): PrioritizedListeners
    {
        return new PrioritizedListeners();
    }

    public function getListeners(): array
    {
        return [];
    }
}
