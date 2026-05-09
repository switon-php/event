<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Unit;

use Switon\Eventing\EventWrapper;
use Switon\Eventing\Tests\Fixtures\TestEvent;
use Switon\Eventing\Tests\Fixtures\TestEventWithObject;
use Switon\Eventing\Tests\Fixtures\TestJsonSerializableEvent;
use Switon\Eventing\Tests\TestCase;

/**
 * Test cases for EventWrapper class.
 *
 * Tests event object wrapping and serialization functionality.
 *
 */
class EventWrapperTest extends TestCase
{
    /**
     * Test that EventWrapper can wrap a simple event object.
     */
    public function testWrapSimpleEvent(): void
    {
        $event = new TestEvent('test message', 123);
        $wrapper = new EventWrapper($event);

        $this->assertSame($event, $wrapper->event);
        $this->assertNull($wrapper->fields);
    }

    /**
     * Test that EventWrapper can wrap an event with field specification.
     */
    public function testWrapEventWithFields(): void
    {
        $event = new TestEvent('test message', 123);
        $wrapper = new EventWrapper($event, 'message,value');

        $this->assertSame($event, $wrapper->event);
        $this->assertSame('message,value', $wrapper->fields);
    }

    /**
     * Test that jsonSerialize() extracts all non-object properties when no fields specified.
     */
    public function testJsonSerializeExtractsAllProperties(): void
    {
        $event = new TestEvent('test message', 123);
        $wrapper = new EventWrapper($event);
        $data = $wrapper->jsonSerialize();

        $this->assertIsArray($data);
        $this->assertSame('test message', $data['message']);
        $this->assertSame(123, $data['value']);
    }

    /**
     * Test that jsonSerialize() uses event's jsonSerialize() when event implements JsonSerializable.
     */
    public function testJsonSerializeUsesEventJsonSerialize(): void
    {
        $event = new TestJsonSerializableEvent('test', ['key' => 'value']);
        $wrapper = new EventWrapper($event);
        $data = $wrapper->jsonSerialize();

        $this->assertIsArray($data);
        $this->assertSame('test', $data['name']);
        $this->assertSame(['key' => 'value'], $data['data']);
    }

    /**
     * Test that jsonSerialize() excludes object properties from auto-extraction.
     */
    public function testJsonSerializeExcludesObjectProperties(): void
    {
        $object = new \stdClass();
        $event = new TestEventWithObject('test', $object);
        $wrapper = new EventWrapper($event);
        $data = $wrapper->jsonSerialize();

        $this->assertIsArray($data);
        $this->assertSame('test', $data['name']);
        $this->assertArrayNotHasKey('object', $data);
    }

    /**
     * Test that jsonSerialize() extracts only specified fields when fields are provided.
     */
    public function testJsonSerializeExtractsSpecifiedFields(): void
    {
        $event = new TestEvent('test message', 123);
        $wrapper = new EventWrapper($event, 'message');
        $data = $wrapper->jsonSerialize();

        $this->assertIsArray($data);
        $this->assertSame('test message', $data['message']);
        $this->assertArrayNotHasKey('value', $data);
    }

    /**
     * Test that jsonSerialize() handles comma-separated field specification.
     */
    public function testJsonSerializeHandlesCommaSeparatedFields(): void
    {
        $event = new TestEvent('test message', 123);
        $wrapper = new EventWrapper($event, 'message,value');
        $data = $wrapper->jsonSerialize();

        $this->assertIsArray($data);
        $this->assertSame('test message', $data['message']);
        $this->assertSame(123, $data['value']);
    }

    /**
     * Test that jsonSerialize() handles space-separated field specification.
     */
    public function testJsonSerializeHandlesSpaceSeparatedFields(): void
    {
        $event = new TestEvent('test message', 123);
        $wrapper = new EventWrapper($event, 'message value');
        $data = $wrapper->jsonSerialize();

        $this->assertIsArray($data);
        $this->assertSame('test message', $data['message']);
        $this->assertSame(123, $data['value']);
    }

    /**
     * Test that __toString() returns JSON string representation.
     */
    public function testToStringReturnsJsonString(): void
    {
        $event = new TestEvent('test message', 123);
        $wrapper = new EventWrapper($event);
        $json = (string)$wrapper;

        $this->assertIsString($json);
        $data = json_decode($json, true);
        $this->assertIsArray($data);
        $this->assertSame('test message', $data['message']);
        $this->assertSame(123, $data['value']);
    }
}
