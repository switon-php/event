<?php

declare(strict_types=1);

namespace Switon\Eventing;

use IteratorAggregate;
use SplDoublyLinkedList;
use Traversable;

/**
 * Stores event listeners grouped by priority.
 *
 * Use when eventing internals need direct access to priority buckets while PSR-facing callers still iterate one callable at a time.
 *
 * @see \Switon\Eventing\EventDispatcher
 *
 * @implements IteratorAggregate<int, callable|array{object, string}>
 *
 * @see \Switon\Eventing\ListenerProvider
 */
class PrioritizedListeners implements IteratorAggregate
{
    /** @var array<int, SplDoublyLinkedList<callable|array{object, string}>> */
    protected array $groups = [];

    /**
     * @param array<int, SplDoublyLinkedList<callable|array{object, string}>> $groups
     */
    public function __construct(array $groups = [])
    {
        $this->groups = $groups;
        ksort($this->groups);
    }

    public function add(callable $listener, int $priority = 0): void
    {
        if (($listeners = $this->groups[$priority] ?? null) === null) {
            $listeners = $this->groups[$priority] = new SplDoublyLinkedList();
            ksort($this->groups);
        }

        $listeners->push($listener);
    }

    /**
     * @return array<int, SplDoublyLinkedList<callable|array{object, string}>>
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    public function getIterator(): Traversable
    {
        foreach ($this->groups as $listeners) {
            foreach ($listeners as $listener) {
                yield $listener;
            }
        }
    }
}
