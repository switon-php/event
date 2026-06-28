<?php

declare(strict_types=1);

namespace Switon\Eventing\Event;

use Switon\Eventing\ListenerProviderInterface;
use Switon\Core\SenderAwareInterface;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;

/**
 * Event dispatched when a business listener is registered for a specific event type.
 *
 * Log category: <code>switon.eventing.business.registered</code>
 *
 * @see \Switon\Eventing\ListenerProvider
 * @see \Switon\Eventing\ListenerProvider::register() Typical emitter
 * @see \Switon\Eventing\Event\ObservabilityRegistered
 */
#[EventLevel(Severity::INFO)]
class BusinessRegistered implements SenderAwareInterface
{
    public function __construct(
        public ListenerProviderInterface $sender,
        public string $listener,
        public string $method,
        public string $eventClass,
    ) {

    }

    public function getSender(): ListenerProviderInterface
    {
        return $this->sender;
    }
}
