<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Fixtures;

use Switon\Core\SenderAwareInterface;

/** @internal Stand-in for a future engine-level HTTP client event (sender = engine). */
final class EngineAwareSenderTestEvent implements SenderAwareInterface
{
    public function __construct(public EngineAwareResourceStub $sender, public string $payload = '')
    {
    }

    public function getSender(): EngineAwareResourceStub
    {
        return $this->sender;
    }
}
