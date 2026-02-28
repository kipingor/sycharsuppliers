<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Reconciliation Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for payment reconciliation including
    | allocation strategies, overpayment handling, and reconciliation rules.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Allocation Strategy
    |--------------------------------------------------------------------------
    |
    | Determines how payments are allocated to outstanding bills.
    | Options: 'fifo', 'lifo', 'manual', 'smallest_first', 'oldest_due'
    |
    */
    'allocation_strategy' => env('RECONCILIATION_STRATEGY', 'fifo'),

    /*
    |--------------------------------------------------------------------------
    | Automatic Reconciliation
    |--------------------------------------------------------------------------
    |
    | Enable or disable automatic reconciliation when payments are created.
    |
    */
    'auto_reconcile' => env('RECONCILIATION_AUTO', true),

    /*
    |--------------------------------------------------------------------------
    | Overpayment Handling
    |--------------------------------------------------------------------------
    |
    | Determines what happens when payment exceeds outstanding balance.
    | Options: 'carry_forward', 'refund', 'manual'
    |
    */
    'overpayment_handling' => env('OVERPAYMENT_HANDLING', 'carry_forward'),

    /*
    |--------------------------------------------------------------------------
    | Underpayment Handling
    |--------------------------------------------------------------------------
    |
    | Configuration for partial payments that don't cover full bill amount.
    |
    */
    'underpayment' => [
        // Allow partial payments
        'allow_partial' => env('RECONCILIATION_ALLOW_PARTIAL', true),

        // Minimum partial payment percentage (e.g., 25 = 25% of bill)
        'minimum_percentage' => env('RECONCILIATION_MIN_PARTIAL', 25),

        // Minimum absolute amount for partial payment
        'minimum_amount' => env('RECONCILIATION_MIN_AMOUNT', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Grace Period
    |--------------------------------------------------------------------------
    |
    | Number of days after due date before reconciliation restrictions apply.
    |
    */
    'grace_period' => env('RECONCILIATION_GRACE_PERIOD', 5),

    /*
    |--------------------------------------------------------------------------
    | Minimum Allocation Amount
    |--------------------------------------------------------------------------
    |
    | Minimum amount to allocate (to avoid rounding issues).
    | Amounts smaller than this will be treated as zero.
    |
    */
    'minimum_allocation' => env('RECONCILIATION_MIN_ALLOCATION', 0.01),

    /*
    |--------------------------------------------------------------------------
    | Carry Forward Settings
    |--------------------------------------------------------------------------
    */
    'carry_forward' => [
        // Auto-create carry forward for overpayments
        'auto_create' => env('RECONCILIATION_AUTO_CARRY_FORWARD', true),

        // Expiration for carry forward credits (months, null = no expiration)
        'expiration_months' => env('RECONCILIATION_CREDIT_EXPIRY', null),

        // Auto-apply carry forward to new bills
        'auto_apply' => env('RECONCILIATION_AUTO_APPLY_CREDIT', true),

        // Minimum amount to create carry forward
        'minimum_amount' => env('RECONCILIATION_MIN_CARRY_FORWARD', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bill Selection Rules
    |--------------------------------------------------------------------------
    |
    | Rules for selecting which bills to allocate payments to.
    |
    */
    'bill_selection' => [
        // Prioritize overdue bills
        'prioritize_overdue' => true,

        // Maximum age of bills to reconcile (months, null = no limit)
        'max_bill_age_months' => env('RECONCILIATION_MAX_BILL_AGE', null),

        // Skip disputed bills during auto-reconciliation
        'skip_disputed' => true,

        // Skip voided bills
        'skip_voided' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Reconciliation Locking
    |--------------------------------------------------------------------------
    |
    | Prevent concurrent reconciliation of the same account.
    |
    */
    'locking' => [
        // Enable locking during reconciliation
        'enabled' => true,

        // Lock timeout in seconds
        'timeout' => 30,

        // Lock key prefix
        'key_prefix' => 'reconciliation_lock',
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit and Logging
    |--------------------------------------------------------------------------
    */
    'audit_enabled' => env('RECONCILIATION_AUDIT', true),

    /*
    |--------------------------------------------------------------------------
    | Reversal Policy
    |--------------------------------------------------------------------------
    |
    | Rules for reversing reconciliations.
    |
    */
    'reversal' => [
        // Allow reconciliation reversal
        'allow_reversal' => env('RECONCILIATION_ALLOW_REVERSAL', true),

        // Require reason for reversal
        'require_reason' => true,

        // Who can reverse: 'anyone', 'admin_only', 'same_user'
        'who_can_reverse' => env('RECONCILIATION_WHO_CAN_REVERSE', 'admin_only'),

        // Time limit for reversal (hours, null = no limit)
        'time_limit_hours' => env('RECONCILIATION_REVERSAL_TIME_LIMIT', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */
    'validation' => [
        // Verify payment amount matches allocation total
        'verify_amount_match' => true,

        // Tolerance for amount matching (for rounding differences)
        'amount_tolerance' => 0.02,

        // Prevent allocation to paid bills
        'prevent_overpayment' => true,

        // Prevent negative allocations
        'prevent_negative' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    */
    'performance' => [
        // Batch size for bulk reconciliation
        'batch_size' => env('RECONCILIATION_BATCH_SIZE', 100),

        // Cache reconciliation results
        'cache_enabled' => env('RECONCILIATION_CACHE', true),

        // Cache TTL in seconds
        'cache_ttl' => 300, // 5 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        // Notify on successful reconciliation
        'on_success' => env('RECONCILIATION_NOTIFY_SUCCESS', true),

        // Notify on failed reconciliation
        'on_failure' => env('RECONCILIATION_NOTIFY_FAILURE', true),

        // Notify on partial reconciliation
        'on_partial' => env('RECONCILIATION_NOTIFY_PARTIAL', true),

        // Notify on carry forward creation
        'on_carry_forward' => env('RECONCILIATION_NOTIFY_CARRY_FORWARD', true),

        // Notification channels
        'channels' => ['mail', 'database'],
    ],

    /*
    |--------------------------------------------------------------------------
    | API Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limiting' => [
        // Enable rate limiting for reconciliation API
        'enabled' => env('RECONCILIATION_RATE_LIMIT', true),

        // Maximum reconciliations per minute
        'max_per_minute' => env('RECONCILIATION_MAX_PER_MINUTE', 60),

        // Maximum reconciliations per hour
        'max_per_hour' => env('RECONCILIATION_MAX_PER_HOUR', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    */
    'queue' => [
        // Queue reconciliation jobs
        'enabled' => env('RECONCILIATION_QUEUE_ENABLED', true),

        // Queue name
        'queue_name' => env('RECONCILIATION_QUEUE', 'reconciliation'),

        // Connection
        'connection' => env('RECONCILIATION_QUEUE_CONNECTION', 'redis'),

        // Job timeout in seconds
        'timeout' => 300,

        // Retry attempts
        'retry_attempts' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Reporting
    |--------------------------------------------------------------------------
    */
    'reporting' => [
        // Generate reconciliation reports
        'enabled' => true,

        // Report retention days
        'retention_days' => 365,

        // Report formats
        'formats' => ['pdf', 'csv', 'json'],
    ],

];