<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Fixtures;

final class SenderScopedListenerWithAttribute
{
    public int $calls = 0;

    public function onSenderAwareTestEvent(SenderAwareTestEvent $event): void
    {
        $this->calls++;
    }
}
