<?php

namespace App\Jobs;

use App\Models\MeterReading;
use App\Services\Billing\BulkMeterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Process Meter Reading Job
 * 
 * Asynchronously processes meter readings including:
 * - Validation
 * - Bulk meter distribution
 * - Anomaly detection
 * - Related bill generation triggers
 * 
 * @package App\Jobs
 */
class ProcessMeterReadingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $backoff = 300;

    /**
     * The maximum number of seconds the job can run.
     */
    public $timeout = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public MeterReading $meterReading,
        public bool $autoDistribute = true
    ) {}

    /**
     * Execute the job.
     */
    public function handle(BulkMeterService $bulkMeterService): void
    {
        Log::info('Processing meter reading', [
            'reading_id' => $this->meterReading->id,
            'meter_id' => $this->meterReading->meter_id,
            'auto_distribute' => $this->autoDistribute,
        ]);

        try {
            // Validate reading
            $this->validateReading();

            // Check for anomalies
            $anomalies = $this->checkForAnomalies();
            if (!empty($anomalies)) {
                $this->handleAnomalies($anomalies);
            }

            // Process bulk meter distribution if applicable
            if ($this->shouldDistribute()) {
                $this->distributeBulkReading($bulkMeterService);
            }

            // Update processing status
            $this->meterReading->update([
                'processed_at' => now(),
                'processing_status' => 'completed',
            ]);

            Log::info('Meter reading processed successfully', [
                'reading_id' => $this->meterReading->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to process meter reading', [
                'reading_id' => $this->meterReading->id,
                'error' => $e->getMessage(),
            ]);

            $this->meterReading->update([
                'processing_status' => 'failed',
                'processing_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Validate the reading
     */
    protected function validateReading(): void
    {
        $meter = $this->meterReading->meter;

        // Check if meter is active
        if (!$meter->isActive()) {
            throw new \RuntimeException('Cannot process reading for inactive meter');
        }

        // Check if reading is reasonable
        $previousReading = $this->meterReading->previous_reading_value;
        if ($previousReading) {
            $consumption = $this->meterReading->reading - $previousReading->reading;
            
            // Check for negative consumption (excluding meter resets)
            if ($consumption < 0 && abs($consumption) < 1000) {
                throw new \RuntimeException('Reading shows negative consumption without meter reset');
            }
        }
    }

    /**
     * Check for anomalies in the reading
     */
    protected function checkForAnomalies(): array
    {
        $anomalies = [];
        $meter = $this->meterReading->meter;
        $previousReading = $this->meterReading->previous_reading_value;

        if (!$previousReading) {
            return $anomalies;
        }

        $consumption = $this->meterReading->getConsumption();
        $avgConsumption = $meter->getAverageMonthlyConsumption();

        // Check for unusually high consumption
        if ($avgConsumption > 0 && $consumption > ($avgConsumption * 5)) {
            $anomalies[] = [
                'type' => 'high_consumption',
                'message' => "Consumption ({$consumption} units) is 5x higher than average ({$avgConsumption} units)",
                'severity' => 'warning',
            ];
        }

        // Check for unusually low consumption
        if ($avgConsumption > 0 && $consumption < ($avgConsumption * 0.1)) {
            $anomalies[] = [
                'type' => 'low_consumption',
                'message' => "Consumption ({$consumption} units) is significantly lower than average ({$avgConsumption} units)",
                'severity' => 'info',
            ];
        }

        // Check for zero consumption
        if ($consumption == 0) {
            $anomalies[] = [
                'type' => 'zero_consumption',
                'message' => 'Reading shows zero consumption',
                'severity' => 'warning',
            ];
        }

        return $anomalies;
    }

    /**
     * Handle detected anomalies
     */
    protected function handleAnomalies(array $anomalies): void
    {
        Log::warning('Anomalies detected in meter reading', [
            'reading_id' => $this->meterReading->id,
            'meter_id' => $this->meterReading->meter_id,
            'anomalies' => $anomalies,
        ]);

        // Store anomalies
        $this->meterReading->update([
            'has_anomalies' => true,
            'anomalies' => json_encode($anomalies),
        ]);

        // Send notification for high-severity anomalies
        $highSeverity = collect($anomalies)->where('severity', 'warning')->isNotEmpty();
        if ($highSeverity) {
            // Notify relevant parties
            // Notification::route('mail', config('billing.admin_email'))
            //     ->notify(new ReadingAnomalyDetected($this->meterReading, $anomalies));
        }
    }

    /**
     * Check if reading should be distributed
     */
    protected function shouldDistribute(): bool
    {
        if (!$this->autoDistribute) {
            return false;
        }

        $meter = $this->meterReading->meter;

        // Only distribute bulk meter readings
        if (!$meter->isBulkMeter()) {
            return false;
        }

        // Don't distribute if already distributed
        if ($this->meterReading->is_distributed) {
            return false;
        }

        // Don't distribute estimated readings
        if ($this->meterReading->reading_type === 'estimated') {
            return false;
        }

        // Check if meter has sub-meters
        if (!$meter->hasSubMeters()) {
            Log::warning('Bulk meter has no sub-meters for distribution', [
                'meter_id' => $meter->id,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Distribute bulk meter reading
     */
    protected function distributeBulkReading(BulkMeterService $bulkMeterService): void
    {
        try {
            $distributedReadings = $bulkMeterService->distributeBulkReading($this->meterReading);

            Log::info('Bulk reading distributed', [
                'reading_id' => $this->meterReading->id,
                'sub_readings_created' => count($distributedReadings),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to distribute bulk reading', [
                'reading_id' => $this->meterReading->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'meter-reading',
            'meter:' . $this->meterReading->meter_id,
            'reading:' . $this->meterReading->id,
        ];
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Meter reading processing job failed permanently', [
            'reading_id' => $this->meterReading->id,
            'error' => $exception->getMessage(),
        ]);

        $this->meterReading->update([
            'processing_status' => 'failed',
            'processing_error' => $exception->getMessage(),
        ]);
    }
}
