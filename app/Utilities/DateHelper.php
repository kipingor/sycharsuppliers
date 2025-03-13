<?php

namespace App\Utilities;

use Carbon\Carbon;

class DateHelper
{
    /**
     * Format a given date.
     */
    public static function formatDate($date, string $format = 'Y-m-d'): string
    {
        return Carbon::parse($date)->format($format);
    }

    /**
     * Format a date with a human-readable difference.
     */
    public static function humanReadableDate($date): string
    {
        return Carbon::parse($date)->diffForHumans();
    }

    /**
     * Get the start and end dates of the current billing cycle.
     */
    public static function getBillingCycle(): array
    {
        return [
            'start' => Carbon::now()->startOfMonth()->format('Y-m-d'),
            'end' => Carbon::now()->endOfMonth()->format('Y-m-d'),
        ];
    }

    /**
     * Get the number of days between two dates.
     */
    public static function daysBetween($startDate, $endDate): int
    {
        return Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate));
    }

    /**
     * Check if a given date is in the past.
     */
    public static function isPastDate($date): bool
    {
        return Carbon::parse($date)->isPast();
    }

    /**
     * Check if a given date is in the future.
     */
    public static function isFutureDate($date): bool
    {
        return Carbon::parse($date)->isFuture();
    }

    /**
     * Get the current timestamp in a specific format.
     */
    public static function currentTimestamp(string $format = 'Y-m-d H:i:s'): string
    {
        return Carbon::now()->format($format);
    }
}
