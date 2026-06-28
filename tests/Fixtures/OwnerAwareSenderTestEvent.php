<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Fixtures;

use Switon\Core\SenderAwareInterface;

final class OwnerAwareSenderTestEvent implements SenderAwareInterface
{
    public function __construct(public OwnerAwareResourceStub $sender, public string $payload = '')
    {
    }

    public function getSender(): OwnerAwareResourceStub
    {
        return $this->sender;
    }
}
