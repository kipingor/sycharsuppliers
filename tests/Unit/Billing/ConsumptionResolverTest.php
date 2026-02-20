<?php

namespace Tests\Unit\Billing;

use PHPUnit\Framework\TestCase;
use App\Services\Billing\ConsumptionResolver;

class ConsumptionResolverTest extends TestCase
{
    /**
     * @Test
     */
    public function test_consumption_is_difference_between_readings(): void
    {
        $resolver = new ConsumptionResolver();

        $units = $resolver->resolveForPeriod(1, '2025-01');

        $this->assertEquals(20, $units);
    }
}
