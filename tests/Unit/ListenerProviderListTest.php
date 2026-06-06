<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Unit;

use Switon\Eventing\ListenerProvider;
use Switon\Eventing\Tests\Fixtures\TestEvent;
use Switon\Eventing\Tests\TestCase;

class ListenerProviderListTest extends TestCase
{
    public function testGetListenersNormalizesCallableNamesAndPlacesWildcardFirst(): void
    {
        $provider = new ListenerProvider();

        $objectHandler = new ListenerProviderListObjectHandler();
        $invokableHandler = new ListenerProviderListInvokableHandler();

        $provider->on(TestEvent::class, [$objectHandler, 'handleEvent']);
        $provider->on(TestEvent::class, [ListenerProviderListObjectHandler::class, 'handleStatic']);
        $provider->on(TestEvent::class, $invokableHandler);
        $provider->on(TestEvent::class, 'strlen');
        $provider->on('*', static function (object $event): void {
        });

        $listeners = $provider->getListeners();

        $this->assertSame('*', array_key_first($listeners));
        $this->assertSame(['Closure'], $listeners['*']);
        $this->assertSame(
            [
                ListenerProviderListObjectHandler::class . '::handleEvent',
                ListenerProviderListObjectHandler::class . '::handleStatic',
                ListenerProviderListInvokableHandler::class . '::__invoke',
                'strlen',
            ],
            $listeners[TestEvent::class]
        );
    }

    public function testGetListenersOrdersByPriorityAscending(): void
    {
        $provider = new ListenerProvider();
        $handler = new ListenerProviderListPriorityHandler();

        $provider->on(TestEvent::class, [$handler, 'low'], 10);
        $provider->on(TestEvent::class, [$handler, 'high'], -10);
        $provider->on(TestEvent::class, [$handler, 'mid'], 0);

        $listeners = $provider->getListeners();

        $this->assertSame(
            [
                ListenerProviderListPriorityHandler::class . '::high',
                ListenerProviderListPriorityHandler::class . '::mid',
                ListenerProviderListPriorityHandler::class . '::low',
            ],
            $listeners[TestEvent::class]
        );
    }
}

class ListenerProviderListObjectHandler
{
    public function handleEvent(TestEvent $event): void
    {
    }

    public static function handleStatic(TestEvent $event): void
    {
    }
}

class ListenerProviderListInvokableHandler
{
    public function __invoke(TestEvent $event): void
    {
    }
}

class ListenerProviderListPriorityHandler
{
    public function high(TestEvent $event): void
    {
    }

    public function mid(TestEvent $event): void
    {
    }

    public function low(TestEvent $event): void
    {
    }
}
