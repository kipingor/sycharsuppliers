<?php

namespace App\Enum;

enum TariffType: string
{
    case RESIDENTIAL = 'residential';
    case COMMERCIAL = 'commercial';
    case INDUSTRIAL = 'industrial';
    case INSTITUTIONAL = 'institutional';

    /**
     * Get the label for the tariff type.
     */
    public function label(): string
    {
        return match ($this) {
            self::RESIDENTIAL => 'Residential',
            self::COMMERCIAL => 'Commercial',
            self::INDUSTRIAL => 'Industrial',
            self::INSTITUTIONAL => 'Institutional',
        };
    }

    /**
     * Check if the tariff is residential.
     */
    public function isResidential(): bool
    {
        return $this === self::RESIDENTIAL;
    }

    /**
     * Check if the tariff is commercial.
     */
    public function isCommercial(): bool
    {
        return $this === self::COMMERCIAL;
    }

    /**
     * Check if the tariff is industrial.
     */
    public function isIndustrial(): bool
    {
        return $this === self::INDUSTRIAL;
    }

    /**
     * Check if the tariff is institutional.
     */
    public function isInstitutional(): bool
    {
        return $this === self::INSTITUTIONAL;
    }

    /**
     * Get all tariff types.
     */
    public static function all(): array
    {
        return self::cases();
    }
}