<?php

namespace App\Services\Audit;

use App\Models\Billing;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Models\Audit;
use Illuminate\Support\Facades\Auth;

/**
 * Centralized Audit Service
 * 
 * Provides unified interface for logging and retrieving audit trails
 * across the billing domain.
 * 
 * @package App\Services\Audit
 */
class AuditService
{
    /**
     * Log a billing-specific action
     * 
     * @param string $action Action performed (e.g., 'generated', 'rebilled', 'voided')
     * @param Billing|null $billing Billing instance (nullable for account-level actions)
     * @param array $context Additional context data
     * @return Audit|null
     */
    public function logBillingAction(
        string $action,
        ?Billing $billing,
        array $context = []
    ): ?Audit {
        if (!config('audit.enabled')) {
            return null;
        }

        return $this->createAuditLog(
            auditable: $billing,
            event: $action,
            context: array_merge([
                'billing_period' => $billing?->billing_period,
                'account_id' => $billing?->account_id ?? $context['account_id'] ?? null,
                'action_type' => 'billing',
            ], $context)
        );
    }

    /**
     * Log a payment-specific action
     * 
     * @param string $action Action performed (e.g., 'reconciled', 'reversed', 'allocated')
     * @param Payment $payment Payment instance
     * @param array $context Additional context data
     * @return Audit|null
     */
    public function logPaymentAction(
        string $action,
        Payment $payment,
        array $context = []
    ): ?Audit {
        if (!config('audit.enabled')) {
            return null;
        }

        return $this->createAuditLog(
            auditable: $payment,
            event: $action,
            context: array_merge([
                'payment_amount' => $payment->amount,
                'payment_method' => $payment->method,
                'account_id' => $payment->account_id,
                'action_type' => 'payment',
            ], $context)
        );
    }

    /**
     * Log a meter-specific action
     * 
     * @param string $action Action performed (e.g., 'activated', 'deactivated', 'reading_added')
     * @param Meter $meter Meter instance
     * @param array $context Additional context data
     * @return Audit|null
     */
    public function logMeterAction(
        string $action,
        Meter $meter,
        array $context = []
    ): ?Audit {
        if (!config('audit.enabled')) {
            return null;
        }

        return $this->createAuditLog(
            auditable: $meter,
            event: $action,
            context: array_merge([
                'meter_number' => $meter->meter_number,
                'meter_type' => $meter->type,
                'account_id' => $meter->account_id,
                'action_type' => 'meter',
            ], $context)
        );
    }

    /**
     * Log a meter reading-specific action
     * 
     * @param string $action Action performed (e.g., 'created', 'updated', 'deleted', 'validated')
     * @param MeterReading $reading Meter reading instance
     * @param array $context Additional context data
     * @return Audit|null
     */
    public function logMeterReadingAction(
        string $action,
        MeterReading $reading,
        array $context = []
    ): ?Audit {
        if (!config('audit.enabled')) {
            return null;
        }

        return $this->createAuditLog(
            auditable: $reading,
            event: $action,
            context: array_merge([
                'meter_id' => $reading->meter_id,
                'meter_number' => $reading->meter?->meter_number,
                'reading_value' => $reading->reading,
                'reading_date' => $reading->reading_date?->toDateString(),
                'reading_type' => $reading->reading_type,
                'consumption' => $reading->consumption,
                'account_id' => $reading->meter?->account_id,
                'action_type' => 'meter_reading',
            ], $context)
        );
    }

