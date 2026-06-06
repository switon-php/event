<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Unit;

use Switon\Core\ContextManagerInterface;
use Switon\Core\StopFlow;
use Switon\Eventing\EventDispatcher;
use Switon\Eventing\ListenerProviderInterface;
use Switon\Eventing\PrioritizedListeners;
use Switon\Eventing\Tests\Fixtures\TestBusinessListener;
use Switon\Eventing\Tests\Fixtures\TestEvent;
use Switon\Eventing\Tests\Fixtures\TestObservabilityListener;
use Switon\Eventing\Tests\Support\InMemoryContextManager;
use Switon\Eventing\Tests\Support\NullListenerProvider;
use Switon\Eventing\Tests\TestCase;

final class EventDispatcherTest extends TestCase
{
    public function testDispatchReturnsEventUnchangedWhenListenerProviderIsNotWired(): void
    {
        $dispatcher = $this->createDispatcher(listenerProvider: null);

        $event = new TestEvent('noop');

        self::assertSame($event, $dispatcher->dispatch($event));
    }

    public function testDispatchInvokesWildcardListenersBeforeEventSpecificListeners(): void
    {
        $order = [];

        $wildcard = new PrioritizedListeners();
        $wildcard->add(static function () use (&$order): void {
            $order[] = 'wildcard';
        });

        $specific = new PrioritizedListeners();
        $specific->add(static function () use (&$order): void {
            $order[] = 'specific';
        });

        $provider = new StubListenerProvider($wildcard, $specific);

        $dispatcher = $this->createDispatcher($provider);
        $dispatcher->dispatch(new TestEvent());

        self::assertSame(['wildcard', 'specific'], $order);
    }

    public function testDispatchStopsBeforeEventSpecificWhenWildcardThrowsStopFlow(): void
    {
        $specificRan = false;

        $wildcard = new PrioritizedListeners();
        $wildcard->add(static function (): void {
            throw StopFlow::abort();
        });

        $specific = new PrioritizedListeners();
        $specific->add(static function () use (&$specificRan): void {
            $specificRan = true;
        });

        $provider = new StubListenerProvider($wildcard, $specific);

        $dispatcher = $this->createDispatcher($provider);
        $dispatcher->dispatch(new TestEvent());

        self::assertFalse($specificRan);
    }

    public function testDispatchRecordsObservabilityVersusBusinessInvocationKeys(): void
    {
        $wildcard = new PrioritizedListeners();
        $obs = new TestObservabilityListener();
        $wildcard->add([$obs, 'handleEvent']);

        $specific = new PrioritizedListeners();
        $biz = new TestBusinessListener();
        $specific->add([$biz, 'handleEvent']);

        $provider = new StubListenerProvider($wildcard, $specific);

        $dispatcher = $this->createDispatcher($provider);
        $dispatcher->dispatch(new TestEvent());

        $ctx = $dispatcher->getContext();

        $obsKey = TestObservabilityListener::class . '::handleEvent';
        $bizKey = TestBusinessListener::class . '::handleEvent';

        self::assertSame(1, $ctx->observabilityCallCounts[$obsKey] ?? 0);
        self::assertSame(1, $ctx->businessCallCounts[$bizKey] ?? 0);
    }

    private function createDispatcher(?ListenerProviderInterface $listenerProvider): EventDispatcher
    {
        return new class (new InMemoryContextManager(), $listenerProvider) extends EventDispatcher {
            public function __construct(ContextManagerInterface $contextManager, ?ListenerProviderInterface $listenerProvider)
            {
                $this->contextManager = $contextManager;
                $this->listenerProvider = $listenerProvider ?? new NullListenerProvider();
            }
        };
    }
}

/**
 * Minimal listener provider for dispatcher unit tests (fixed wildcard vs event-specific buckets).
 */
final class StubListenerProvider implements ListenerProviderInterface
{
    public function __construct(
        private readonly PrioritizedListeners $wildcard,
        private readonly PrioritizedListeners $specific,
    ) {
    }

    public function on(string $event, callable $handler, int $priority = 0): void
    {
    }

    public function register(string|object $listener): void
    {
    }

    public function getListeners(): array
    {
        return [];
    }

    public function getListenersForEvent(object $event): PrioritizedListeners
    {
        return $this->specific;
    }

    public function getListenersForWildcard(): PrioritizedListeners
    {
        return $this->wildcard;
    }

    public function getObservabilityListenerRegistry(): array
    {
        return [];
    }

    public function getBusinessListenerRegistry(): array
    {
        return [];
    }
}
