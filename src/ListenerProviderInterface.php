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
     * When a string, it is a container service ID (<code>Type#name</code>) or a relative alias
     * (<code>#name</code>); relative aliases use the handler's first event parameter to resolve
     * the sender type via <code>getSender()</code>.
     *
     * When an object, registration resolves the bucket key from the handler event's
     * {@see \Switon\Core\SenderAwareInterface::getSender()} type <code>T</code> (and owner type
     * <code>O</code> from {@see \Switon\Core\OwnerAwareInterface::getOwner()} when <code>T</code> is
     * owner-aware). Dispatch then matches {@see getListenersForSender()} against that key.
     *
     * Guidance: Typical service holders inject a Redis or HTTP client on <code>$redis</code> /
     * <code>$httpClient</code>. Registration scans those properties for <code>O</code>; dispatch
     * matches pooled connection or engine events via one {@see \Switon\Core\OwnerAwareInterface::getOwner()}
     * hop. Client-level HTTP events already send the client as <code>T</code> (scan only, no owner hop).
     *
     * Road-signs (object sender scope):
     * - <code>$sender instanceof T</code> → register on <code>$sender</code>
     * - else <code>T</code> is owner-aware and <code>$sender instanceof O</code> → register on <code>$sender</code>
     * - else scan <code>$sender</code> properties (<code>O</code> then <code>T</code> when owner-aware, else <code>T</code> only)
     * - else {@see InvalidListenerException}
     *
     * The handler must declare exactly one typed parameter (event class, <code>object</code>, or union).
     * Lower priority values run earlier.
     *
     * @param string|object|null $sender Dispatching instance filter, container ID, relative alias, or null for global scope
     * @param callable $handler Listener callback
     * @param int $priority Execution priority
     *
     * @throws InvalidListenerException When the handler signature is invalid or sender scope cannot be resolved
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
     * Guidance: Typical owner-aware senders are pooled connections or transport engines; listeners
     * registered on the owning client (or a service holder that scanned those properties) match after
     * one {@see \Switon\Core\OwnerAwareInterface::getOwner()} hop.
     *
     * Road-signs:
     * - match {@see \Switon\Core\SenderAwareInterface::getSender()} bucket first
     * - else when sender is owner-aware, match {@see \Switon\Core\OwnerAwareInterface::getOwner()} bucket
     * - else empty
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
