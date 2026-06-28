<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Fixtures;

use Switon\Core\SenderAwareInterface;

/** @internal Stand-in for a client-level HTTP event (sender is the client). */
final class HttpClientSenderTestEvent implements SenderAwareInterface
{
    public function __construct(public HttpClientOwnerStubInterface $sender, public string $payload = '')
    {
    }

    public function getSender(): HttpClientOwnerStubInterface
    {
        return $this->sender;
    }
}
