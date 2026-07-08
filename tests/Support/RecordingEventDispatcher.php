<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Support;

use Psr\EventDispatcher\EventDispatcherInterface;

class RecordingEventDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $dispatchedEvents = [];

    public function dispatch(object $event): object
    {
        $this->dispatchedEvents[] = $event;

        return $event;
    }

    public function clear(): void
    {
        $this->dispatchedEvents = [];
    }
}
