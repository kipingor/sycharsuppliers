<?php

namespace App\Services\Meter;

use App\Models\Meter;
use App\Models\MeterReading;
use App\Services\Audit\AuditService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;

/**
 * Meter Reading Service
 *
 * Handles all meter reading operations with validation, audit logging,
 * and data integrity enforcement.
 *
 * @package App\Services\Meter
 */
class MeterReadingService
{
    public function __construct(
        protected AuditService $auditService
    ) {
    }

    /**
     * Create a new meter reading with full validation
     *
     * @param array $data Reading data
     * @return MeterReading
     * @throws ValidationException
     */
    public function createReading(array $data): MeterReading
    {
        // Validate input
        $validated = $this->validateReadingData($data);

        // Get meter
        $meter = Meter::findOrFail($validated['meter_id']);

        // Validate monotonic constraint
        $this->validateMonotonicReading($meter, $validated['reading_value'], $validated['reading_date']);

        // Prevent duplicates
        $this->preventDuplicateReading($meter, $validated['reading_date']);

        // Create reading in transaction
        return DB::transaction(function () use ($validated, $meter) {
            // Calculate consumption
            $previousReading = $this->getPreviousReading($meter, $validated['reading_date']);
            $consumption = $previousReading
                ? max(0, $validated['reading_value'] - $previousReading->reading_value)
                : 0;

            // Create reading
            $reading = MeterReading::create([
                'meter_id' => $validated['meter_id'],
                'reading_value' => $validated['reading_value'],
                'reading_date' => $validated['reading_date'],
                'reading_type' => $validated['reading_type'] ?? 'actual',
                'consumption' => $consumption,
                'reader_id' => $validated['reader_id'] ?? Auth::id(),
                'notes' => $validated['notes'] ?? null,
                'photo_path' => $validated['photo_path'] ?? null,
            ]);

            // Log audit trail
            $this->auditService->logMeterReadingAction(
                action: 'created',
                reading: $reading,
                context: [
                    'previous_reading' => $previousReading?->reading_value,
                    'consumption_calculated' => $consumption,
                    'validation_passed' => true,
                ]
            );

            return $reading;
        });
    }

    /**
     * Update an existing meter reading
     *
     * @param MeterReading $reading Reading to update
     * @param array $data Update data
     * @return MeterReading
     * @throws ValidationException
     */
    public function updateReading(MeterReading $reading, array $data): MeterReading
    {
        // Prevent updates to readings that have been billed
        if ($this->hasBeenBilled($reading)) {
            $this->auditService->logMeterReadingAction(
                action: 'update_prevented',
                reading: $reading,
                context: [
                    'reason' => 'reading_already_billed',
                    'attempted_changes' => $data,
                ]
            );

            throw ValidationException::withMessages([
                'reading_value' => 'Cannot update reading that has already been billed.'
            ]);
        }

        // Store old values for audit
        $oldValues = $reading->only(['reading_value', 'reading_date', 'reading_type', 'notes']);

        // Validate new reading value if changed
        if (isset($data['reading_value']) && $data['reading_value'] != $reading->reading_value) {
            $this->validateMonotonicReading(
                $reading->meter,
                $data['reading_value'],
                $data['reading_date'] ?? $reading->reading_date,
                $reading->id
            );
        }

        return DB::transaction(function () use ($reading, $data, $oldValues) {
            // Update reading
            $reading->update($data);

            // Recalculate consumption if reading value changed
            if (isset($data['reading_value'])) {
                $previousReading = $this->getPreviousReading($reading->meter, $reading->reading_date, $reading->id);
                $reading->consumption = $previousReading
                    ? max(0, $reading->reading - $previousReading->reading)
                    : 0;
                $reading->save();
            }

            // Log audit trail
            $this->auditService->logMeterReadingAction(
                action: 'updated',
                reading: $reading,
                context: [
                    'old_values' => $oldValues,
                    'new_values' => $reading->only(['reading_value', 'reading_date', 'reading_type', 'notes']),
                    'consumption_recalculated' => isset($data['reading_value']),
                ]
            );

            return $reading;
        });
    }

    /**
     * Delete a meter reading
     *
     * @param MeterReading $reading Reading to delete
     * @return bool
     * @throws ValidationException
     */
    public function deleteReading(MeterReading $reading): bool
    {
        // Prevent deletion if billed
        if ($this->hasBeenBilled($reading)) {
            $this->auditService->logMeterReadingAction(
                action: 'delete_prevented',
                reading: $reading,
                context: [
                    'reason' => 'reading_already_billed',
                ]
            );

            throw ValidationException::withMessages([
                'reading_value' => 'Cannot delete reading that has already been billed.'
            ]);
        }

        // Prevent deletion if other readings depend on it
        if ($this->hasDependentReadings($reading)) {
            $this->auditService->logMeterReadingAction(
                action: 'delete_prevented',
                reading: $reading,
                context: [
                    'reason' => 'has_dependent_readings',
                ]
            );

            throw ValidationException::withMessages([
                'reading_value' => 'Cannot delete reading - subsequent readings depend on it for consumption calculation.'
            ]);
        }

        return DB::transaction(function () use ($reading) {
            $readingData = $reading->toArray();
            
            $deleted = $reading->delete();

            if ($deleted) {
                // Log deletion
                $this->auditService->logMeterReadingAction(
                    action: 'deleted',
                    reading: $reading,
                    context: [
                        'deleted_reading_data' => $readingData,
                    ]
                );
            }

            return $deleted;
        });
    }

