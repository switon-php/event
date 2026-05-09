<?php

declare(strict_types=1);

namespace Switon\Eventing;

use JsonSerializable;

/**
 * Request/coroutine-local statistics for event listener dispatches.
 *
 * Keys use <code>Class::method</code> for object listeners and <code>Closure</code> for closure listeners.
 *
 * Road-signs:
 * - stats per request/coroutine
 * - keys Class::method and Closure
 * - observability vs business
 * - totals via jsonSerialize()
 *
 * @see \Switon\Eventing\EventDispatcherInterface
 * @see \Switon\Eventing\ObservabilityProbe
 */
class EventDispatcherContext implements JsonSerializable
{
    /** @var array<string, int> Observability listener call counts by listener key. */
    public array $observabilityCallCounts = [];

    /** @var array<string, int> Business listener call counts by listener key. */
    public array $businessCallCounts = [];

    /** @return array<string, array{_keys: list<string>, _count: int, _total: int}> */
    public function jsonSerialize(): array
    {
        return [
            'observabilityCallCounts' => [
                '_keys' => array_keys($this->observabilityCallCounts),
                '_count' => count($this->observabilityCallCounts),
                '_total' => array_sum($this->observabilityCallCounts),
            ],
            'businessCallCounts' => [
                '_keys' => array_keys($this->businessCallCounts),
                '_count' => count($this->businessCallCounts),
                '_total' => array_sum($this->businessCallCounts),
            ],
        ];
    }
}
