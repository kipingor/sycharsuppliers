<?php

namespace App\ValueObjects;

use InvalidArgumentException;

class Money
{
    /**
     * Create a new Money instance.
     *
     * @param float $amount The amount in the base currency
     * @param string $currency The currency code (e.g., 'USD', 'KES')
     */
    public function __construct(
        public readonly float $amount,
        public readonly string $currency = 'KES'
    ) {
        if ($this->amount < 0) {
            throw new InvalidArgumentException('Money amount cannot be negative');
        }
    }

    /**
     * Create a Money instance from cents.
     */
    public static function fromCents(int $cents, string $currency = 'KES'): self
    {
        return new self($cents / 100, $currency);
    }

    /**
     * Get the amount in cents.
     */
    public function toCents(): int
    {
        return (int) round($this->amount * 100);
    }

    /**
     * Add money to this instance.
     */
    public function add(Money $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Cannot add money in different currencies');
        }

        return new self($this->amount + $other->amount, $this->currency);
    }

    /**
     * Subtract money from this instance.
     */
    public function subtract(Money $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Cannot subtract money in different currencies');
        }

        return new self($this->amount - $other->amount, $this->currency);
    }

    /**
     * Multiply the money amount.
     */
    public function multiply(float $multiplier): self
    {
        return new self($this->amount * $multiplier, $this->currency);
    }

    /**
     * Divide the money amount.
     */
    public function divide(float $divisor): self
    {
        if ($divisor == 0) {
            throw new InvalidArgumentException('Cannot divide by zero');
        }

        return new self($this->amount / $divisor, $this->currency);
    }

    /**
     * Check if this money is greater than another.
     */
    public function greaterThan(Money $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->amount > $other->amount;
    }

    /**
     * Check if this money is less than another.
     */
    public function lessThan(Money $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->amount < $other->amount;
    }

    /**
     * Check if this money is equal to another.
     */
    public function equals(Money $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->amount === $other->amount;
    }

    /**
     * Check if this money is greater than or equal to another.
     */
    public function greaterThanOrEqual(Money $other): bool
    {
        return $this->greaterThan($other) || $this->equals($other);
    }

    /**
     * Check if this money is less than or equal to another.
     */
    public function lessThanOrEqual(Money $other): bool
    {
        return $this->lessThan($other) || $this->equals($other);
    }

    /**
     * Check if the amount is zero.
     */
    public function isZero(): bool
    {
        return $this->amount === 0.0;
    }

    /**
     * Check if the amount is positive.
     */
    public function isPositive(): bool
    {
        return $this->amount > 0;
    }

    /**
     * Get the formatted money string.
     */
    public function format(): string
    {
        return number_format($this->amount, 2, '.', ',') . ' ' . $this->currency;
    }

    /**
     * Get the absolute value.
     */
    public function abs(): self
    {
        return new self(abs($this->amount), $this->currency);
    }

    /**
     * Assert that another Money instance uses the same currency.
     */
    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Cannot compare money in different currencies: {$this->currency} vs {$other->currency}"
            );
        }
    }

    /**
     * Convert to string.
     */
    public function __toString(): string
    {
        return $this->format();
    }
}
