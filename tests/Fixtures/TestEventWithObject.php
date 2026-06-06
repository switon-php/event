<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Fixtures;

class TestEventWithObject
{
    public function __construct(
        public string  $name = 'test',
        public ?object $object = null,
    ) {
    }
}
