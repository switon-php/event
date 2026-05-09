<?php

declare(strict_types=1);

namespace Switon\Eventing\Event;

use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;

/**
 * Event dispatched when a wildcard business listener is registered.
 *
 * Log category: <code>switon.eventing.wildcard.business.registered</code>
 *
 * @see \Switon\Eventing\ListenerProvider
 * @see \Switon\Eventing\ListenerProvider::register() Typical emitter
 * @see \Switon\Eventing\Event\WildcardObservabilityRegistered
 */
#[EventLevel(Severity::INFO)]
class WildcardBusinessRegistered
{
    public function __construct(
        public string $listener,
        public string $method,
    )
    {

    }
}