    /**
     * Create multiple readings in bulk
     *
     * @param array $readingsData Array of reading data
     * @param array $context Additional context (e.g., upload metadata)
     * @return array ['created' => Collection, 'failed' => array]
     */
    public function createBulkReadings(array $readingsData, array $context = []): array
    {
        $created = collect();
        $failed = [];

        DB::transaction(function () use ($readingsData, $context, &$created, &$failed) {
            foreach ($readingsData as $index => $data) {
                try {
                    $reading = $this->createReading($data);
                    $created->push($reading);
                } catch (\Exception $e) {
                    $failed[] = [
                        'index' => $index,
                        'data' => $data,
                        'error' => $e->getMessage(),
                    ];

                    // Log validation failure
                    if (isset($data['meter_id'])) {
                        $meter = Meter::find($data['meter_id']);
                        if ($meter) {
                            $reading = new MeterReading($data);
                            $reading->meter_id = $meter->id;
                            
                            $this->auditService->logMeterReadingAction(
                                action: 'validation_failed',
                                reading: $reading,
                                context: [
                                    'error' => $e->getMessage(),
                                    'bulk_operation' => true,
                                    'bulk_context' => $context,
                                ]
                            );
                        }
                    }
                }
            }

            // Log bulk operation summary
            if ($created->isNotEmpty()) {
                $this->auditService->logBulkMeterReadingCreation(
                    readings: $created->all(),
                    context: array_merge($context, [
                        'total_attempted' => count($readingsData),
                        'total_created' => $created->count(),
                        'total_failed' => count($failed),
                    ])
                );
            }
        });

        return [
            'created' => $created,
            'failed' => $failed,
        ];
    }

    /**
     * Validate monotonic reading constraint
     *
     * Ensures new reading >= previous reading and <= future reading
     *
     * @param Meter $meter Meter instance
     * @param float $newReading New reading value
     * @param Carbon $readingDate Reading date
     * @param int|null $excludeReadingId Reading ID to exclude (for updates)
     * @throws ValidationException
     */
    public function validateMonotonicReading(
        Meter $meter,
        float $newReading,
        Carbon $readingDate,
        ?int $excludeReadingId = null
    ): void {
        // Get previous reading
        $previousReading = MeterReading::where('meter_id', $meter->id)
            ->where('reading_date', '<', $readingDate)
            ->when($excludeReadingId, fn ($q) => $q->where('id', '!=', $excludeReadingId))
            ->orderBy('reading_date', 'desc')
            ->first();

        // Check if new reading is less than previous
        if ($previousReading && $newReading < $previousReading->reading) {
            // Create temporary reading for audit logging
            $tempReading = new MeterReading([
                'meter_id' => $meter->id,
                'reading_value' => $newReading,
                'reading_date' => $readingDate,
            ]);

            $this->auditService->logMeterReadingAction(
                action: 'monotonic_violation',
                reading: $tempReading,
                context: [
                    'previous_reading_value' => $previousReading->reading,
                    'previous_reading_date' => $previousReading->reading_date->toDateString(),
                    'attempted_reading_value' => $newReading,
                    'attempted_reading_date' => $readingDate->toDateString(),
                    'violation_amount' => $previousReading->reading - $newReading,
                ]
            );

            throw ValidationException::withMessages([
                'reading_value' => sprintf(
                    'Reading cannot be less than previous reading. Previous: %s on %s, Attempted: %s',
                    number_format($previousReading->reading, 2),
                    $previousReading->reading_date->format('Y-m-d'),
                    number_format($newReading, 2)
                )
            ]);
        }

        // Get next reading (future reading)
        $futureReading = MeterReading::where('meter_id', $meter->id)
            ->where('reading_date', '>', $readingDate)
            ->when($excludeReadingId, fn ($q) => $q->where('id', '!=', $excludeReadingId))
            ->orderBy('reading_date', 'asc')
            ->first();

        // Check if new reading is greater than future reading
        if ($futureReading && $newReading > $futureReading->reading) {
            // Create temporary reading for audit logging
            $tempReading = new MeterReading([
                'meter_id' => $meter->id,
                'reading_value' => $newReading,
                'reading_date' => $readingDate,
            ]);

            $this->auditService->logMeterReadingAction(
                action: 'monotonic_violation',
                reading: $tempReading,
                context: [
                    'future_reading_value' => $futureReading->reading,
                    'future_reading_date' => $futureReading->reading_date->toDateString(),
                    'attempted_reading_value' => $newReading,
                    'attempted_reading_date' => $readingDate->toDateString(),
                    'violation_amount' => $newReading - $futureReading->reading,
                ]
            );

            throw ValidationException::withMessages([
                'reading_value' => sprintf(
                    'Reading cannot be greater than future reading. Future: %s on %s, Attempted: %s',
                    number_format($futureReading->reading, 2),
                    $futureReading->reading_date->format('Y-m-d'),
                    number_format($newReading, 2)
                )
            ]);
        }
    }

