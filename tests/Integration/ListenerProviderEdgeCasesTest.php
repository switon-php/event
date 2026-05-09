<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Integration;

use Switon\Eventing\Attribute\EventListener;
use Switon\Core\Exception\MisuseException;
use Switon\Eventing\ListenerProviderInterface;
use Switon\Eventing\Tests\Fixtures\TestEvent;
use Switon\Eventing\Tests\TestCase;
use Switon\Eventing\Tests\Support\Container;

/**
 * Test cases for ListenerProvider edge cases and error conditions.
 *
 * Tests method validation, duplicate prevention, and invalid configurations.
 */
class ListenerProviderEdgeCasesTest extends TestCase
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
     * Test that add() skips methods without Event attribute.
     */
    public function testAddSkipsMethodsWithoutAttribute(): void
    {
        // Arrange
        $listener = new class {
            public function regularMethod(TestEvent $event): void
            {
                // This should not be registered
            }
        };
        $this->provider->register($listener);

        // Act
        $listeners = $this->provider->getListenersForEvent(new TestEvent());
        $count = 0;
        foreach ($listeners as $handler) {
            $count++;
        }

        // Assert
        $this->assertSame(0, $count);
    }

    /** Test that add() throws on methods with multiple parameters. */
    public function testAddThrowsOnMethodsWithMultipleParameters(): void
    {
        // Arrange
        $listener = new class {
            #[EventListener] public function invalidMethod(TestEvent $event, string $extra): void
            {
                // Invalid listener signature: listeners must accept exactly one event parameter.
            }
        };

        $this->expectException(MisuseException::class);
        $this->expectExceptionMessage('must declare exactly one parameter');

        // Act
        $this->provider->register($listener);
    }

    /**
     * Test that add() prevents duplicate registration of the same object.
     */
    public function testAddPreventsDuplicateRegistration(): void
    {
        // Arrange
        $listener = new class {
            public int $callCount = 0;

            #[EventListener] public function handleEvent(TestEvent $event): void
            {
                $this->callCount++;
            }
        };

        $this->provider->register($listener);
        $this->provider->register($listener); // Register again

        // Act
        $listeners = $this->provider->getListenersForEvent(new TestEvent());
        $count = 0;
        foreach ($listeners as $handler) {
            $count++;
            $handler(new TestEvent());
        }

        // Assert (should only be registered once)
        $this->assertSame(1, $count);
        $this->assertSame(1, $listener->callCount);
    }

    public function testRegisterIgnoresMissingListenerClassString(): void
    {
        $this->provider->register('Switon\\Eventing\\Tests\\Fixtures\\MissingOptionalListener');

        $listeners = $this->provider->getListenersForEvent(new TestEvent());
        $count = 0;
        foreach ($listeners as $handler) {
            $count++;
        }

        $this->assertSame(0, $count);
    }
}
