<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Fixtures;

use Switon\Core\OwnerAwareInterface;

final class HolderWithOwnerStub
{
    public function __construct(public OwnerHolderStub $client)
    {
    }
}