    /**
     * Prevent duplicate readings for same meter in same month
     *
     * @param Meter $meter Meter instance
     * @param Carbon $readingDate Reading date
     * @param int|null $excludeReadingId Reading ID to exclude (for updates)
     * @throws ValidationException
     */
    public function preventDuplicateReading(
        Meter $meter,
        Carbon $readingDate,
        ?int $excludeReadingId = null
    ): void {
        $existingReading = MeterReading::where('meter_id', $meter->id)
            ->whereYear('reading_date', $readingDate->year)
            ->whereMonth('reading_date', $readingDate->month)
            ->when($excludeReadingId, fn ($q) => $q->where('id', '!=', $excludeReadingId))
            ->first();

        if ($existingReading) {
            // Create temporary reading for audit logging
            $tempReading = new MeterReading([
                'meter_id' => $meter->id,
                'reading_date' => $readingDate,
            ]);

            $this->auditService->logMeterReadingAction(
                action: 'duplicate_prevented',
                reading: $tempReading,
                context: [
                    'existing_reading_id' => $existingReading->id,
                    'existing_reading_date' => $existingReading->reading_date->toDateString(),
                    'attempted_reading_date' => $readingDate->toDateString(),
                ]
            );

            throw ValidationException::withMessages([
                'reading_date' => sprintf(
                    'A reading for this meter already exists for %s-%s. Existing reading on %s.',
                    $readingDate->format('F'),
                    $readingDate->format('Y'),
                    $existingReading->reading_date->format('Y-m-d')
                )
            ]);
        }
    }

    /**
     * Check if reading has been used in billing
     *
     * @param MeterReading $reading Reading to check
     * @return bool
     */
    public function hasBeenBilled(MeterReading $reading): bool
    {
        return DB::table('billing_details')
            ->where('meter_id', $reading->meter_id)
            ->where('reading_date', $reading->reading_date)
            ->exists();
    }

    /**
     * Check if reading has dependent readings
     *
     * @param MeterReading $reading Reading to check
     * @return bool
     */
    protected function hasDependentReadings(MeterReading $reading): bool
    {
        return MeterReading::where('meter_id', $reading->meter_id)
            ->where('reading_date', '>', $reading->reading_date)
            ->exists();
    }

    /**
     * Get previous reading for a meter
     *
     * @param Meter $meter Meter instance
     * @param Carbon $beforeDate Get reading before this date
     * @param int|null $excludeReadingId Reading ID to exclude
     * @return MeterReading|null
     */
    protected function getPreviousReading(
        Meter $meter,
        Carbon $beforeDate,
        ?int $excludeReadingId = null
    ): ?MeterReading {
        return MeterReading::where('meter_id', $meter->id)
            ->where('reading_date', '<', $beforeDate)
            ->when($excludeReadingId, fn ($q) => $q->where('id', '!=', $excludeReadingId))
            ->orderBy('reading_date', 'desc')
            ->first();
    }

    /**
     * Validate reading data
     *
     * @param array $data Data to validate
     * @return array Validated data
     * @throws ValidationException
     */
    protected function validateReadingData(array $data): array
    {
        $validator = Validator::make($data, [
            'meter_id' => 'required|exists:meters,id',
            'reading_value' => 'required|numeric|min:0',
            'reading_date' => 'required|date',
            'reading_type' => 'nullable|in:actual,estimated',
            'reader_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string|max:1000',
            'photo_path' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        $validated['reading_date'] = Carbon::parse($validated['reading_date']);

        return $validated;
    }

    /**
     * Get readings for a meter within a date range
     *
     * @param Meter $meter Meter instance
     * @param Carbon|null $from Start date
     * @param Carbon|null $to End date
     * @return Collection
     */
    public function getReadingsForMeter(
        Meter $meter,
        ?Carbon $from = null,
        ?Carbon $to = null
    ): Collection {
        $query = MeterReading::where('meter_id', $meter->id);

        if ($from) {
            $query->where('reading_date', '>=', $from);
        }

        if ($to) {
            $query->where('reading_date', '<=', $to);
        }

        return $query->orderBy('reading_date', 'asc')->get();
    }

    /**
     * Get audit trail for a reading
     *
     * @param MeterReading $reading Reading instance
     * @return Collection
     */
    public function getReadingAuditTrail(MeterReading $reading): Collection
    {
        return $this->auditService->getAuditTrail($reading);
    }

    public function getReadingsForExport($filters = []): Collection
    {
        $query = MeterReading::query();

        if (isset($filters['meter_id'])) {
            $query->where('meter_id', $filters['meter_id']);
        }

        if (isset($filters['from_date'])) {
            $query->where('reading_date', '>=', Carbon::parse($filters['from_date']));
        }

        if (isset($filters['to_date'])) {
            $query->where('reading_date', '<=', Carbon::parse($filters['to_date']));
        }

        return $query->orderBy('reading_date', 'asc')->get();
    }
}
