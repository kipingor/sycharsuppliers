<?php

namespace App\Enum;

enum BillingStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case OVERDUE = 'overdue';
    case PARTIALLY_PAID = 'partially_paid';
    case VOID = 'void';
    case UNPAID = 'unpaid';

    /**
     * Get the label for the billing status.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::PAID => 'Paid',
            self::OVERDUE => 'Overdue',
            self::PARTIALLY_PAID => 'Partially Paid',
            self::VOID => 'Void',
            self::UNPAID => 'Unpaid',
        };
    }

    /**
     * Get the color for the billing status.
     */
    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'blue',
            self::PAID => 'green',
            self::OVERDUE => 'red',
            self::PARTIALLY_PAID => 'yellow',
            self::VOID => 'gray',
            self::UNPAID => 'orange',
        };
    }

    /**
     * Check if the billing status is paid.
     */
    public function isPaid(): bool
    {
        return $this === self::PAID;
    }

    /**
     * Check if the billing status is overdue.
     */
    public function isOverdue(): bool
    {
        return $this === self::OVERDUE;
    }

    /**
     * Check if the billing status is void.
     */
    public function isVoid(): bool
    {
        return $this === self::VOID;
    }

    /**
     * Check if the billing status is pending or unpaid.
     */
    public function isPending(): bool
    {
        return in_array($this, [self::PENDING, self::UNPAID]);
    }
}