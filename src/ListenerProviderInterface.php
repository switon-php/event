<?php

declare(strict_types=1);

namespace Switon\Eventing;

use Psr\EventDispatcher\ListenerProviderInterface as PsrListenerProviderInterface;
use Switon\Eventing\Exception\InvalidListenerException;

/**
 * Event-package listener-provider contract.
 *
 * Use when event-package internals need grouped listener buckets for dispatch.
 * Listener lookup matches registered concrete event types only.
 * Guidance: Use union parameter types for one listener across multiple event classes, or <code>object</code> for wildcard listeners.
 *
 * Road-signs:
 * - register listeners via on/register
 * - wildcard key is <code>*</code> for <code>object</code> listeners
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
     * Lower priority values run earlier. Use the wildcard event key for <code>object</code> listeners.
     *
     * @param string $event Event FQCN
     * @param callable $handler Listener callback
     * @param int $priority Execution priority
     */
    public function on(string $event, callable $handler, int $priority = 0): void;

    /**
     * Register a listener class or instance.
     *
     * Implementations may discover methods marked with
     * <code>#[EventListener]</code>.
     * A listener method should declare exactly one typed event parameter.
     *
     * @param string|object $listener Class name or instance
     *
     * @throws InvalidListenerException When an attributed method has an invalid signature
     */
    public function register(string|object $listener): void;

    /**
     * Return grouped listeners for one event instance.
     */
    public function getListenersForEvent(object $event): PrioritizedListeners;

    /**
     * Return grouped wildcard listeners.
     */
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

    /**
     * @return array<string, true>
     */
    public function getObservabilityListenerRegistry(): array;

    /**
     * @return array<string, true>
     */
    public function getBusinessListenerRegistry(): array;
}
