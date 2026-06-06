<?php

declare(strict_types=1);

namespace Switon\Eventing;

use Psr\Log\LoggerInterface;

/**
 * Optional event-level logging hook.
 *
 * Implement on events or handlers that need custom log formatting, level, or sampling behavior.
 *
 * @see \Switon\Eventing\EventLogger
 * @see \Switon\Redis\Event\RedisCalled
 * @see \Switon\Redis\Event\RedisCalling
 * @see \Switon\Http\Event\RequestFailed
 */
interface EventLogInterface
{
    /**
     * Log one event with a provided logger.
     *
     * Implementations should avoid throwing to prevent breaking event flow.
     * The event instance is passed separately so implementations can log wrappers or proxies.
     */
    public function log(object $event, LoggerInterface $logger): void;
}
