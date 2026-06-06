<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Integration;

use Switon\Eventing\Attribute\EventListener;
use Switon\Eventing\ListenerProviderInterface;
use Switon\Eventing\Tests\Fixtures\TestEvent;
use Switon\Eventing\Tests\Fixtures\TestJsonSerializableEvent;
use Switon\Eventing\Tests\Support\Container;
use Switon\Eventing\Tests\TestCase;

/**
 * Test cases for ListenerProvider advanced functionality.
 *
 * Tests priority ordering, wildcard listeners, union types, and attribute-based configuration.
 */
class ListenerProviderAdvancedTest extends TestCase
{
    protected ListenerProviderInterface $provider;
    protected Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        // Set coroutine disabled for tests
        \Switon\Core\Runtime::setCoroutineEnabled(false);

        // Use pre-configured test container (ContextManager, EventDispatcher, ListenerProvider are already registered)
        $this->container = new \Switon\Eventing\Tests\Support\Container();

        // Get ListenerProvider from container (already registered)
        $this->provider = $this->container->get(ListenerProviderInterface::class);
    }

    /**
     * Test that on() supports priority ordering.
     */
    public function testOnSupportsPriorityOrdering(): void
    {
        // Arrange
        $executionOrder = [];

        // Lower number = higher priority (executed first)
        $this->provider->on(TestEvent::class, function () use (&$executionOrder) {
            $executionOrder[] = 'high';
        }, -10); // Higher priority (lower number)

        $this->provider->on(TestEvent::class, function () use (&$executionOrder) {
            $executionOrder[] = 'low';
        }, 10); // Lower priority (higher number)

        // Act
        $listeners = $this->provider->getListenersForEvent(new TestEvent());
        $allListeners = [];
        foreach ($listeners as $listener) {
            $allListeners[] = $listener;
        }

        foreach ($allListeners as $listener) {
            $listener(new TestEvent());
        }

        // Assert (lower priority number (-10) should execute before higher priority number (10))
        $this->assertSame(['high', 'low'], $executionOrder);
    }

    /**
     * Test that add() handles wildcard listeners (object type hint).
     */
    public function testAddHandlesWildcardListeners(): void
    {
        // Arrange
        $listener = new class () {
            public array $handledEvents = [];

            #[EventListener] public function handleAnyEvent(object $event): void
            {
                $this->handledEvents[] = $event;
            }
        };

        $this->provider->register($listener);

        // Act
        $wildcardListeners = $this->provider->getListenersForWildcard();
        $this->assertNotEmpty($wildcardListeners);

        $testEvent = new TestEvent();
        foreach ($wildcardListeners as $handler) {
            $handler($testEvent);
        }

        // Assert (listener may receive registration events as well, so check it received at least our event)
        $this->assertGreaterThanOrEqual(1, count($listener->handledEvents));
        $this->assertContains($testEvent, $listener->handledEvents);
    }

    /**
     * Test that add() handles union type parameters.
     */
    public function testAddHandlesUnionTypeParameters(): void
    {
        // Arrange
        $listener = new class () {
            public array $handledEvents = [];

            #[EventListener] public function handleMultipleEvents(TestEvent|TestJsonSerializableEvent $event): void
            {
                $this->handledEvents[] = $event;
            }
        };
        $this->provider->register($listener);

        $testEvent1 = new TestEvent();
        $testEvent2 = new TestJsonSerializableEvent();

        // Act
        $listeners1 = $this->provider->getListenersForEvent($testEvent1);
        $listeners2 = $this->provider->getListenersForEvent($testEvent2);

        foreach ($listeners1 as $handler) {
            $handler($testEvent1);
        }

        foreach ($listeners2 as $handler) {
            $handler($testEvent2);
        }

        // Assert
        $this->assertCount(2, $listener->handledEvents);
    }

    /**
     * Test that add() respects priority from Event attribute.
     */
    public function testAddRespectsPriorityFromAttribute(): void
    {
        // Arrange
        $executionOrder = [];

        $listener = new class ($executionOrder) {
            public function __construct(private array &$executionOrder)
            {
            }

            // Lower number = higher priority (executed first)
            #[EventListener(priority: -10)] public function handleHighPriority(TestEvent $event): void
            {
                $this->executionOrder[] = 'high';
            }

            #[EventListener(priority: 10)] public function handleLowPriority(TestEvent $event): void
            {
                $this->executionOrder[] = 'low';
            }
        };

        $this->provider->register($listener);

        // Act
        $listeners = $this->provider->getListenersForEvent(new TestEvent());
        $allListeners = [];
        foreach ($listeners as $handler) {
            $allListeners[] = $handler;
        }

        foreach ($allListeners as $handler) {
            $handler(new TestEvent());
        }

        // Assert (lower priority number (-10) should execute before higher priority number (10))
        $this->assertSame(['high', 'low'], $executionOrder);
    }

}
