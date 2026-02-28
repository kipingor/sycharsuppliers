<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Billing Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for the Water Billing System's
    | billing operations including late fees, grace periods, and automation.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Late Fee Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how late fees are calculated and applied to overdue bills.
    |
    */
    'late_fees' => [
        // Enable or disable late fee application
        'enabled' => env('BILLING_LATE_FEES_ENABLED', true),

        // Late fee percentage (e.g., 5 = 5%)
        'percentage' => env('BILLING_LATE_FEE_PERCENTAGE', 5),

        // Minimum late fee amount
        'minimum_amount' => env('BILLING_LATE_FEE_MIN', 50),

        // Maximum late fee amount
        'maximum_amount' => env('BILLING_LATE_FEE_MAX', 5000),

        // Grace period in days before late fees apply
        'grace_period_days' => env('BILLING_GRACE_PERIOD_DAYS', 14),

        // Apply late fees automatically (via scheduled command)
        'auto_apply' => env('BILLING_LATE_FEES_AUTO', true),

        // Frequency of late fee application (once per bill or compound)
        'frequency' => env('BILLING_LATE_FEE_FREQUENCY', 'once'), // 'once' or 'monthly'
    ],

    /*
    |--------------------------------------------------------------------------
    | Bill Generation Settings
    |--------------------------------------------------------------------------
    |
    | Configure bill generation behavior and schedules.
    |
    */
    'generation' => [
        // Day of month to generate bills (1-28)
        'generation_day' => env('BILLING_GENERATION_DAY', 1),

        // Days until bill is due after generation
        'due_days' => env('BILLING_DUE_DAYS', 14),

        // Prevent duplicate bills for same account/period
        'prevent_duplicates' => true,

        // Auto-generate bills via scheduled command
        'auto_generate' => env('BILLING_AUTO_GENERATE', true),

        // Minimum consumption to generate bill (units)
        'minimum_consumption' => env('BILLING_MIN_CONSUMPTION', 0),

        // Include zero-consumption bills
        'include_zero_bills' => env('BILLING_INCLUDE_ZERO', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Billing Period Configuration
    |--------------------------------------------------------------------------
    */
    'period' => [
        // Billing period format
        'format' => 'Y-m',

        // Allow backdated bills
        'allow_backdate' => env('BILLING_ALLOW_BACKDATE', true),

        // Maximum months to backdate
        'max_backdate_months' => env('BILLING_MAX_BACKDATE', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Statement Configuration
    |--------------------------------------------------------------------------
    */
    'statements' => [
        // Auto-send statements after bill generation
        'auto_send' => env('BILLING_STATEMENTS_AUTO_SEND', true),

        // Statement delivery methods: 'email', 'sms', 'postal'
        'delivery_methods' => ['email'],

        // Include payment history in statements
        'include_payment_history' => true,

        // Number of previous bills to include
        'history_months' => 6,

        // PDF generation settings
        'pdf' => [
            'orientation' => 'portrait',
            'paper_size' => 'A4',
            'include_logo' => true,
            'include_qr_code' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        // Send notification when bill is generated
        'on_bill_generated' => env('BILLING_NOTIFY_GENERATED', true),

        // Send notification when bill is due
        'on_bill_due' => env('BILLING_NOTIFY_DUE', true),

        // Days before due date to send reminder
        'due_reminder_days' => env('BILLING_DUE_REMINDER_DAYS', 3),

        // Send notification when bill is overdue
        'on_bill_overdue' => env('BILLING_NOTIFY_OVERDUE', true),

        // Send notification when payment is received
        'on_payment_received' => env('BILLING_NOTIFY_PAYMENT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Account Status Rules
    |--------------------------------------------------------------------------
    */
    'account_status' => [
        // Auto-suspend account after X overdue bills
        'auto_suspend_after_bills' => env('BILLING_AUTO_SUSPEND_BILLS', 3),

        // Auto-suspend account after X days overdue
        'auto_suspend_after_days' => env('BILLING_AUTO_SUSPEND_DAYS', 60),

        // Prevent new bills for suspended accounts
        'skip_suspended' => true,

        // Require payment to reactivate suspended account
        'require_payment_to_reactivate' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Estimation Settings
    |--------------------------------------------------------------------------
    */
    'estimation' => [
        // Allow estimated readings
        'enabled' => env('BILLING_ESTIMATION_ENABLED', true),

        // Method: 'average', 'last_reading', 'seasonal'
        'method' => env('BILLING_ESTIMATION_METHOD', 'average'),

        // Number of months to average for estimation
        'average_months' => env('BILLING_ESTIMATION_MONTHS', 3),

        // Flag estimated bills clearly
        'flag_estimated' => true,

        // Require actual reading after X estimated readings
        'max_consecutive_estimates' => env('BILLING_MAX_ESTIMATES', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rounding and Precision
    |--------------------------------------------------------------------------
    */
    'precision' => [
        // Decimal places for amounts
        'amount_decimals' => 2,

        // Decimal places for units/consumption
        'unit_decimals' => 2,

        // Rounding method: 'round', 'ceil', 'floor'
        'rounding_method' => 'round',
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    */
    'queue' => [
        // Queue name for billing jobs
        'queue_name' => env('BILLING_QUEUE', 'billing'),

        // Connection for billing queue
        'connection' => env('BILLING_QUEUE_CONNECTION', 'redis'),

        // Retry attempts for failed jobs
        'retry_attempts' => 3,

        // Delay between retries (seconds)
        'retry_delay' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    */
    'cache' => [
        // Enable caching for balance calculations
        'enabled' => env('BILLING_CACHE_ENABLED', true),

        // Cache TTL in seconds (1 hour default)
        'ttl' => env('BILLING_CACHE_TTL', 3600),

        // Cache key prefix
        'prefix' => 'billing',
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit and Logging
    |--------------------------------------------------------------------------
    */
    'audit' => [
        // Enable comprehensive audit logging
        'enabled' => env('BILLING_AUDIT_ENABLED', true),

        // Log level for billing operations
        'log_level' => env('BILLING_LOG_LEVEL', 'info'),

        // Channels to log to
        'log_channels' => ['stack', 'billing'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Bulk Meter Configuration
    |--------------------------------------------------------------------------
    */
    'bulk_meters' => [
        // Enable bulk meter support
        'enabled' => env('BILLING_BULK_METERS_ENABLED', true),

        // Default allocation method: 'percentage', 'equal', 'manual'
        'allocation_method' => env('BILLING_BULK_ALLOCATION', 'percentage'),

        // Require 100% allocation for bulk meters
        'require_full_allocation' => true,

        // Generate individual bills for sub-meters
        'generate_submeter_bills' => true,
    ],

];