<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Fixtures;

use Switon\Core\OwnerAwareInterface;

/** @internal Stand-in for a pooled transport engine resource. */
final class EngineAwareResourceStub implements OwnerAwareInterface
{
    public function __construct(protected HttpClientOwnerStubInterface $owner)
    {
    }

    public function getOwner(): HttpClientOwnerStubInterface
    {
        return $this->owner;
    }
}