    /**
     * Log a configuration change
     * 
     * @param string $config Configuration key that changed
     * @param mixed $oldValue Old value
     * @param mixed $newValue New value
     * @return Audit|null
     */
    public function logConfigChange(
        string $config,
        mixed $oldValue,
        mixed $newValue
    ): ?Audit {
        if (!config('audit.enabled')) {
            return null;
        }

        $id = DB::table('audits')->insertGetId([
            'user_type' => Auth::check() ? User::class : null,
            'user_id' => Auth::id(),
            'event' => 'config_changed',
            'auditable_type' => 'App\Config',
            'auditable_id' => 0,
            'old_values' => json_encode([$config => $oldValue]),
            'new_values' => json_encode([$config => $newValue]),
            'url' => request()->fullUrl(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'tags' => 'configuration',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Audit::find($id);
    }

    /**
     * Retrieve audit trail for a specific model
     * 
     * @param Model $model Model to get audit trail for
     * @param Carbon|null $from Start date (optional)
     * @param Carbon|null $to End date (optional)
     * @return Collection Collection of Audit records
     */
    public function getAuditTrail(
        Model $model,
        ?Carbon $from = null,
        ?Carbon $to = null
    ): Collection {
        $query = Audit::where('auditable_type', get_class($model))
            ->where('auditable_id', $model->id)
            ->orderBy('created_at', 'desc');

        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return $query->get();
    }

    /**
     * Get audit trail for a meter (including all readings)
     * 
     * @param Meter $meter Meter instance
     * @param Carbon|null $from Start date (optional)
     * @param Carbon|null $to End date (optional)
     * @return Collection
     */
    public function getMeterAuditTrail(
        Meter $meter,
        ?Carbon $from = null,
        ?Carbon $to = null
    ): Collection {
        $query = Audit::where(function ($q) use ($meter) {
            // Get audits for the meter itself
            $q->where(function ($meterQuery) use ($meter) {
                $meterQuery->where('auditable_type', Meter::class)
                    ->where('auditable_id', $meter->id);
            })
            // And audits for all readings for this meter
            ->orWhere(function ($readingQuery) use ($meter) {
                $readingQuery->where('auditable_type', MeterReading::class)
                    ->whereIn('auditable_id', function ($subQuery) use ($meter) {
                        $subQuery->select('id')
                            ->from('meter_readings')
                            ->where('meter_id', $meter->id);
                    });
            });
        })
        ->orderBy('created_at', 'desc');

        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return $query->get();
    }

    /**
     * Get audit trail for an account (all related entities)
     * 
     * @param int $accountId Account ID
     * @param Carbon|null $from Start date (optional)
     * @param Carbon|null $to End date (optional)
     * @return Collection
     */
    public function getAccountAuditTrail(
        int $accountId,
        ?Carbon $from = null,
        ?Carbon $to = null
    ): Collection {
        $query = Audit::where(function ($q) use ($accountId) {
            // Get audits for billings, payments, meters, and meter readings for this account
            $q->where(function ($billingQuery) use ($accountId) {
                $billingQuery->where('auditable_type', Billing::class)
                    ->whereIn('auditable_id', function ($subQuery) use ($accountId) {
                        $subQuery->select('id')
                            ->from('billings')
                            ->where('account_id', $accountId);
                    });
            })
            ->orWhere(function ($paymentQuery) use ($accountId) {
                $paymentQuery->where('auditable_type', Payment::class)
                    ->whereIn('auditable_id', function ($subQuery) use ($accountId) {
                        $subQuery->select('id')
                            ->from('payments')
                            ->where('account_id', $accountId);
                    });
            })
            ->orWhere(function ($meterQuery) use ($accountId) {
                $meterQuery->where('auditable_type', Meter::class)
                    ->whereIn('auditable_id', function ($subQuery) use ($accountId) {
                        $subQuery->select('id')
                            ->from('meters')
                            ->where('account_id', $accountId);
                    });
            })
            ->orWhere(function ($readingQuery) use ($accountId) {
                $readingQuery->where('auditable_type', MeterReading::class)
                    ->whereIn('auditable_id', function ($subQuery) use ($accountId) {
                        $subQuery->select('meter_readings.id')
                            ->from('meter_readings')
                            ->join('meters', 'meter_readings.meter_id', '=', 'meters.id')
                            ->where('meters.account_id', $accountId);
                    });
            });
        })
        ->orderBy('created_at', 'desc');

        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return $query->get();
    }

    /**
     * Export audit log to CSV
     * 
     * @param array $filters Filters to apply
     * @return string Path to generated CSV file
     */
    public function exportAuditLog(array $filters = []): string
    {
        $query = Audit::query();

        // Apply filters
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['event'])) {
            $query->where('event', $filters['event']);
        }

        if (!empty($filters['auditable_type'])) {
            $query->where('auditable_type', $filters['auditable_type']);
        }

        if (!empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        if (!empty($filters['tags'])) {
            $query->where('tags', 'like', '%' . $filters['tags'] . '%');
        }

        $audits = $query->orderBy('created_at', 'desc')->get();

        // Generate CSV
        $filename = 'audit_log_' . now()->format('Y-m-d_His') . '.csv';
        $filepath = storage_path('app/exports/' . $filename);

        // Ensure directory exists
        if (!file_exists(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }

        $file = fopen($filepath, 'w');

        // Write headers
        fputcsv($file, [
            'ID',
            'Date/Time',
            'User ID',
            'User',
            'Event',
            'Entity Type',
            'Entity ID',
            'Old Values',
            'New Values',
            'URL',
            'IP Address',
            'Tags',
        ]);

        // Write data
        foreach ($audits as $audit) {
            fputcsv($file, [
                $audit->id,
                $audit->created_at->toDateTimeString(),
                $audit->user_id,
                $audit->user?->name ?? 'N/A',
                $audit->event,
                class_basename($audit->auditable_type),
                $audit->auditable_id,
                json_encode($audit->old_values),
                json_encode($audit->new_values),
                $audit->url,
                $audit->ip_address,
                $audit->tags,
            ]);
        }

        fclose($file);

        return $filepath;
    }

    /**
     * Get user activity summary
     * 
     * @param User $user User to get activity for
     * @param Carbon|null $from Start date (optional)
     * @param Carbon|null $to End date (optional)
     * @return array Activity summary
     */
    public function getUserActivity(
        User $user,
        ?Carbon $from = null,
        ?Carbon $to = null
    ): array {
        $query = Audit::where('user_id', $user->id);

        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        $audits = $query->get();

        $summary = [
            'total_actions' => $audits->count(),
            'actions_by_type' => $audits->groupBy('event')->map(function ($group) {
                return $group->count();
            })->toArray(),
            'actions_by_entity' => $audits->groupBy('auditable_type')->map(function ($group) {
                return $group->count();
            })->toArray(),
            'most_active_day' => $audits->groupBy(function ($audit) {
                return $audit->created_at->format('Y-m-d');
            })->sortByDesc(function ($group) {
                return $group->count();
            })->keys()->first(),
            'latest_activity' => $audits->first()?->created_at,
            'period' => [
                'from' => $from?->toDateString() ?? $audits->last()?->created_at->toDateString(),
                'to' => $to?->toDateString() ?? $audits->first()?->created_at->toDateString(),
            ],
        ];

        return $summary;
    }

    /**
     * Get audit statistics for a date range
     * 
     * @param Carbon $from Start date
     * @param Carbon $to End date
     * @return array Statistics
     */
    public function getAuditStatistics(Carbon $from, Carbon $to): array
    {
        $audits = Audit::whereBetween('created_at', [$from, $to])->get();

        return [
            'total_audits' => $audits->count(),
            'unique_users' => $audits->pluck('user_id')->unique()->count(),
            'events_breakdown' => $audits->groupBy('event')->map->count()->toArray(),
            'entities_breakdown' => $audits->groupBy('auditable_type')->map->count()->toArray(),
            'daily_activity' => $audits->groupBy(function ($audit) {
                return $audit->created_at->format('Y-m-d');
            })->map->count()->toArray(),
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        ];
    }

    /**
     * Get meter reading statistics for a date range
     * 
     * @param Carbon $from Start date
     * @param Carbon $to End date
     * @param int|null $meterId Optional meter ID to filter by
     * @return array Statistics
     */
    public function getMeterReadingStatistics(
        Carbon $from,
        Carbon $to,
        ?int $meterId = null
    ): array {
        $query = Audit::where('auditable_type', MeterReading::class)
            ->whereBetween('created_at', [$from, $to]);

        if ($meterId) {
            $query->whereIn('auditable_id', function ($subQuery) use ($meterId) {
                $subQuery->select('id')
                    ->from('meter_readings')
                    ->where('meter_id', $meterId);
            });
        }

        $audits = $query->get();

        return [
            'total_reading_actions' => $audits->count(),
            'actions_by_event' => $audits->groupBy('event')->map->count()->toArray(),
            'unique_meters_affected' => $audits->pluck('new_values')
                ->map(function ($values) {
                    $decoded = json_decode($values, true);
                    return $decoded['meter_id'] ?? null;
                })
                ->filter()
                ->unique()
                ->count(),
            'readings_by_type' => $audits->pluck('new_values')
                ->map(function ($values) {
                    $decoded = json_decode($values, true);
                    return $decoded['reading_type'] ?? null;
                })
                ->filter()
                ->groupBy(function ($type) {
                    return $type;
                })
                ->map->count()
                ->toArray(),
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        ];
    }

    /**
     * Create a custom audit log entry
     * 
     * @param Model|null $auditable Auditable model (nullable for system-level logs)
     * @param string $event Event name
     * @param array $context Additional context
     * @return Audit|null
     */
    protected function createAuditLog(
        ?Model $auditable,
        string $event,
        array $context = []
    ): ?Audit {
        $auditData = [
            'user_type' => Auth::check() ? User::class : null,
            'user_id' => Auth::id(),
            'event' => $event,
            'auditable_type' => $auditable ? get_class($auditable) : 'System',
            'auditable_id' => $auditable?->id ?? 0,
            'old_values' => json_encode([]),
            'new_values' => json_encode($context),
            'url' => request()->fullUrl(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'tags' => $context['action_type'] ?? 'general',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $id = DB::table('audits')->insertGetId($auditData);

        return Audit::find($id);
    }

    /**
     * Bulk log actions (for batch operations)
     * 
     * @param string $action Action performed
     * @param array $entities Array of models
     * @param array $context Shared context
     * @return int Count of audit logs created
     */
    public function bulkLogAction(
        string $action,
        array $entities,
        array $context = []
    ): int {
        if (!config('audit.enabled')) {
            return 0;
        }

        $count = 0;
        foreach ($entities as $entity) {
            if ($entity instanceof Billing) {
                $this->logBillingAction($action, $entity, $context);
            } elseif ($entity instanceof Payment) {
                $this->logPaymentAction($action, $entity, $context);
            } elseif ($entity instanceof Meter) {
                $this->logMeterAction($action, $entity, $context);
            } elseif ($entity instanceof MeterReading) {
                $this->logMeterReadingAction($action, $entity, $context);
            }
            $count++;
        }

        return $count;
    }

    /**
     * Log bulk meter reading creation
     * 
     * @param array $readings Array of MeterReading instances
     * @param array $context Shared context (e.g., upload_id, file_name)
     * @return int Count of logs created
     */
    public function logBulkMeterReadingCreation(
        array $readings,
        array $context = []
    ): int {
        if (!config('audit.enabled')) {
            return 0;
        }

        // Create a summary audit entry for the bulk operation
        $summaryContext = array_merge([
            'action_type' => 'meter_reading_bulk',
            'total_readings' => count($readings),
            'meter_ids' => array_unique(array_map(fn($r) => $r->meter_id, $readings)),
            'reading_dates' => array_unique(array_map(fn($r) => $r->reading_date->toDateString(), $readings)),
        ], $context);

        $this->createAuditLog(
            auditable: null,
            event: 'bulk_reading_created',
            context: $summaryContext
        );

        // Log individual readings
        return $this->bulkLogAction('created', $readings, $context);
    }

    /**
     * Get validation failure audit trail
     * 
     * Returns audit logs for failed validations (monotonic, duplicate, etc.)
     * 
     * @param Carbon|null $from Start date
     * @param Carbon|null $to End date
     * @return Collection
     */
    public function getValidationFailures(
        ?Carbon $from = null,
        ?Carbon $to = null
    ): Collection {
        $query = Audit::where('auditable_type', MeterReading::class)
            ->whereIn('event', [
                'validation_failed',
                'monotonic_violation',
                'duplicate_prevented',
                'update_prevented',
                'delete_prevented'
            ])
            ->orderBy('created_at', 'desc');

        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return $query->get();
    }
}