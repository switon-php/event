<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Unit;

use Switon\Eventing\EventDispatcherContext;
use Switon\Eventing\Tests\TestCase;

class EventDispatcherContextTest extends TestCase
{
    public function testJsonSerializeReturnsAggregatedStatsWhenEmpty(): void
    {
        $context = new EventDispatcherContext();

        $this->assertSame(
            [
                'observabilityCallCounts' => [
                    '_keys' => [],
                    '_count' => 0,
                    '_total' => 0,
                ],
                'businessCallCounts' => [
                    '_keys' => [],
                    '_count' => 0,
                    '_total' => 0,
                ],
            ],
            $context->jsonSerialize()
        );
    }

    public function testJsonSerializeAggregatesKeysCountsAndTotals(): void
    {
        $context = new EventDispatcherContext();
        $context->observabilityCallCounts['A::m'] = 2;
        $context->observabilityCallCounts['B::n'] = 3;
        $context->businessCallCounts['C::x'] = 1;
        $context->businessCallCounts['Closure'] = 4;

        $data = $context->jsonSerialize();

        $this->assertSame(['A::m', 'B::n'], $data['observabilityCallCounts']['_keys']);
        $this->assertSame(2, $data['observabilityCallCounts']['_count']);
        $this->assertSame(5, $data['observabilityCallCounts']['_total']);

        $this->assertSame(['C::x', 'Closure'], $data['businessCallCounts']['_keys']);
        $this->assertSame(2, $data['businessCallCounts']['_count']);
        $this->assertSame(5, $data['businessCallCounts']['_total']);
    }
}
