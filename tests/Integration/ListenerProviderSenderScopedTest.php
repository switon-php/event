<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Integration;

use Switon\Eventing\Exception\InvalidListenerException;
use Switon\Eventing\EventDispatcher;
use Switon\Eventing\EventDispatcherInterface;
use Switon\Eventing\ListenerProvider;
use Switon\Eventing\Tests\Fixtures\EngineAwareResourceStub;
use Switon\Eventing\Tests\Fixtures\EngineAwareSenderTestEvent;
use Switon\Eventing\Tests\Fixtures\HolderWithEmitterStub;
use Switon\Eventing\Tests\Fixtures\HolderWithHttpClientStub;
use Switon\Eventing\Tests\Fixtures\HolderWithOwnerStub;
use Switon\Eventing\Tests\Fixtures\HolderWithRedisStub;
use Switon\Eventing\Tests\Fixtures\HttpClientOwnerStub;
use Switon\Eventing\Tests\Fixtures\HttpClientSenderTestEvent;
use Switon\Eventing\Tests\Fixtures\OwnerAwareResourceStub;
use Switon\Eventing\Tests\Fixtures\OwnerAwareSenderTestEvent;
use Switon\Eventing\Tests\Fixtures\OwnerHolderStub;
use Switon\Eventing\Tests\Fixtures\SenderAwareTestEvent;
use Switon\Eventing\Tests\Fixtures\SenderEmitterStub;
use Switon\Eventing\Tests\Fixtures\SenderScopedListener;
use Switon\Eventing\Tests\Fixtures\SenderScopedListenerWithAttribute;
use Switon\Eventing\Tests\Fixtures\TestEvent;
use Switon\Eventing\Tests\Fixtures\WrongHolderStub;
use Switon\Eventing\Tests\Support\Container;
use Switon\Eventing\Tests\Support\InMemoryContextManager;
use Switon\Eventing\Tests\TestCase;

/**
 * Sender-scoped registration and dispatch.
 *
 * Canonical production patterns (see {@see ListenerProviderInterface::on()}):
 * - Redis: {@see HolderWithRedisStub} + {@see OwnerAwareSenderTestEvent} stand in for
 *   <code>$service->redis</code> + pooled connection events.
 * - HTTP client: {@see HolderWithHttpClientStub} + {@see EngineAwareSenderTestEvent} /
 *   {@see HttpClientSenderTestEvent} stand in for <code>$service->httpClient</code> + engine or client events.
 */
class ListenerProviderSenderScopedTest extends TestCase
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

    public function testSenderScopedListenerMatchesOwnerAwareSenderChain(): void
    {
        $holder = new OwnerHolderStub('primary');
        $other = new OwnerHolderStub('secondary');
        $calls = 0;

        $this->provider->on($holder, static function (OwnerAwareSenderTestEvent $event) use (&$calls): void {
            $calls++;
        });

        $this->dispatcher->dispatch(new OwnerAwareSenderTestEvent(new OwnerAwareResourceStub($holder), 'yes'));
        $this->dispatcher->dispatch(new OwnerAwareSenderTestEvent(new OwnerAwareResourceStub($other), 'no'));

        $this->assertSame(1, $calls);
    }

    public function testSenderScopedListenerPrefersDirectSenderBeforeOwnerChain(): void
    {
        $holder = new OwnerHolderStub('holder');
        $resource = new OwnerAwareResourceStub($holder);
        $order = [];

        $this->provider->on($resource, static function (OwnerAwareSenderTestEvent $event) use (&$order): void {
            $order[] = 'resource';
        });
        $this->provider->on($holder, static function (OwnerAwareSenderTestEvent $event) use (&$order): void {
            $order[] = 'holder';
        });

        $this->dispatcher->dispatch(new OwnerAwareSenderTestEvent($resource));

        $this->assertSame(['resource'], $order);
    }

    public function testOnResolvesHolderPropertyForNonOwnerAwareSenderType(): void
    {
        $emitter = new SenderEmitterStub();
        $holder = new HolderWithEmitterStub($emitter);
        $calls = 0;

        $this->provider->on($holder, static function (SenderAwareTestEvent $event) use (&$calls): void {
            $calls++;
        });

        $this->dispatcher->dispatch(new SenderAwareTestEvent($emitter));

        $this->assertSame(1, $calls);
    }

    public function testOnResolvesHolderPropertyForOwnerAwareOwnerType(): void
    {
        $owner = new OwnerHolderStub('resolved');
        $holder = new HolderWithOwnerStub($owner);
        $calls = 0;

        $this->provider->on($holder, static function (OwnerAwareSenderTestEvent $event) use (&$calls): void {
            $calls++;
        });

        $this->dispatcher->dispatch(new OwnerAwareSenderTestEvent(new OwnerAwareResourceStub($owner)));

        $this->assertSame(1, $calls);
    }

    public function testOnThrowsWhenSenderScopeCannotBeResolved(): void
    {
        $this->expectException(InvalidListenerException::class);

        $this->provider->on(new WrongHolderStub(), static function (OwnerAwareSenderTestEvent $event): void {
        });
    }

    public function testOnResolvesServiceRedisPropertyForOwnerAwareConnectionEvent(): void
    {
        $client = new OwnerHolderStub('audit');
        $other = new OwnerHolderStub('other');
        $service = new HolderWithRedisStub($client);
        $calls = 0;

        $this->provider->on($service, static function (OwnerAwareSenderTestEvent $event) use (&$calls): void {
            $calls++;
        });

        $this->dispatcher->dispatch(new OwnerAwareSenderTestEvent(new OwnerAwareResourceStub($client), 'yes'));
        $this->dispatcher->dispatch(new OwnerAwareSenderTestEvent(new OwnerAwareResourceStub($other), 'no'));

        $this->assertSame(1, $calls);
    }

    public function testOnResolvesServiceHttpClientPropertyForOwnerAwareEngineEvent(): void
    {
        $httpClient = new HttpClientOwnerStub();
        $other = new HttpClientOwnerStub();
        $service = new HolderWithHttpClientStub($httpClient);
        $calls = 0;

        $this->provider->on($service, static function (EngineAwareSenderTestEvent $event) use (&$calls): void {
            $calls++;
        });

        $this->dispatcher->dispatch(new EngineAwareSenderTestEvent(new EngineAwareResourceStub($httpClient), 'yes'));
        $this->dispatcher->dispatch(new EngineAwareSenderTestEvent(new EngineAwareResourceStub($other), 'no'));

        $this->assertSame(1, $calls);
    }

    public function testOnResolvesServiceHttpClientPropertyForHttpClientEvent(): void
    {
        $httpClient = new HttpClientOwnerStub();
        $other = new HttpClientOwnerStub();
        $service = new HolderWithHttpClientStub($httpClient);
        $calls = 0;

        $this->provider->on($service, static function (HttpClientSenderTestEvent $event) use (&$calls): void {
            $calls++;
        });

        $this->dispatcher->dispatch(new HttpClientSenderTestEvent($httpClient, 'yes'));
        $this->dispatcher->dispatch(new HttpClientSenderTestEvent($other, 'no'));

        $this->assertSame(1, $calls);
    }
}
