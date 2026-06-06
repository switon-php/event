<?php

declare(strict_types=1);

namespace Switon\Eventing\Attribute;

use Attribute;

/**
 * Marks a method as an event listener for auto-discovery.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class EventListener
{
    public function __construct(public int $priority = 0)
    {
    }
}
