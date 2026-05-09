<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface as PsrListenerProviderInterface;
use Switon\Core\ContainerInterface;
use Switon\Eventing\EventDispatcher;
use Switon\Eventing\EventDispatcherInterface;
use Switon\Eventing\EventLoggerInterface;
use Switon\Eventing\ListenerDiscoveryInterface;
use Switon\Eventing\ListenerProvider;
use Switon\Eventing\ListenerProviderInterface;
use Switon\Eventing\ServiceProvider;
use Switon\Eventing\Tests\TestCase;
use Switon\Eventing\Tests\Support\Container;

/**
 * Test cases for ServiceProvider class.
 *
 * Tests service registration and bootstrap functionality.
 */
#[AllowMockObjectsWithoutExpectations]
class ServiceProviderTest extends TestCase
{
    protected ServiceProvider $serviceProvider;

    protected function setUp(): void
    {
        parent::setUp();

        // Use pre-configured test container (ContextManager is already registered)
        $this->serviceProvider = new ServiceProvider();
    }

    /**
     * Test that register() maps PSR-14 EventDispatcherInterface to Switon's EventDispatcherInterface.
     */
    public function testRegisterMapsPsrEventDispatcherInterface(): void
    {

        // Arrange
        $this->serviceProvider->register($this->container);

        // Act
        $dispatcher = $this->container->get(PsrEventDispatcherInterface::class);

        // Assert
        $this->assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
    }

    /** Test that register() maps PSR-14 ListenerProviderInterface to the listener provider. */
    public function testRegisterMapsPsrListenerProviderInterface(): void
    {
        // Arrange
        $this->serviceProvider->register($this->container);

        // Act
        $provider = $this->container->get(PsrListenerProviderInterface::class);

        // Assert
        $this->assertInstanceOf(ListenerProviderInterface::class, $provider);
        $this->assertInstanceOf(ListenerProvider::class, $provider);
    }

    /** Test that PSR, Switon, and concrete listener-provider IDs resolve to the same singleton. */
    public function testRegisterMapsListenerProviderIdsToSameInstance(): void
    {
        // Arrange
        $this->serviceProvider->register($this->container);

        // Act
        $psrProvider = $this->container->get(PsrListenerProviderInterface::class);
        $eventProvider = $this->container->get(ListenerProviderInterface::class);
        // Assert
        $this->assertSame($eventProvider, $psrProvider);
    }

    /**
     * Test that register() maps Switon's ListenerProviderInterface to ListenerProvider.
     */
    public function testRegisterMapsSwitonListenerProviderInterface(): void
    {
        // Check for optional dependency and skip if not available
        if (!interface_exists(ListenerProviderInterface::class, true)) {
            $this->markTestSkipped('ListenerProviderInterface not available');
            return;
        }

        // Arrange
        $this->serviceProvider->register($this->container);

        // Act
        $provider = $this->container->get(ListenerProviderInterface::class);

        // Assert
        $this->assertInstanceOf(ListenerProviderInterface::class, $provider);
    }

    /**
     * Test that register() method completes without error.
     */
    public function testRegisterCompletesWithoutError(): void
    {
        // Act & Assert - Should not throw exception
        try {
            $this->serviceProvider->register($this->container);
            $this->assertTrue(true); // If we reach here, test passes
        } catch (\Exception $e) {
            $this->fail('register() should not throw exception: ' . $e->getMessage());
        }
    }

    /**
     * Test that register() sets all required service mappings.
     */
    public function testRegisterSetsAllRequiredServiceMappings(): void
    {
        // Arrange
        $this->serviceProvider->register($this->container);

        // Act & Assert - Verify all PSR-14 mappings
        $this->assertTrue($this->container->has(PsrEventDispatcherInterface::class));
        $this->assertTrue($this->container->has(PsrListenerProviderInterface::class));

        // Act & Assert - Verify Switon-specific mappings
        $this->assertTrue($this->container->has(ListenerProviderInterface::class));
    }

    /**
     * Test that boot() initializes the event logger.
     */
    public function testBootInitializesEventLogger(): void
    {
        // Arrange
        $mockDiscovery = $this->createMock(ListenerDiscoveryInterface::class);
        $mockDiscovery->expects($this->once())
            ->method('discover');

        $mockLogger = $this->createMock(EventLoggerInterface::class);
        $mockLogger->expects($this->once())
            ->method('boot');

        // Set mocks in container BEFORE make() so they get autowired
        $this->container->set(ListenerDiscoveryInterface::class, $mockDiscovery);
        $this->container->set(EventLoggerInterface::class, $mockLogger);

        // Create ServiceProvider with autowired dependencies
        $serviceProvider = $this->container->make(ServiceProvider::class);

        // Act
        $serviceProvider->boot();

        // Assert - verified by mock expectations above
    }

    /**
     * Test that getDefinition() returns correct definitions after register().
     */
    public function testGetDefinitionReturnsCorrectDefinitions(): void
    {
        // Arrange
        $this->serviceProvider->register($this->container);

        // Act & Assert
        $this->assertSame(EventDispatcherInterface::class, $this->container->getDefinition(PsrEventDispatcherInterface::class));
        $this->assertSame(ListenerProviderInterface::class, $this->container->getDefinition(PsrListenerProviderInterface::class));
        $this->assertNull($this->container->getDefinition(ListenerProviderInterface::class));
        $this->assertSame(ListenerProviderInterface::class, $this->container->getDefinition(ListenerProvider::class));
    }

    /**
     * Test that register() properly overwrites existing definitions.
     */
    public function testRegisterOverwritesExistingDefinitions(): void
    {
        // Arrange - Remove existing event system setup from Container constructor
        // Container constructor automatically sets up event system, so we need to remove it first
        $this->container->remove(PsrEventDispatcherInterface::class);
        $this->container->remove(EventDispatcherInterface::class);
        $this->container->remove(ListenerProviderInterface::class);

        // Set a custom implementation
        $customDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->container->set(PsrEventDispatcherInterface::class, $customDispatcher);

        // Act
        $this->serviceProvider->register($this->container);

        // Act & Assert - ServiceProvider should override the custom implementation
        $dispatcher = $this->container->get(PsrEventDispatcherInterface::class);
        $this->assertNotSame($customDispatcher, $dispatcher);
        // After ServiceProvider.register(), it should map to EventDispatcherInterface::class
        // which will resolve to EventDispatcher implementation
        // Note: Container may wrap it with TestEventDispatcher for testing, so check the actual implementation
        $this->assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        $this->assertInstanceOf(EventDispatcher::class, $dispatcher);
    }

    /**
     * Test that ServiceProvider has correct interface implementation.
     */
    public function testServiceProviderImplementsCorrectInterface(): void
    {
        // Check for optional dependency and skip if not available
        if (!interface_exists(\Switon\Core\ServiceProviderInterface::class, true)) {
            $this->markTestSkipped('Core package ServiceProviderInterface not available');
            return;
        }

        // Assert
        $this->assertInstanceOf(\Switon\Core\ServiceProviderInterface::class, $this->serviceProvider);
    }

}
