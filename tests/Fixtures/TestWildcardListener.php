<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Fixtures;

use Switon\Eventing\Attribute\EventListener;

class TestWildcardListener
{
    public array $handledEvents = [];

    #[EventListener]
    public function handleAnyEvent(object $event): void
    {
        $this->handledEvents[] = $event;
    }
}
