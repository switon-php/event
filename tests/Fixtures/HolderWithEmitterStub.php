<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Fixtures;

class HolderWithEmitterStub
{
    public function __construct(public SenderEmitterStub $emitter)
    {
    }
}
