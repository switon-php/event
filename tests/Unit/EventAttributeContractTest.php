<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Unit;

use ReflectionClass;
use ReflectionMethod;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Attribute\EventListener;
use Switon\Eventing\Severity;
use Switon\Eventing\Tests\TestCase;

/**
 * Reflection-based checks that EventListener / EventLevel / Severity behave as documented contracts.
 */
class EventAttributeContractTest extends TestCase
{
    public function testEventListenerAttributeMarksEventListenersWithPriority(): void
    {
        $listener = new class () {
            #[EventListener(priority: 100)] public function handleEvent($event): void
            {
            }
        };

        $method = new ReflectionMethod($listener, 'handleEvent');
        $attributes = $method->getAttributes(EventListener::class);

        $this->assertCount(1, $attributes);

        $eventAttr = $attributes[0]->newInstance();
        $this->assertSame(100, $eventAttr->priority);
    }

    public function testEventLevelAttributeSpecifiesLoggingLevel(): void
    {
        $reflection = new ReflectionClass(TestEventLevelClass::class);
        $attributes = $reflection->getAttributes(EventLevel::class);

        $this->assertCount(1, $attributes);

        $levelAttr = $attributes[0]->newInstance();
        $this->assertSame(Severity::ERROR, $levelAttr->severity);
    }

    public function testLevelEnumProvidesPsr3LogLevels(): void
    {
        $levels = [
            Severity::DEBUG,
            Severity::INFO,
            Severity::WARNING,
            Severity::ERROR,
        ];

        foreach ($levels as $level) {
            $this->assertInstanceOf(Severity::class, $level);
            $this->assertIsString($level->value);
        }

        $result = match (Severity::ERROR) {
            Severity::DEBUG => 'debug',
            Severity::INFO => 'info',
            Severity::ERROR => 'error',
            default => 'unknown'
        };

        $this->assertSame('error', $result);
    }
}

#[EventLevel(Severity::ERROR)]
class TestEventLevelClass
{
    public function someMethod(): void
    {
    }
}
