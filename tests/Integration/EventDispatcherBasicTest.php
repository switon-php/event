<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Integration;

use Switon\Core\ContextManagerInterface;
use Switon\Core\StopFlow;
use Switon\Eventing\EventDispatcher;
use Switon\Eventing\EventDispatcherContext;
use Switon\Eventing\EventDispatcherInterface;
use Switon\Eventing\ListenerProviderInterface;
use ReflectionProperty;
use Switon\Eventing\Tests\Fixtures\TestBusinessListener;
use Switon\Eventing\Tests\Fixtures\TestEvent;
use Switon\Eventing\Tests\TestCase;
use Switon\Eventing\Tests\Support\Container;

/**
 * Test cases for EventDispatcher basic functionality.
 *
 * Tests basic event dispatching, listener invocation, and context tracking.
 */
class EventDispatcherBasicTest extends TestCase
{
    protected EventDispatcherInterface $dispatcher;
    protected ContextManagerInterface $contextManager;
    protected ListenerProviderInterface $listenerProvider;

    protected function setUp(): void
    {
        parent::setUp();

        // Use pre-configured test container (ContextManager, EventDispatcher, ListenerProvider are already registered)
        $container = new Container();

        // Get ContextManager from container
        $this->contextManager = $container->get(ContextManagerInterface::class);

        // Get ListenerProvider from container (already registered)
        $this->listenerProvider = $container->get(ListenerProviderInterface::class);

        // Real implementation (not TestEventDispatcher wrapper) for low-level dispatch tests
        $this->dispatcher = $container->get(EventDispatcherInterface::class);
    }

    /**
     * Test that dispatch() invokes event-specific listeners.
     */
    public function testDispatchInvokesEventSpecificListeners(): void
    {
        // Arrange
        $event = new TestEvent('test message');
        $listener = new TestBusinessListener();
        $this->listenerProvider->register($listener);

        // Act
        $this->dispatcher->dispatch($event);

        // Assert
        $this->assertCount(1, $listener->handledEvents);
        $this->assertSame($event, $listener->handledEvents[0]);
    }

    /**
     * Test that dispatch() tracks business listener execution in context.
     */
    public function testDispatchTracksBusinessListenerExecution(): void
    {
        // Arrange
        $event = new TestEvent('test message');
        $listener = new TestBusinessListener();
        $this->listenerProvider->register($listener);

        // Act
        $this->dispatcher->dispatch($event);

        // Assert
        $context = $this->dispatcher->getContext();
        $key = TestBusinessListener::class . '::handleEvent';
        $this->assertArrayHasKey($key, $context->businessCallCounts);
        $this->assertSame(1, $context->businessCallCounts[$key]);
    }

    /**
     * Test that dispatch() tracks closure listener execution in context.
     */
    public function testDispatchTracksClosureListenerExecution(): void
    {
        // Arrange
        $event = new TestEvent('test message');
        $called = false;
        $this->listenerProvider->on(TestEvent::class, function (TestEvent $e) use (&$called, $event) {
            $called = true;
            $this->assertSame($event, $e);
        });

        // Act
        $this->dispatcher->dispatch($event);

        // Assert
        $this->assertTrue($called);
        $context = $this->dispatcher->getContext();
        $this->assertArrayHasKey('Closure', $context->businessCallCounts);
        $this->assertSame(1, $context->businessCallCounts['Closure']);
    }

    /**
     * Test that dispatch() increments listener execution count on multiple dispatches.
     */
    public function testDispatchIncrementsListenerExecutionCount(): void
    {
        // Arrange
        $event1 = new TestEvent('message 1');
        $event2 = new TestEvent('message 2');
        $listener = new TestBusinessListener();
        $this->listenerProvider->register($listener);

        // Act
        $this->dispatcher->dispatch($event1);
        $this->dispatcher->dispatch($event2);

        // Assert
        $context = $this->dispatcher->getContext();
        $key = TestBusinessListener::class . '::handleEvent';
        $this->assertSame(2, $context->businessCallCounts[$key]);
    }

    /**
     * Test that dispatch() handles events when no listeners are registered.
     */
    public function testDispatchHandlesEventsWithNoListeners(): void
    {
        // Arrange
        $event = new TestEvent('test message');

        // Act (should not throw any exception)
        $this->dispatcher->dispatch($event);

        // Assert
        $context = $this->dispatcher->getContext();
        $this->assertEmpty($context->businessCallCounts);
        $this->assertEmpty($context->observabilityCallCounts);
    }

    /**
     * Test that getContext() returns EventDispatcherContext instance.
     */
    public function testGetContextReturnsEventDispatcherContext(): void
    {
        $context = $this->dispatcher->getContext();
        $this->assertInstanceOf(EventDispatcherContext::class, $context);
    }

    /**
     * Test that getContext() returns the same context instance on multiple calls.
     */
    public function testGetContextReturnsSameInstance(): void
    {
        $context1 = $this->dispatcher->getContext();
        $context2 = $this->dispatcher->getContext();
        $this->assertSame($context1, $context2);
    }

    /** Test that dispatch() returns the event unchanged when no listener provider is wired. */
    public function testDispatchReturnsEventWhenListenerProviderIsNull(): void
    {
        $event = new TestEvent('no provider');
        $prop = new ReflectionProperty(EventDispatcher::class, 'listenerProvider');
        $prop->setValue($this->dispatcher, null);

        $this->assertSame($event, $this->dispatcher->dispatch($event));

        $context = $this->dispatcher->getContext();
        $this->assertEmpty($context->businessCallCounts);
        $this->assertEmpty($context->observabilityCallCounts);
    }

    public function testDispatchStopsPropagationWhenListenerThrowsStopFlow(): void
    {
        // Arrange: first listener throws StopFlow, second should not run
        $event = new TestEvent('test');
        $secondCalled = false;
        $this->listenerProvider->on(TestEvent::class, function (TestEvent $e) {
            throw StopFlow::because('Stop after first');
        });
        $this->listenerProvider->on(TestEvent::class, function (TestEvent $e) use (&$secondCalled) {
            $secondCalled = true;
        });

        // Act
        $returned = $this->dispatcher->dispatch($event);

        // Assert: same event returned, second listener was not invoked
        $this->assertSame($event, $returned, 'Dispatcher should return the same event when StopFlow is thrown');
        $this->assertFalse($secondCalled, 'Listeners after StopFlow must not be invoked');
    }
}
