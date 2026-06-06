<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Unit\Attribute;

use Switon\Eventing\Attribute\EventListener;
use Switon\Eventing\Tests\TestCase;

/**
 * Test cases for EventListener attribute class.
 *
 * Tests attribute instantiation, property access, and basic functionality.
 */
class EventTest extends TestCase
{
    /**
     * Test that EventListener attribute can be instantiated with default priority.
     */
    public function testEventListenerWithDefaultPriority(): void
    {
        $attribute = new EventListener();

        $this->assertSame(0, $attribute->priority);
    }

    /**
     * Test that EventListener attribute can be instantiated with custom priority.
     */
    public function testEventListenerWithCustomPriority(): void
    {
        $attribute = new EventListener(priority: -100);

        $this->assertSame(-100, $attribute->priority);
    }

    /**
     * Test that EventListener attribute accepts positive priority values.
     */
    public function testEventListenerWithPositivePriority(): void
    {
        $attribute = new EventListener(priority: 100);

        $this->assertSame(100, $attribute->priority);
    }
}
