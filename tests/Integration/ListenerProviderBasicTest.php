<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Integration;

use Psr\EventDispatcher\ListenerProviderInterface as PsrListenerProviderInterface;
use Switon\Eventing\Attribute\EventListener;
use Switon\Eventing\ListenerProviderInterface;
use Switon\Eventing\ObservabilityProbe;
use Switon\Eventing\ServiceProvider;
use Switon\Eventing\Tests\Fixtures\TestEvent;
use Switon\Eventing\Tests\Support\Container;
use Switon\Eventing\Tests\TestCase;

/**
 * Test cases for ListenerProvider basic functionality.
 *
 * Tests basic listener registration and event listener retrieval.
 */
class ListenerProviderBasicTest extends TestCase
{
    protected ListenerProviderInterface $provider;
    protected Container $container;
    protected PsrListenerProviderInterface $psrProvider;

    protected function setUp(): void
    {
        parent::setUp();
        // Set coroutine disabled for tests
        \Switon\Core\Runtime::setCoroutineEnabled(false);

        // Use pre-configured test container (ContextManager, EventDispatcher, ListenerProvider are already registered)
        $this->container = new Container();
        (new ServiceProvider())->register($this->container);

        // Get ListenerProvider from container (already registered)
        $this->provider = $this->container->get(ListenerProviderInterface::class);
        $this->psrProvider = $this->container->get(PsrListenerProviderInterface::class);
    }

    /**
     * Test that on() registers a callable listener for an event.
     */
    public function testOnRegistersCallableListener(): void
    {
        // Arrange
        $called = false;
        $this->provider->on(TestEvent::class, function (TestEvent $event) use (&$called) {
            $called = true;
        });

        // Act
        $listeners = $this->provider->getListenersForEvent(new TestEvent());
        $this->assertNotEmpty($listeners);

        foreach ($listeners as $listener) {
            $listener(new TestEvent());
        }

        // Assert
        $this->assertTrue($called);
    }

    /**
     * Test that add() automatically discovers listener methods with Event attribute.
     */
    public function testAddDiscoversListenerMethods(): void
    {
        // Arrange
        $listener = new class () {
            public array $handledEvents = [];

            #[EventListener] public function handleEvent(TestEvent $event): void
            {
                $this->handledEvents[] = $event;
            }
        };
        $this->provider->register($listener);

        // Act
        $listeners = $this->provider->getListenersForEvent(new TestEvent());
        $this->assertNotEmpty($listeners);

        foreach ($listeners as $handler) {
            $handler(new TestEvent('test'));
        }

        // Assert
        $this->assertCount(1, $listener->handledEvents);
    }

    /**
     * Test that getListenersForEvent() returns empty iterable when no listeners registered.
     */
    public function testGetListenersForEventReturnsEmptyWhenNoListeners(): void
    {
        // Arrange & Act
        $listeners = $this->provider->getListenersForEvent(new TestEvent());
        $count = 0;
        foreach ($listeners as $handler) {
            $count++;
        }

        // Assert
        $this->assertSame(0, $count);
    }

    /**
     * Test that getListenersForWildcard() returns empty iterable when no wildcard listeners.
     */
    public function testGetListenersForWildcardReturnsEmptyWhenNoListeners(): void
    {
        // Arrange & Act
        $listeners = $this->provider->getListenersForWildcard();
        $count = 0;
        foreach ($listeners as $handler) {
            $count++;
        }

        // Assert
        $this->assertSame(0, $count);
    }

    /**
     * Test that getObservabilityListenerRegistry() returns registered observability listeners.
     */
    public function testGetObservabilityListenerRegistry(): void
    {
        // Arrange
        $listener = new class () implements ObservabilityProbe {
            #[EventListener] public function handleEvent(TestEvent $event): void
            {
            }
        };
        $this->provider->register($listener);

        // Act
        $registry = $this->provider->getObservabilityListenerRegistry();

        // Assert
        $key = $listener::class . '::handleEvent';
        $this->assertArrayHasKey($key, $registry);
        $this->assertTrue($registry[$key]);
    }

    public function testPsrProviderReturnsCallableSequence(): void
    {
        $called = false;

        $this->provider->on(TestEvent::class, function (TestEvent $event) use (&$called): void {
            $called = true;
        });

        $listeners = $this->psrProvider->getListenersForEvent(new TestEvent());
        $this->assertNotEmpty($listeners);

        foreach ($listeners as $listener) {
            $this->assertIsCallable($listener);
            $listener(new TestEvent());
        }

        $this->assertTrue($called);
    }

    /**
     * Test that getBusinessListenerRegistry() returns registered business listeners.
     */
    public function testGetBusinessListenerRegistry(): void
    {
        // Arrange
        $listener = new class () {
            #[EventListener] public function handleEvent(TestEvent $event): void
            {
            }
        };
        $this->provider->register($listener);

        // Act
        $registry = $this->provider->getBusinessListenerRegistry();

        // Assert
        $key = $listener::class . '::handleEvent';
        $this->assertArrayHasKey($key, $registry);
        $this->assertTrue($registry[$key]);
    }
}
