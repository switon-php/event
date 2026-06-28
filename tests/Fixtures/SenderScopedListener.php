<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Fixtures;

final class SenderScopedListener
{
    public int $calls = 0;

    public string $lastPayload = '';

    public function onSenderAwareTestEvent(SenderAwareTestEvent $event): void
    {
        $this->calls++;
        $this->lastPayload = $event->payload;
    }
}
