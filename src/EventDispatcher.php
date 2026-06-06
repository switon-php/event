<?php

declare(strict_types=1);

namespace Switon\Eventing;

use Switon\Core\Attribute\Autowired;
use Switon\Core\ContextManagerInterface;
use Switon\Core\Lazy;
use Switon\Core\StopFlow;

use function is_array;

/**
 * Dispatches PSR-14 events and records listener invocation statistics in request context.
 *
 * Dispatch order is wildcard listeners first, then event-specific listeners.
 * Throw <code>StopFlow</code> in a listener to stop remaining listeners for the current event.
 * Guidance: Keep business flow control explicit in listener logic; use <code>StopFlow</code> only for intentional early-stop branches, not as an error-handling substitute.
 *
 * Road-signs:
 * - event object can be any class
 * - wildcard first
 * - event-specific next
 * - StopFlow stops remaining listeners
 * - context tracks invocation counts
 *
 * @see \Switon\Eventing\EventDispatcherInterface
 * @see \Switon\Eventing\EventDispatcherContext
 * @see \Switon\Eventing\ListenerProviderInterface
 * @see \Switon\Eventing\PrioritizedListeners
 * @see \Switon\Core\ContextManagerInterface Context boundary
 * @see \Switon\Core\StopFlow
 */
class EventDispatcher implements EventDispatcherInterface
{
    #[Autowired] protected ContextManagerInterface $contextManager;

    #[Autowired] protected ListenerProviderInterface|Lazy $listenerProvider;

    /** {@inheritDoc} */
    public function getContext(): EventDispatcherContext
    {
        return $this->contextManager->getContext($this);
    }

    public function dispatch(object $event): object
    {
        try {
            $this->invokeListeners($event, $this->listenerProvider->getListenersForWildcard());
            $this->invokeListeners($event, $this->listenerProvider->getListenersForEvent($event));
        } catch (StopFlow) {
            // Listener requested flow stop; return event without invoking remaining listeners
        }

        return $event;
    }

    /** Invokes listeners and records business/observability invocation counts. */
    protected function invokeListeners(object $event, PrioritizedListeners $listeners): void
    {
        $context = $this->getContext();

        foreach ($listeners->getGroups() as $group) {
            foreach ($group as $listener) {
                if (is_array($listener)) {
                    $listenerObject = $listener[0] ?? null;
                    $listenerMethod = $listener[1] ?? null;

                    if ($listenerObject instanceof ObservabilityProbe) {
                        $this->recordObservabilityInvocation($context, $listenerObject, $listenerMethod);
                    } else {
                        $this->recordBusinessInvocation($context, $listenerObject, $listenerMethod);
                    }
                } else {
                    $context->businessCallCounts['Closure'] ??= 0;
                    $context->businessCallCounts['Closure']++;
                }
                $listener($event);
            }
        }
    }

    /** Increments observability listener call count when listener metadata is available. */
    protected function recordObservabilityInvocation(EventDispatcherContext $context, ?object $listenerObject, ?string $listenerMethod): void
    {
        if ($listenerObject !== null) {
            $key = $listenerObject::class . '::' . $listenerMethod;
            $context->observabilityCallCounts[$key] ??= 0;
            $context->observabilityCallCounts[$key]++;
        }
    }

    /** Increments business listener call count when listener metadata is available. */
    protected function recordBusinessInvocation(EventDispatcherContext $context, ?object $listenerObject, ?string $listenerMethod): void
    {
        if ($listenerObject !== null) {
            $key = $listenerObject::class . '::' . $listenerMethod;
            $context->businessCallCounts[$key] ??= 0;
            $context->businessCallCounts[$key]++;
        }
    }
}
