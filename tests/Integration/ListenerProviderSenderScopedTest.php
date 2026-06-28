<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Integration;

use Switon\Eventing\EventDispatcher;
use Switon\Eventing\EventDispatcherInterface;
use Switon\Eventing\ListenerProvider;
use Switon\Eventing\Tests\Fixtures\SenderAwareTestEvent;
use Switon\Eventing\Tests\Fixtures\SenderEmitterStub;
use Switon\Eventing\Tests\Fixtures\SenderScopedListener;
use Switon\Eventing\Tests\Fixtures\SenderScopedListenerWithAttribute;
use Switon\Eventing\Tests\Fixtures\TestEvent;
use Switon\Eventing\Tests\Support\Container;
use Switon\Eventing\Tests\Support\InMemoryContextManager;
use Switon\Eventing\Tests\TestCase;

final class ListenerProviderSenderScopedTest extends TestCase
{
    protected ListenerProvider $provider;

    protected EventDispatcherInterface $dispatcher;

    protected function setUpContainer(): void
    {
        $primary = new SenderEmitterStub();
        $secondary = new SenderEmitterStub();

        $this->container->set(SenderEmitterStub::class . '#primary', $primary);
        $this->container->set(SenderEmitterStub::class . '#secondary', $secondary);

        $this->provider = $this->container->get(ListenerProvider::class);
        $this->dispatcher = new class ($this->container->get(InMemoryContextManager::class), $this->provider) extends EventDispatcher {
            public function __construct($contextManager, $listenerProvider)
            {
                $this->contextManager = $contextManager;
                $this->listenerProvider = $listenerProvider;
            }
        };
    }

    public function testSenderScopedListenerRunsOnlyForMatchingSender(): void
    {
        $primary = $this->container->get(SenderEmitterStub::class . '#primary');
        $secondary = $this->container->get(SenderEmitterStub::class . '#secondary');
        $listener = new SenderScopedListener();

        $this->provider->on($primary, [$listener, 'onSenderAwareTestEvent']);

        $this->dispatcher->dispatch(new SenderAwareTestEvent($primary, 'yes'));
        $this->dispatcher->dispatch(new SenderAwareTestEvent($secondary, 'no'));

        $this->assertSame(1, $listener->calls);
        $this->assertSame('yes', $listener->lastPayload);
    }

    public function testGlobalListenerStillRunsForAllSenders(): void
    {
        $primary = new SenderEmitterStub();
        $secondary = new SenderEmitterStub();
        $count = 0;

        $this->provider->on(null, static function (SenderAwareTestEvent $event) use (&$count): void {
            $count++;
        });

        $this->dispatcher->dispatch(new SenderAwareTestEvent($primary));
        $this->dispatcher->dispatch(new SenderAwareTestEvent($secondary));

        $this->assertSame(2, $count);
    }

    public function testManualSenderScopedOnUsesResolvedInstance(): void
    {
        $primary = $this->container->get(SenderEmitterStub::class . '#primary');
        $delegate = new SenderScopedListenerWithAttribute();

        $this->provider->on($primary, [$delegate, 'onSenderAwareTestEvent']);

        $this->dispatcher->dispatch(new SenderAwareTestEvent($primary));
        $this->dispatcher->dispatch(new SenderAwareTestEvent($this->container->get(SenderEmitterStub::class . '#secondary')));

        $this->assertSame(1, $delegate->calls);
    }

    public function testOnRelativeSenderAliasResolvesFromSenderAwareEvent(): void
    {
        $primary = $this->container->get(SenderEmitterStub::class . '#primary');
        $listener = new SenderScopedListener();

        $this->provider->on('#primary', [$listener, 'onSenderAwareTestEvent']);

        $this->dispatcher->dispatch(new SenderAwareTestEvent($primary, 'alias'));
        $this->dispatcher->dispatch(new SenderAwareTestEvent($this->container->get(SenderEmitterStub::class . '#secondary')));

        $this->assertSame(1, $listener->calls);
        $this->assertSame('alias', $listener->lastPayload);
    }

    public function testOnNamedSenderAliasResolvesFromContainer(): void
    {
        $primary = $this->container->get(SenderEmitterStub::class . '#primary');
        $listener = new SenderScopedListener();

        $this->provider->on(SenderEmitterStub::class . '#primary', [$listener, 'onSenderAwareTestEvent']);

        $this->dispatcher->dispatch(new SenderAwareTestEvent($primary, 'named'));

        $this->assertSame(1, $listener->calls);
        $this->assertSame('named', $listener->lastPayload);
    }

    public function testMergeRunsGlobalBeforeSenderScopedAtSamePriority(): void
    {
        $sender = new SenderEmitterStub();
        $order = [];

        $this->provider->on(null, static function (SenderAwareTestEvent $event) use (&$order): void {
            $order[] = 'global';
        });
        $this->provider->on($sender, static function (SenderAwareTestEvent $event) use (&$order): void {
            $order[] = 'sender';
        });

        $this->dispatcher->dispatch(new SenderAwareTestEvent($sender));

        $this->assertSame(['global', 'sender'], $order);
    }

    public function testNonSenderAwareEventDoesNotInvokeSenderScopedListener(): void
    {
        $sender = new SenderEmitterStub();
        $called = false;

        $this->provider->on($sender, static function (TestEvent $event) use (&$called): void {
            $called = true;
        });

        $this->dispatcher->dispatch(new TestEvent('x'));

        $this->assertFalse($called);
    }
}
