<?php

declare(strict_types=1);

namespace Switon\Eventing\Exception;

use Switon\Core\Exception\RuntimeException;

/**
 * Use when a <code>#[EventListener]</code> method has an invalid signature.
 *
 * @see \Switon\Eventing\ListenerProvider
 * @see \Switon\Eventing\Attribute\EventListener
 */
class InvalidListenerException extends RuntimeException
{
}
