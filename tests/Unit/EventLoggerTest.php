<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Switon\Core\Categorizable;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\EventLogger;
use Switon\Eventing\EventLogInterface;
use Switon\Eventing\EventSilent;
use Switon\Eventing\ListenerProviderInterface;
use Switon\Eventing\Severity;
use Switon\Eventing\Tests\Fixtures\TestEvent;
use Switon\Eventing\Tests\Support\Container;
use Switon\Eventing\Tests\TestCase;
use RuntimeException;

/**
 * Unit coverage for {@see EventLogger} (same scenarios as integration tests; unit suite only).
 */
#[AllowMockObjectsWithoutExpectations]
final class EventLoggerTest extends TestCase
{
    protected EventLogger $eventLogger;
    protected MockObject|LoggerInterface $logger;
    protected ListenerProviderInterface $listenerProvider;
    protected Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        \Switon\Core\Runtime::setCoroutineEnabled(false);

        $this->container = new Container();
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->container->set(LoggerInterface::class, $this->logger);
        $this->listenerProvider = $this->container->get(ListenerProviderInterface::class);
        $this->eventLogger = $this->container->make(EventLogger::class);
    }

    public function testOnEventLogsEventsWithDefaultLevel(): void
    {
        $event = new TestEvent('test message');

        $this->logger->expects($this->once())
            ->method('debug')
            ->with($this->anything());

        $this->eventLogger->onEvent($event);
    }

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

    public function testOnEventSkipsSilentEvents(): void
    {
        $event = new class () implements EventSilent {
            public string $message = 'silent event';
        };

        $this->logger->expects($this->never())->method('log');

        $this->eventLogger->onEvent($event);
    }

    public function testBootRegistersWildcardListenerWhenEnabled(): void
    {
        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Event logger boot',
                $this->callback(fn ($context) => ($context['enabled'] ?? null) === true)
            );

        $this->eventLogger->boot();
    }

    public function testBootDoesNotRegisterWildcardWhenDisabled(): void
    {
        $listenerProvider = $this->createMock(ListenerProviderInterface::class);
        $listenerProvider->expects($this->never())->method('on');
        $this->container->replace(ListenerProviderInterface::class, $listenerProvider);

        $eventLogger = $this->container->make(EventLogger::class, [
            'enabled' => false,
        ]);

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Event logger boot',
                $this->callback(fn ($context) => ($context['enabled'] ?? null) === false)
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
            ->willThrowException(new RuntimeException('sink broken'));

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Event logging failed: {error}',
                $this->callback(fn ($context) => ($context['error'] ?? '') === 'sink broken'
                    && ($context['event'] ?? null) === TestEvent::class)
            );

        $this->eventLogger->onEvent($event);
    }

    public function testEventCategoriesAndLevelsAvailableAfterLogging(): void
    {
        $event = new TestEvent('test message');
        $this->eventLogger->onEvent($event);

        $this->assertArrayHasKey(TestEvent::class, $this->eventLogger->getCategoryMapping());
        $this->assertSame(LogLevel::DEBUG, $this->eventLogger->getLevelMapping()[TestEvent::class]);
    }

    public function testOnEventCallsEventLogMethod(): void
    {
        $event = new class () implements EventLogInterface {
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

    public function testOnEventHandlesEventLogInterfaceExceptions(): void
    {
        $event = new class () implements EventLogInterface {
            public function log(object $event, LoggerInterface $logger): void
            {
                throw new RuntimeException('Log error');
            }
        };

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Event logging failed: {error}',
                $this->callback(fn ($context) => ($context['error'] ?? null) === 'Log error'
                    && isset($context['event']))
            );

        $this->eventLogger->onEvent($event);
    }

    public function testOnEventUsesCustomCategory(): void
    {
        $event = new class () implements Categorizable {
            public function getCategory(): string
            {
                return 'custom.category';
            }

            public function __toString(): string
            {
                return 'custom.category';
            }
        };

        $this->logger->expects($this->once())
            ->method('debug')
            ->with(
                $this->callback(fn ($message) => $message instanceof \Switon\Core\Categorized
                    && $message->getCategory() === 'custom.category'),
                $this->anything()
            );

        $this->eventLogger->onEvent($event);
    }

    public function testOnEventAutoGeneratesCategory(): void
    {
        $event = new TestEvent('test message');

        $this->logger->expects($this->once())
            ->method('debug')
            ->with(
                $this->callback(fn ($message) => $message instanceof \Switon\Core\Categorized
                    && $message->getCategory() === 'switon.eventing.tests.fixtures.event'),
                $this->callback(fn ($context) => ($context['message'] ?? null) === 'test message'
                    && ($context['value'] ?? null) === 0)
            );

        $this->eventLogger->onEvent($event);
    }

    public function testOnEventUsesEventWrapperForEventSerialization(): void
    {
        $event = new TestEvent('test message');

        $this->logger->expects($this->once())
            ->method('debug')
            ->with(
                $this->callback(fn ($message) => $message instanceof \Switon\Core\Categorized),
                $this->equalTo(['message' => 'test message', 'value' => 0])
            );

        $this->eventLogger->onEvent($event);
    }

    public function testCategoryForClassStripsEventSegments(): void
    {
        $category = $this->eventLogger->categoryForClass('App\\Event\\User\\UserCreated');
        $this->assertSame('app.user.created', $category);
        $this->assertStringNotContainsString('.event.', $category);
    }

    public function testCategoryGenerationRemovesDuplicateWords(): void
    {
        $event = new class () {
        };
        $className = $event::class;

        $this->eventLogger->onEvent($event);
        $mapping = $this->eventLogger->getCategoryMapping();

        $category = $mapping[$className] ?? null;
        $this->assertNotNull($category);

        $words = explode('.', $category);
        $uniqueWords = array_unique($words);
        $this->assertCount(count($uniqueWords), $words, 'Category should not contain duplicate words');
    }
}
