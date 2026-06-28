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

        $provider->on(null, [$objectHandler, 'handleEvent']);
        $provider->on(null, [ListenerProviderListObjectHandler::class, 'handleStatic']);
        $provider->on(null, $invokableHandler);
        $provider->on(null, static function (object $event): void {
        });

        $listeners = $provider->getListeners();

        $this->assertSame('*', array_key_first($listeners));
        $this->assertSame(['Closure'], $listeners['*']);
        $this->assertSame(
            [
                ListenerProviderListObjectHandler::class . '::handleEvent',
                ListenerProviderListObjectHandler::class . '::handleStatic',
                ListenerProviderListInvokableHandler::class . '::__invoke',
            ],
            $listeners[TestEvent::class]
        );
    }

    public function testGetListenersOrdersByPriorityAscending(): void
    {
        $provider = new ListenerProvider();
        $handler = new ListenerProviderListPriorityHandler();

        $provider->on(null, [$handler, 'low'], 10);
        $provider->on(null, [$handler, 'high'], -10);
        $provider->on(null, [$handler, 'mid'], 0);

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
