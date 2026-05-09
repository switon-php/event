<?php

declare(strict_types=1);

namespace Switon\Eventing;

/**
 * Contract for logging dispatched events and exposing resolved category/level mappings.
 *
 * Keep this listener for observability only; domain listeners should handle business reactions separately.
 *
 * Road-signs:
 * - boot registers wildcard listener
 * - category from Categorizable or class naming
 * - level from EventLevel or debug default
 * - mapping methods expose runtime cache
 *
 * @see \Switon\Eventing\EventLogger
 * @see \Switon\Eventing\ListenerProviderInterface
 * @see \Switon\Eventing\Attribute\EventLevel
 * @see \Switon\Core\Categorizable
 * @see \Switon\Eventing\EventLogInterface
 * @see \Switon\Eventing\EventSilent
 */
interface EventLoggerInterface
{
    /**
     * Default log category (code) for an event class. Same rule as runtime when event does not implement Categorizable.
     * Beacon CLI: <code>event:category</code> / <code>event:by-code</code>.
     */
    public function categoryForClass(string $eventClass): string;

    /** Registers logger listener and emits bootstrap status log. */
    public function boot(): void;

    /** @return array<string, string> Runtime cache: handled event class => resolved category ID. */
    public function getCategoryMapping(): array;

    /** @return array<string, string> Runtime cache: handled event class => resolved PSR-3 level. */
    public function getLevelMapping(): array;
}
