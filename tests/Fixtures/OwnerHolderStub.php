<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Fixtures;

final class OwnerHolderStub
{
    public function __construct(public string $name = 'holder')
    {
    }
}
