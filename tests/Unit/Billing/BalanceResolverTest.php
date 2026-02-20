<?php

namespace Tests\Unit\Billing;

use PHPUnit\Framework\TestCase;
use App\Services\Billing\BalanceResolver;

class BalanceResolverTest extends TestCase
{
    /**
     * @test
     */
    public function test_balance_is_sum_of_bills_minus_payments(): void
    {
        $resolver = new BalanceResolver();

        $balance = $resolver->resolve(1);

        $this->assertEquals(60.00, $balance);
    }
}
