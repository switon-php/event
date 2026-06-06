<?php

declare(strict_types=1);

namespace Switon\Eventing\Event;

use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;

/**
 * Event dispatched when an observability listener is registered for a specific event type.
 *
 * Log category: <code>switon.eventing.observability.registered</code>
 *
 * @see \Switon\Eventing\ListenerProvider
 * @see \Switon\Eventing\ListenerProvider::register() Typical emitter
 * @see \Switon\Eventing\Event\BusinessRegistered
 */
#[EventLevel(Severity::DEBUG)]
class ObservabilityRegistered
{
    public function __construct(
        public string $listener,
        public string $method,
        public string $eventClass,
    ) {

    }
}
