<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Fixtures;

use Switon\Eventing\Attribute\EventLevel;
use Switon\Core\Categorizable;
use Switon\Eventing\EventLogInterface;
use Switon\Eventing\EventSilent;
use Switon\Eventing\Severity;

/**
 * Test utilities for Event tests.
 *
 * Provides factory methods and helper functions for creating test events and listeners.
 */
class TestEventFactory
{
    /**
     * Creates a simple test event.
     */
    public static function createSimpleEvent(string $message = 'test', int $value = 0): TestEvent
    {
        return new TestEvent($message, $value);
    }

    /**
     * Creates an event with a specific log level.
     *
     * Attribute arguments must be constant expressions; level cannot be passed as a variable
     * into #[EventLevel(...)], so this delegates to fixed-level fixture classes.
     */
    public static function createEventWithLevel(Level $level, string $message = 'test'): object
    {
        return match ($level) {
            Severity::DEBUG => new TestLeveledDebugEvent($message),
            Severity::INFO => new TestLeveledInfoEvent($message),
            Severity::NOTICE => new TestLeveledNoticeEvent($message),
            Severity::WARNING => new TestLeveledWarningEvent($message),
            Severity::ERROR => new TestLeveledErrorEvent($message),
            Severity::CRITICAL => new TestLeveledCriticalEvent($message),
            Severity::ALERT => new TestLeveledAlertEvent($message),
            Severity::EMERGENCY => new TestLeveledEmergencyEvent($message),
        };
    }

    /**
     * Creates a silent event that should not be logged.
     */
    public static function createSilentEvent(string $message = 'silent'): object
    {
        return new class($message) implements EventSilent {
            public function __construct(public string $message)
            {
            }
        };
    }

    /**
     * Creates an event with custom category.
     */
    public static function createEventWithCategory(string $category, string $message = 'test'): object
    {
        return new class($category, $message) implements Categorizable {
            public function __construct(
                private string $category,
                public string  $message
            )
            {
            }

            public function getCategory(): string
            {
                return $this->category;
            }
        };
    }

    /**
     * Creates an event that implements custom logging.
     */
    public static function createEventWithCustomLog(string $customMessage = 'custom log'): object
    {
        return new class($customMessage) implements EventLogInterface {
            public bool $logCalled = false;

            public function __construct(public string $customMessage)
            {
            }

            public function log(object $event, \Psr\Log\LoggerInterface $logger): void
            {
                $this->logCalled = true;
                $logger->info($this->customMessage);
            }
        };
    }

    /**
     * Creates a business listener for testing.
     */
    public static function createBusinessListener(): TestBusinessListener
    {
        return new TestBusinessListener();
    }

    /**
     * Creates an observability listener for testing.
     */
    public static function createObservabilityListener(): TestObservabilityListener
    {
        return new TestObservabilityListener();
    }

    /**
     * Creates a wildcard listener for testing.
     */
    public static function createWildcardListener(): TestWildcardListener
    {
        return new TestWildcardListener();
    }

    /**
     * Creates a listener with specific event handling method.
     */
    public static function createCustomListener(string $methodName = 'handleEvent'): object
    {
        return new class($methodName) {
            public array $handledEvents = [];

            public function __construct(private string $methodName)
            {
            }

            public function __call(string $name, array $arguments): void
            {
                if ($name === $this->methodName) {
                    $this->handledEvents[] = $arguments[0] ?? null;
                }
            }
        };
    }

    /**
     * Creates a JSON serializable event.
     */
    public static function createJsonSerializableEvent(string $name = 'test', array $data = []): TestJsonSerializableEvent
    {
        return new TestJsonSerializableEvent($name, $data);
    }
}

abstract class TestLeveledEventBase
{
    public function __construct(public string $message)
    {
    }
}

#[EventLevel(Severity::DEBUG)]
final class TestLeveledDebugEvent extends TestLeveledEventBase
{
}

#[EventLevel(Severity::INFO)]
final class TestLeveledInfoEvent extends TestLeveledEventBase
{
}

#[EventLevel(Severity::NOTICE)]
final class TestLeveledNoticeEvent extends TestLeveledEventBase
{
}

#[EventLevel(Severity::WARNING)]
final class TestLeveledWarningEvent extends TestLeveledEventBase
{
}

#[EventLevel(Severity::ERROR)]
final class TestLeveledErrorEvent extends TestLeveledEventBase
{
}

#[EventLevel(Severity::CRITICAL)]
final class TestLeveledCriticalEvent extends TestLeveledEventBase
{
}

#[EventLevel(Severity::ALERT)]
final class TestLeveledAlertEvent extends TestLeveledEventBase
{
}

#[EventLevel(Severity::EMERGENCY)]
final class TestLeveledEmergencyEvent extends TestLeveledEventBase
{
}
