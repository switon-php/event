<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Switon\Eventing\Attribute\EventListener;
use Switon\Eventing\ObservabilityProbe;
use Switon\Eventing\Event\BusinessRegistered;
use Switon\Eventing\Event\ObservabilityRegistered;
use Switon\Eventing\Event\WildcardObservabilityRegistered;
use Switon\Eventing\EventDispatcherInterface;
use Switon\Eventing\ListenerProvider;
use Switon\Eventing\Tests\Fixtures\TestEvent;
use Switon\Eventing\Tests\Fixtures\TestJsonSerializableEvent;
use Switon\Eventing\Tests\TestCase;

#[AllowMockObjectsWithoutExpectations]
class ListenerProviderAddTest extends TestCase
{
    public function testAddResolvesClassStringAndDispatchesBusinessRegistered(): void
    {
        $provider = new ListenerProvider();
        $container = $this->createMock(ContainerInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $listener = new ListenerProviderAddBusinessListener();

        $this->setProperty($provider, 'container', $container);
        $this->setProperty($provider, 'eventDispatcher', $eventDispatcher);

        $container->expects($this->once())
            ->method('get')
            ->with(ListenerProviderAddBusinessListener::class)
            ->willReturn($listener);

        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $event): bool {
                return $event instanceof BusinessRegistered
                    && $event->listener === ListenerProviderAddBusinessListener::class
                    && $event->method === 'onEvent'
                    && $event->eventClass === TestEvent::class;
            }))
            ->willReturnArgument(0);

        $provider->register(ListenerProviderAddBusinessListener::class);

        $listeners = $provider->getListeners();
        $this->assertSame(
            [ListenerProviderAddBusinessListener::class . '::onEvent'],
            $listeners[TestEvent::class]
        );
    }

    public function testAddSkipsDuplicateRegistrationWhenContainerReturnsSameInstance(): void
    {
        $provider = new ListenerProvider();
        $container = $this->createMock(ContainerInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $listener = new ListenerProviderAddBusinessListener();

        $this->setProperty($provider, 'container', $container);
        $this->setProperty($provider, 'eventDispatcher', $eventDispatcher);

        $container->expects($this->exactly(2))
            ->method('get')
            ->with(ListenerProviderAddBusinessListener::class)
            ->willReturn($listener);

        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(BusinessRegistered::class))
            ->willReturnArgument(0);

        $provider->register(ListenerProviderAddBusinessListener::class);
        $provider->register(ListenerProviderAddBusinessListener::class);

        $listeners = $provider->getListenersForEvent(new TestEvent());
        $count = 0;
        foreach ($listeners as $handler) {
            $count++;
        }
        $this->assertSame(1, $count);
    }

    public function testAddDispatchesWildcardObservabilityRegisteredForObjectListener(): void
    {
        $provider = new ListenerProvider();
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->setProperty($provider, 'eventDispatcher', $eventDispatcher);

        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (object $event): bool {
                return $event instanceof WildcardObservabilityRegistered
                    && $event->listener === ListenerProviderAddObservabilityListener::class
                    && $event->method === 'onAnyEvent';
            }))
            ->willReturnArgument(0);

        $provider->register(new ListenerProviderAddObservabilityListener());

        $listeners = $provider->getListeners();
        $this->assertSame(
            [ListenerProviderAddObservabilityListener::class . '::onAnyEvent'],
            $listeners['*']
        );
    }

    public function testAddDispatchesOneRegistrationEventPerUnionType(): void
    {
        $provider = new ListenerProvider();
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->setProperty($provider, 'eventDispatcher', $eventDispatcher);

        $seenEventClasses = [];
        $eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (object $event) use (&$seenEventClasses): object {
                if ($event instanceof BusinessRegistered) {
                    $seenEventClasses[] = $event->eventClass;
                }
                return $event;
            });

        $provider->register(new ListenerProviderAddUnionListener());

        sort($seenEventClasses);
        $this->assertSame(
            [TestEvent::class, TestJsonSerializableEvent::class],
            $seenEventClasses
        );
    }

    public function testAddDispatchesObservabilityRegisteredForEachUnionMember(): void
    {
        $provider = new ListenerProvider();
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->setProperty($provider, 'eventDispatcher', $eventDispatcher);

        $seen = [];
        $eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (object $event) use (&$seen): object {
                if ($event instanceof ObservabilityRegistered) {
                    $seen[] = $event->eventClass;
                }
                return $event;
            });

        $provider->register(new ListenerProviderAddUnionObservabilityListener());

        sort($seen);
        $this->assertSame([TestEvent::class, TestJsonSerializableEvent::class], $seen);
    }

    public function testRegisterIgnoresListenerClassWhenContainerThrowsNotFound(): void
    {
        $provider = new ListenerProvider();
        $container = $this->createMock(ContainerInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->setProperty($provider, 'container', $container);
        $this->setProperty($provider, 'eventDispatcher', $eventDispatcher);

        $container->expects($this->once())
            ->method('get')
            ->with('Optional\\MissingListener')
            ->willThrowException(new ListenerProviderAddNotFound('missing'));

        $eventDispatcher->expects($this->never())->method('dispatch');

        $provider->register('Optional\\MissingListener');

        $this->assertSame([], $provider->getListeners());
    }

    public function testRegisterPropagatesContainerExceptionFromContainer(): void
    {
        $provider = new ListenerProvider();
        $container = $this->createMock(ContainerInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->setProperty($provider, 'container', $container);
        $this->setProperty($provider, 'eventDispatcher', $eventDispatcher);

        $container->expects($this->once())
            ->method('get')
            ->willThrowException(new ListenerProviderAddContainerException('broken'));

        $eventDispatcher->expects($this->never())->method('dispatch');

        $this->expectException(ContainerExceptionInterface::class);
        $this->expectExceptionMessage('broken');

        $provider->register(ListenerProviderAddBusinessListener::class);
    }

    protected function setProperty(object $target, string $name, mixed $value): void
    {
        $reflection = new \ReflectionClass($target);
        $property = $reflection->getProperty($name);
        $property->setValue($target, $value);
    }
}

class ListenerProviderAddBusinessListener
{
    #[EventListener]
    public function onEvent(TestEvent $event): void
    {
    }
}

class ListenerProviderAddObservabilityListener implements ObservabilityProbe
{
    #[EventListener]
    public function onAnyEvent(object $event): void
    {
    }
}

class ListenerProviderAddUnionListener
{
    #[EventListener]
    public function onUnion(TestEvent|TestJsonSerializableEvent $event): void
    {
    }
}

class ListenerProviderAddUnionObservabilityListener implements ObservabilityProbe
{
    #[EventListener]
    public function onUnion(TestEvent|TestJsonSerializableEvent $event): void
    {
    }
}

final class ListenerProviderAddNotFound extends \RuntimeException implements NotFoundExceptionInterface
{
}

final class ListenerProviderAddContainerException extends \RuntimeException implements ContainerExceptionInterface
{
}
