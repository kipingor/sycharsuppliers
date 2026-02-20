<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Meter Reading API Resource
 *
 * Standardizes meter reading responses across API and Inertia.
 * Prevents raw Eloquent model exposure.
 *
 * @mixin \App\Models\MeterReading
 */
class MeterReadingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'meter_id' => $this->meter_id,
            'reading' => $this->reading,
            'reading_value' => $this->reading, // Alias for frontend compatibility
            'reading_date' => $this->reading_date?->format('Y-m-d'),
            'reading_type' => $this->reading_type,
            'notes' => $this->notes,
            'photo_path' => $this->photo_path,
            
            // Consumption (already calculated by model)
            'consumption' => $this->consumption,
            'consumption_display' => $this->getConsumptionDisplay(),
            'previous_reading_value' => $this->previous_reading_value,
            'has_consumption' => $this->consumption > 0,
            'has_previous_reading' => $this->previous_reading_value !== null,

            // Relationships
            'meter' => $this->when(
                $this->relationLoaded('meter'),
                fn () => [
                    'id' => $this->meter->id,
                    'meter_number' => $this->meter->meter_number,
                    'meter_name' => $this->meter->meter_name ?? null,
                    'meter_type' => $this->meter->meter_type ?? null,
                    'status' => $this->meter->status ?? null,
                    'account' => $this->when(
                        $this->meter->relationLoaded('account'),
                        fn () => [
                            'id' => $this->meter->account->id,
                            'name' => $this->meter->account->name,
                            'account_number' => $this->meter->account->account_number,
                        ]
                    ),
                ]
            ),

            'reader' => $this->when(
                $this->relationLoaded('reader') && $this->reader,
                fn () => [
                    'id' => $this->reader->id,
                    'name' => $this->reader->name,
                    'email' => $this->reader->email ?? null,
                ]
            ),

            // Reading status
            'reading_status' => $this->getReadingStatus(),
            'is_first_reading' => $this->previous_reading_value === null,

            // Metadata
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // Summary (when needed for display)
            'summary' => $this->when(
                $request->routeIs('*.show') || $request->input('include_summary'),
                fn () => $this->getSummary()
            ),
        ];
    }

    /**
     * Get formatted consumption display string.
     *
     * @return string|null
     */
    protected function getConsumptionDisplay(): ?string
    {
        if ($this->consumption === null || $this->consumption == 0) {
            return $this->previous_reading_value === null ? 'First Reading' : '0.00 units';
        }

        return number_format($this->consumption, 2) . ' units';
    }

    /**
     * Determine the reading status based on various conditions.
     *
     * @return string
     */
    protected function getReadingStatus(): string
    {
        // First reading (no previous reading)
        if ($this->previous_reading_value === null) {
            return 'first_reading';
        }

        // No consumption
        if ($this->consumption == 0) {
            return 'no_consumption';
        }

        // Normal consumption
        if ($this->consumption > 0) {
            return 'active';
        }

        return 'pending';
    }

    /**
     * Get a human-readable summary of the reading.
     *
     * @return string
     */
    protected function getSummary(): string
    {
        $meterInfo = $this->relationLoaded('meter') 
            ? "Meter {$this->meter->meter_number}" 
            : "Meter #{$this->meter_id}";
        
        $date = $this->reading_date?->format('M d, Y') ?? 'Unknown date';
        
        $summary = "Reading #{$this->id} for {$meterInfo} on {$date}";
        $summary .= " - Current: " . number_format($this->reading, 2);

        if ($this->previous_reading_value !== null) {
            $summary .= ", Previous: " . number_format($this->previous_reading_value, 2);
            $summary .= ", Consumption: " . number_format($this->consumption, 2) . " units";
        } else {
            $summary .= " (First Reading)";
        }

        return $summary;
    }
}