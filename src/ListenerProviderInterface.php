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
 * - register() discovers listeners for any sender from <code>#[EventListener]</code>
 * - <code>on(null, $handler)</code> registers listeners for any sender
 * - <code>on($sender, $handler)</code> registers sender-scoped listeners manually
 * - <code>on('#name', $handler)</code> resolves sender type from the handler event parameter
 * - on() infers event type from the handler's single typed parameter
 * - wildcard key is <code>*</code> for <code>object</code> listeners
 * - grouped buckets keep priority ordering
 * - dispatcher consumes wildcard, event-specific, then sender-scoped
 *
 * @see \Switon\Eventing\PrioritizedListeners
 * @see \Switon\Eventing\ListenerProvider
 * @see \Switon\Eventing\EventDispatcherInterface
 * @see \Switon\Eventing\Attribute\EventListener
 */
interface ListenerProviderInterface extends PsrListenerProviderInterface
{
    /**
     * Register one callable; event type is inferred from the handler signature.
     *
     * When <code>$sender</code> is null, the listener runs for every matching dispatch.
     * When an object, it runs only for {@see \Switon\Core\SenderAwareInterface} events whose
     * {@see \Switon\Core\SenderAwareInterface::getSender()} is the same instance.
     * When a string, it is a container service ID (<code>Type#name</code>) or a relative alias
     * (<code>#name</code>); relative aliases use the handler's first event parameter to resolve
     * the sender type via <code>getSender()</code>.
     *
     * The handler must declare exactly one typed parameter (event class, <code>object</code>, or union).
     * Lower priority values run earlier.
     *
     * @param string|object|null $sender Dispatching instance filter, container ID, relative alias, or null for global scope
     * @param callable $handler Listener callback
     * @param int $priority Execution priority
     *
     * @throws InvalidListenerException When the handler signature is invalid
     */
    public function on(string|object|null $sender, callable $handler, int $priority = 0): void;

    /**
     * Register a listener class or instance (any sender).
     *
     * Discovers public methods marked with <code>#[EventListener]</code> and delegates to {@see on()}.
     *
     * @param string|object $listener Class name or instance
     *
     * @throws InvalidListenerException When an attributed method has an invalid signature
     */
    public function register(string|object $listener): void;

    /**
     * Return grouped listeners for one event class (any sender).
     */
    public function getListenersForEvent(object $event): PrioritizedListeners;

    /**
     * Return grouped wildcard listeners (any sender).
     */
    public function getListenersForWildcard(): PrioritizedListeners;

    /**
     * Return grouped sender-scoped listeners for one dispatched event.
     *
     * Empty when the event is not {@see \Switon\Core\SenderAwareInterface} or no sender bucket matches
     * {@see \Switon\Core\SenderAwareInterface::getSender()}.
     */
    public function getListenersForSender(object $event): PrioritizedListeners;

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
