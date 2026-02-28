<?php

namespace App\Enum;

enum MeterReadingType: string
{
    case ACTUAL = 'actual';
    case ESTIMATED = 'estimated';
    case CORRECTED = 'corrected';
    case INITIAL = 'initial';

    /**
     * Get the label for the reading type.
     */
    public function label(): string
    {
        return match ($this) {
            self::ACTUAL => 'Actual Reading',
            self::ESTIMATED => 'Estimated Reading',
            self::CORRECTED => 'Corrected Reading',
            self::INITIAL => 'Initial Reading',
        };
    }

    /**
     * Check if the reading is estimated.
     */
    public function isEstimated(): bool
    {
        return $this === self::ESTIMATED;
    }

    /**
     * Check if the reading is actual.
     */
    public function isActual(): bool
    {
        return $this === self::ACTUAL;
    }

    /**
     * Check if the reading is corrected.
     */
    public function isCorrected(): bool
    {
        return $this === self::CORRECTED;
    }
}