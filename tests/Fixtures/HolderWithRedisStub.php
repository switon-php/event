<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Fixtures;

/** @internal Typical service with a Redis client on {@see $redis}. */
class HolderWithRedisStub
{
    public function __construct(public OwnerHolderStub $redis)
    {
    }
}
