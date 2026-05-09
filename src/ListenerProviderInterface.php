<?php

declare(strict_types=1);

namespace Switon\Eventing;

use Psr\EventDispatcher\ListenerProviderInterface as PsrListenerProviderInterface;

/**
 * Event-package listener-provider contract.
 *
 * Use when event-package internals need grouped listener buckets for dispatch.
 * Listener lookup matches registered concrete event types only.
 * Guidance: Use union parameter types for one listener across multiple event classes, or <code>object</code> for wildcard listeners.
 *
 * Road-signs:
 * - register listeners via on/register
 * - wildcard key uses object parameter
 * - grouped buckets keep priority ordering
 * - dispatcher consumes wildcard then event-specific
 *
 * @see \Switon\Eventing\PrioritizedListeners
 * @see \Switon\Eventing\ListenerProvider
 * @see \Switon\Eventing\EventDispatcherInterface
 * @see \Switon\Eventing\Attribute\EventListener
 */
interface ListenerProviderInterface extends PsrListenerProviderInterface
{
    /**
     * Register one callable for a specific event class.
     *
     * Lower priority values run earlier.
     *
     * @param string $event Event FQCN
     * @param callable $handler Listener callback
     * @param int $priority Execution priority
     */
    public function on(string $event, callable $handler, int $priority = 0);

    /**
     * Register a listener class or instance.
     *
     * Implementations may discover methods marked with
     * <code>#[EventListener]</code>.
     *
     * @param string|object $listener Class name or instance
     */
    public function register(string|object $listener): void;

    /** Returns grouped listeners for one event instance. */
    public function getListenersForEvent(object $event): PrioritizedListeners;

    /** Returns grouped wildcard listeners. */
    public function getListenersForWildcard(): PrioritizedListeners;

    /**
     * Get all registered listeners grouped by event class.
     *
     * Key = event FQCN or '*' (wildcard). '*' is listed first when present.
     * Value = ordered list of listeners (each = FQCN::method or class-string). Order reflects execution priority.
     *
     * @return array<string, list<string>>
     */
    public function getListeners(): array;
}
