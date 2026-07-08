<?php

declare(strict_types=1);

namespace Switon\Eventing\Event;

use Switon\Core\SenderAwareInterface;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Throwable;

/**
 * Event describing a dispatch failure and the thrown exception.
 *
 * Dispatch directly from failure handling paths to avoid recursive failure loops.
 *
 * Log category: <code>switon.eventing.dispatch.failed</code>
 *
 * @see \Switon\Eventing\EventDispatcherInterface
 * @see \Switon\Eventing\EventDispatcher
 * @see \Switon\Http\Server\Adapter\Swoole::dispatchEvent() Typical emitter
 */
#[EventLevel(Severity::ERROR)]
class DispatchFailed implements SenderAwareInterface
{
    public function __construct(
        public object $sender,
        public object $failedEvent,
        public string $eventClass,
        public string $exceptionMessage,
        public string $exceptionClass,
        public ?string $exceptionFile = null,
        public ?int $exceptionLine = null,
    ) {
    }

    /** Builds <code>DispatchFailed</code> from the dispatcher, failed event, and throwable. */
    public static function from(object $sender, object $failedEvent, Throwable $exception): self
    {
        return new self(
            $sender,
            $failedEvent,
            $failedEvent::class,
            $exception->getMessage(),
            $exception::class,
            $exception->getFile(),
            $exception->getLine(),
        );
    }

    public function getSender(): object
    {
        return $this->sender;
    }
}
