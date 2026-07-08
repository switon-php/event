<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Fixtures;

use Switon\Core\OwnerAwareInterface;

class OwnerAwareResourceStub implements OwnerAwareInterface
{
    public function __construct(protected OwnerHolderStub $owner)
    {
    }

    public function getOwner(): OwnerHolderStub
    {
        return $this->owner;
    }
}
