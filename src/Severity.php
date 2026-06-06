<?php

declare(strict_types=1);

namespace Switon\Eventing;

/**
 * PSR-3-compatible event severity values.
 */
enum Severity: string
{
    case DEBUG = 'debug';
    case INFO = 'info';
    case NOTICE = 'notice';
    case WARNING = 'warning';
    case ERROR = 'error';
    case CRITICAL = 'critical';
    case ALERT = 'alert';
    case EMERGENCY = 'emergency';
}
