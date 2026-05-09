<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Fixtures;

class TestEvent
{
    public function __construct(
        public string $message = 'test',
        public int    $value = 0,
    )
    {
    }
}
