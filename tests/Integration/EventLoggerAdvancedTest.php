<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Integration;

use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Switon\Core\Categorizable;
use Switon\Eventing\EventLogInterface;
use Switon\Eventing\EventLogger;
use Switon\Eventing\ListenerProviderInterface;
use Switon\Eventing\Tests\Fixtures\TestEvent;
use Switon\Eventing\Tests\Support\Container;
use Switon\Eventing\Tests\TestCase;

/**
 * Test cases for EventLogger advanced functionality.
 *
 * Tests custom logging, categorization, exception handling, and message wrapper usage.
 */
class EventLoggerAdvancedTest extends TestCase
{
    protected EventLogger $eventLogger;
    protected MockObject|LoggerInterface $logger;
    protected ListenerProviderInterface $listenerProvider;
    protected Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        // Set coroutine disabled for tests
        \Switon\Core\Runtime::setCoroutineEnabled(false);

        // Use pre-configured test container (ContextManager, EventDispatcher, ListenerProvider are already registered)
        $this->container = new Container();

        // Replace Logger with mock for verification
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->container->set(LoggerInterface::class, $this->logger);

        // Get ListenerProvider from container (already registered)
        $this->listenerProvider = $this->container->get(ListenerProviderInterface::class);

        // Create EventLogger
        $this->eventLogger = $this->container->make(EventLogger::class);
    }

    /**
     * Test that onEvent() calls event's log() method when event implements EventLogInterface.
     */
    public function testOnEventCallsEventLogMethod(): void
    {
        $event = new class implements EventLogInterface {
            public bool $logCalled = false;

            public function log(object $event, LoggerInterface $logger): void
            {
                $this->logCalled = true;
                $logger->info('Custom log message');
            }
        };

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Custom log message');

        $this->eventLogger->onEvent($event);
        $this->assertTrue($event->logCalled);
    }

    /**
     * Test that onEvent() handles exceptions from EventLogInterface gracefully.
     */
    public function testOnEventHandlesEventLogInterfaceExceptions(): void
    {
        $event = new class implements EventLogInterface {
            public function log(object $event, LoggerInterface $logger): void
            {
                throw new \RuntimeException('Log error');
            }
        };

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Event logging failed: {error}',
                $this->callback(fn($context) => ($context['error'] ?? null) === 'Log error'
                    && isset($context['event']))
            );

        // Should not throw exception
        $this->eventLogger->onEvent($event);
    }

    /**
     * Test that onEvent() uses custom category when event implements Categorizable.
     */
    public function testOnEventUsesCustomCategory(): void
    {
        $event = new class implements Categorizable {
            public function getCategory(): string
            {
                return 'custom.category';
            }
        };

        $this->logger->expects($this->once())
            ->method('debug')
            ->with(
                $this->callback(fn($message) => $message instanceof \Switon\Core\Categorized
                    && $message->getCategory() === 'custom.category'),
                $this->anything()
            );

        $this->eventLogger->onEvent($event);
    }

    /**
     * Test that onEvent() auto-generates category from class name.
     */
    public function testOnEventAutoGeneratesCategory(): void
    {
        $event = new TestEvent('test message');

        $this->logger->expects($this->once())
            ->method('debug')
            ->with(
                $this->callback(fn($message) => $message instanceof \Switon\Core\Categorized
                    && $message->getCategory() === 'switon.eventing.tests.fixtures.event'),
                $this->callback(fn($context) => ($context['message'] ?? null) === 'test message'
                    && ($context['value'] ?? null) === 0)
            );

        $this->eventLogger->onEvent($event);
    }

    /**
     * Test that onEvent() uses EventWrapper for event serialization.
     */
    public function testOnEventUsesEventWrapperForEventSerialization(): void
    {
        $event = new TestEvent('test message');

        $this->logger->expects($this->once())
            ->method('debug')
            ->with(
                $this->callback(fn($message) => $message instanceof \Switon\Core\Categorized),
                $this->equalTo(['message' => 'test message', 'value' => 0])
            );

        $this->eventLogger->onEvent($event);
    }

    /**
     * Test that generated category strings do not contain redundant Event/Event namespace layers.
     *
     * Verifies that category generation correctly removes "Event" and "Events"
     * namespace layers from the generated category string.
     */
    #[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
    public function testCategoryGenerationRemovesEventNamespaceLayers(): void
    {
        // Class names with \Event\ / \Event\ namespace segments should produce the same category
        $categoryWithEvent = $this->eventLogger->categoryForClass('App\\Event\\User\\UserCreated');
        $categoryWithEvents = $this->eventLogger->categoryForClass('App\\Event\\User\\UserCreated');

        $this->assertSame('app.user.created', $categoryWithEvent);
        $this->assertSame('app.user.created', $categoryWithEvents);

        // Categories should not contain standalone ".event." or ".events." segments
        $this->assertStringNotContainsString('.event.', $categoryWithEvent);
        $this->assertStringNotContainsString('.events.', $categoryWithEvent);
        $this->assertStringNotContainsString('.event.', $categoryWithEvents);
        $this->assertStringNotContainsString('.events.', $categoryWithEvents);
    }

    /**
     * Test that generated category strings do not contain duplicate words.
     *
     * Verifies that category generation correctly removes duplicate words
     * from the generated category string.
     */
    #[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
    public function testCategoryGenerationRemovesDuplicateWords(): void
    {
        // Create an event class that would generate duplicate words
        $event = new class {
        };
        $className = $event::class;

        $this->eventLogger->onEvent($event);
        $mapping = $this->eventLogger->getCategoryMapping();

        $category = $mapping[$className] ?? null;
        $this->assertNotNull($category);

        // Split category and check for duplicates
        $words = explode('.', $category);
        $uniqueWords = array_unique($words);
        $this->assertCount(count($uniqueWords), $words, 'Category should not contain duplicate words');
    }
}
