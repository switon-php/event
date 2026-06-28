<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Fixtures;

use Switon\Core\OwnerAwareInterface;

final class OwnerAwareResourceStub implements OwnerAwareInterface
{
    public function __construct(protected OwnerHolderStub $owner)
    {
    }

    public function getOwner(): OwnerHolderStub
    {
        return $this->owner;
    }
}
