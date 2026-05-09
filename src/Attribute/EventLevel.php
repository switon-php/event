<?php

declare(strict_types=1);

namespace Switon\Eventing\Attribute;

use Attribute;
use Switon\Eventing\Severity;

/**
 * Declares default log level for an event class.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class EventLevel
{
    public function __construct(public Severity $severity)
    {
    }
}
