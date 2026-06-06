<?php

declare(strict_types=1);

namespace Switon\Eventing\Event;

use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;

/**
 * Event dispatched when a wildcard observability listener is registered.
 *
 * Log category: <code>switon.eventing.wildcard.observability.registered</code>
 *
 * @see \Switon\Eventing\ListenerProvider
 * @see \Switon\Eventing\ListenerProvider::register() Typical emitter
 * @see \Switon\Eventing\Event\WildcardBusinessRegistered
 */
#[EventLevel(Severity::DEBUG)]
class WildcardObservabilityRegistered
{
    public function __construct(
        public string $listener,
        public string $method,
    ) {

    }
}
