<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Unit;

use Switon\Eventing\PrioritizedListeners;
use Switon\Eventing\Tests\TestCase;
use SplDoublyLinkedList;

final class PrioritizedListenersTest extends TestCase
{
    public function testIterationYieldsLowerPriorityGroupsFirst(): void
    {
        $listeners = new PrioritizedListeners();
        $order = [];

        $listeners->add(static function () use (&$order): void {
            $order[] = 'late';
        }, 10);
        $listeners->add(static function () use (&$order): void {
            $order[] = 'early';
        }, 0);

        foreach ($listeners as $listener) {
            $listener();
        }

        self::assertSame(['early', 'late'], $order);
    }

    public function testConstructorAcceptsPrebuiltGroupsAndSortsByPriority(): void
    {
        $high = new SplDoublyLinkedList();
        $high->push(static fn (): string => 'h');

        $low = new SplDoublyLinkedList();
        $low->push(static fn (): string => 'l');

        $listeners = new PrioritizedListeners([
            5 => $high,
            -1 => $low,
        ]);

        $keys = array_keys($listeners->getGroups());
        self::assertSame([-1, 5], $keys);

        $out = [];
        foreach ($listeners as $callable) {
            $out[] = $callable();
        }

        self::assertSame(['l', 'h'], $out);
    }

    public function testAddCreatesBucketAndPreservesFifoOrderWithinSamePriority(): void
    {
        $listeners = new PrioritizedListeners();
        $order = [];

        $listeners->add(static function () use (&$order): void {
            $order[] = 1;
        }, 0);
        $listeners->add(static function () use (&$order): void {
            $order[] = 2;
        }, 0);

        foreach ($listeners as $listener) {
            $listener();
        }

        self::assertSame([1, 2], $order);
    }
}
