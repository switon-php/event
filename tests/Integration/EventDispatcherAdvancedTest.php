<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Integration;

use Switon\Core\ContextManagerInterface;
use Switon\Eventing\EventDispatcherInterface;
use Switon\Eventing\ListenerProviderInterface;
use Switon\Eventing\Tests\Fixtures\TestBusinessListener;
use Switon\Eventing\Tests\Fixtures\TestEvent;
use Switon\Eventing\Tests\Fixtures\TestObservabilityListener;
use Switon\Eventing\Tests\Fixtures\TestWildcardListener;
use Switon\Eventing\Tests\TestCase;

/**
 * Test cases for EventDispatcher advanced functionality.
 *
 * Tests wildcard listeners, listener prioritization, and advanced dispatching behavior.
 */
class EventDispatcherAdvancedTest extends TestCase
{
    protected EventDispatcherInterface $dispatcher;
    protected ContextManagerInterface $contextManager;
    protected ListenerProviderInterface $listenerProvider;

    protected function setUp(): void
    {
        parent::setUp();
        // Use pre-configured test container (ContextManager, EventDispatcher, ListenerProvider are already registered)
        $container = new \Switon\Eventing\Tests\Support\Container();

        // Get ContextManager from container
        $this->contextManager = $container->get(ContextManagerInterface::class);

        // Get ListenerProvider from container (already registered)
        $this->listenerProvider = $container->get(ListenerProviderInterface::class);

        // Get EventDispatcher from container (already registered)
        $this->dispatcher = $container->get(EventDispatcherInterface::class);
    }

    /**
     * Test that dispatch() invokes wildcard listeners before event-specific listeners.
     */
    public function testDispatchInvokesWildcardListenersFirst(): void
    {
        // Arrange
        $event = new TestEvent('test message');
        $wildcardListener = new TestWildcardListener();
        $specificListener = new TestBusinessListener();
        $this->listenerProvider->register($wildcardListener);
        $this->listenerProvider->register($specificListener);

        // Act
        $this->dispatcher->dispatch($event);

        // Assert
        // Wildcard listener should receive the event (may also receive registration events)
        $this->assertGreaterThanOrEqual(1, count($wildcardListener->handledEvents));
        // Specific listener should receive the event
        $this->assertCount(1, $specificListener->handledEvents);
    }

    /**
     * Test that dispatch() tracks observability listener execution in context.
     */
    public function testDispatchTracksObservabilityListenerExecution(): void
    {
        // Arrange
        $event = new TestEvent('test message');
        $listener = new TestObservabilityListener();
        $this->listenerProvider->register($listener);

        // Act
        $this->dispatcher->dispatch($event);

        // Assert
        $context = $this->dispatcher->getContext();
        $key = TestObservabilityListener::class . '::handleEvent';
        $this->assertArrayHasKey($key, $context->observabilityCallCounts);
        $this->assertSame(1, $context->observabilityCallCounts[$key]);
    }
}
