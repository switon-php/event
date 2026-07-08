<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Fixtures;

use Switon\Core\SenderAwareInterface;

class SenderAwareTestEvent implements SenderAwareInterface
{
    public function __construct(public SenderEmitterStub $sender, public string $payload = '')
    {
    }

    public function getSender(): SenderEmitterStub
    {
        return $this->sender;
    }
}
