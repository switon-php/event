<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Fixtures;

use Switon\Eventing\Attribute\EventListener;

class TestBusinessListener
{
    public array $handledEvents = [];

    #[EventListener]
    public function handleEvent(TestEvent $event): void
    {
        $this->handledEvents[] = $event;
    }
}
