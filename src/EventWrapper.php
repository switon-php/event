<?php

declare(strict_types=1);

namespace Switon\Eventing;

use JsonSerializable;
use Stringable;
use Switon\Core\Json;

use function get_object_vars;
use function is_object;
use function preg_split;

/**
 * Serializes event payloads for event logger output.
 *
 * Uses event's <code>jsonSerialize()</code> when available; otherwise extracts scalar properties
 * (or specified fields only).
 * Guidance: Treat <code>$fields</code> as a trusted whitelist of public property names on the event object.
 *
 * @see \Switon\Eventing\EventLoggerInterface
 * @see \Switon\Eventing\EventLogger Typical consumer
 * @see \Switon\Core\Json Output format
 */
class EventWrapper implements JsonSerializable, Stringable
{
    /** @param string|null $fields Comma/space separated public property names to include when whitelisting. */
    public function __construct(
        public object  $event,
        public ?string $fields = null,
    ) {

    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        $event = $this->event;

        if ($event instanceof JsonSerializable) {
            $data = $event->jsonSerialize();
        } else {
            $data = [];

            if ($this->fields !== null) {
                $fields = preg_split('#[\s,]+#', $this->fields, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($fields as $field) {
                    $data[$field] = $event->$field;
                }
            } else {
                foreach (get_object_vars($event) as $key => $val) {
                    if (is_object($val)) {
                        continue;
                    }

                    $data[$key] = $val;
                }
            }
        }

        return $data;
    }

    /** Returns JSON string representation of serialized event data. */
    public function __toString(): string
    {
        return Json::stringify($this->jsonSerialize());
    }
}
