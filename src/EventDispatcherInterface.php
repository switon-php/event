<?php

declare(strict_types=1);

namespace Switon\Eventing;

use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Switon\Core\ContextAware;

/**
 * Contract for PSR-14 event dispatch with listener-statistics context access.
 *
 * Use when you need standard event dispatch plus listener invocation statistics via <code>getContext()</code>.
 * Implementations should return <code>\Switon\Eventing\EventDispatcherContext</code> from <code>getContext()</code>.
 * Guidance: Inject <code>Psr\EventDispatcher\EventDispatcherInterface</code> for plain dispatch only, and inject this interface only when caller code needs <code>getContext()</code> statistics.
 *
 * Road-signs:
 * - wildcard listeners first
 * - event-specific listeners next
 * - StopFlow stops remaining listeners
 * - getContext exposes call statistics
 * - listeners from provider
 *
 * @see \Switon\Eventing\EventDispatcher
 * @see \Switon\Eventing\EventDispatcherContext
 * @see \Switon\Eventing\ListenerProviderInterface
 * @see \Switon\Core\StopFlow
 * @see \Psr\EventDispatcher\EventDispatcherInterface
 */
interface EventDispatcherInterface extends PsrEventDispatcherInterface, ContextAware
{
}
