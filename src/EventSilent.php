<?php

declare(strict_types=1);

namespace Switon\Eventing;

/**
 * Marker for events that should be skipped by automatic event logging.
 *
 * Use this for events that are already logged elsewhere or would recurse through the logger.
 */
interface EventSilent
{
}
