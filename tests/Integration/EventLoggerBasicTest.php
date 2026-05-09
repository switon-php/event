<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Integration;

use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\EventSilent;
use Switon\Eventing\Severity;
use Switon\Eventing\EventLogger;
use Switon\Eventing\ListenerProviderInterface;
use Switon\Eventing\Tests\Fixtures\TestEvent;
use Switon\Eventing\Tests\Support\Container;
use Switon\Eventing\Tests\TestCase;

/**
 * Test cases for EventLogger basic functionality.
 *
 * Tests basic event logging, level handling, and automatic logging configuration.
 */
class EventLoggerBasicTest extends TestCase
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
     * Test that onEvent() logs events with default DEBUG level.
     */
    public function testOnEventLogsEventsWithDefaultLevel(): void
    {
        $event = new TestEvent('test message');

        $this->logger->expects($this->once())
            ->method('debug')
            ->with($this->anything());

        $this->eventLogger->onEvent($event);
    }

    /**
     * Test that onEvent() uses EventLevel attribute when present.
     */
    public function testOnEventUsesEventLevelAttribute(): void
    {
        $event = new #[EventLevel(Severity::ERROR)] class {
            public string $message = 'error event';
        };

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->anything());

        $this->eventLogger->onEvent($event);
    }

    /**
     * Test that onEvent() skips silent events.
     */
    public function testOnEventSkipsSilentEvents(): void
    {
        $event = new class implements EventSilent {
            public string $message = 'silent event';
        };

        $this->logger->expects($this->never())
            ->method('log');

        $this->eventLogger->onEvent($event);
    }

    /**
     * Test that boot() registers wildcard listener when enabled.
     */
    public function testBootRegistersWildcardListenerWhenEnabled(): void
    {
        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Event logger boot',
                $this->callback(fn($context) => ($context['enabled'] ?? null) === true)
            );

        $this->eventLogger->boot();
    }

    public function testBootDoesNotRegisterWildcardWhenDisabled(): void
    {
        $listenerProvider = $this->createMock(ListenerProviderInterface::class);
        $listenerProvider->expects($this->never())->method('on');
        $this->container->replace(ListenerProviderInterface::class, $listenerProvider);

        $eventLogger = $this->container->make(EventLogger::class);
        $enabled = new \ReflectionProperty(EventLogger::class, 'enabled');
        $enabled->setValue($eventLogger, false);

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Event logger boot',
                $this->callback(fn($context) => ($context['enabled'] ?? null) === false)
            );

        $eventLogger->boot();
    }

    public function testOnEventReusesCachedCategoryAndLevelForSameEventClass(): void
    {
        $event1 = new TestEvent('first');
        $event2 = new TestEvent('second');

        $this->logger->expects($this->exactly(2))
            ->method('debug')
            ->with($this->anything(), $this->anything());

        $this->eventLogger->onEvent($event1);
        $categoryAfterFirst = $this->eventLogger->getCategoryMapping()[TestEvent::class];
        $levelAfterFirst = $this->eventLogger->getLevelMapping()[TestEvent::class];

        $this->eventLogger->onEvent($event2);

        $this->assertSame($categoryAfterFirst, $this->eventLogger->getCategoryMapping()[TestEvent::class]);
        $this->assertSame($levelAfterFirst, $this->eventLogger->getLevelMapping()[TestEvent::class]);
        $this->assertCount(1, $this->eventLogger->getCategoryMapping());
        $this->assertCount(1, $this->eventLogger->getLevelMapping());
    }

    public function testOnEventReportsFailureWhenUnderlyingLoggerThrows(): void
    {
        $event = new TestEvent('fail');

        $this->logger->expects($this->once())
            ->method('debug')
            ->willThrowException(new \RuntimeException('sink broken'));

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Event logging failed: {error}',
                $this->callback(fn($context) => ($context['error'] ?? '') === 'sink broken'
                    && ($context['event'] ?? null) === TestEvent::class)
            );

        $this->eventLogger->onEvent($event);
    }

    /**
     * Test that event categories are available after event logging.
     *
     * Verifies that after logging an event, the category mapping contains
     * the event class and its generated category string.
     */
    #[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
    public function testEventCategoriesAreAvailableAfterLogging(): void
    {
        $event = new TestEvent('test message');
        $this->eventLogger->onEvent($event);

        $mapping = $this->eventLogger->getCategoryMapping();
        $this->assertArrayHasKey(TestEvent::class, $mapping);
        $this->assertIsString($mapping[TestEvent::class]);
    }

    /**
     * Test that event log levels are available after event logging.
     *
     * Verifies that after logging an event, the level mapping contains
     * the event class and its log level.
     */
    #[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
    public function testEventLogLevelsAreAvailableAfterLogging(): void
    {
        $event = new TestEvent('test message');
        $this->eventLogger->onEvent($event);

        $mapping = $this->eventLogger->getLevelMapping();
        $this->assertArrayHasKey(TestEvent::class, $mapping);
        $this->assertSame(LogLevel::DEBUG, $mapping[TestEvent::class]);
    }
}
