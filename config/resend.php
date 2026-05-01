<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Resend API Key
    |--------------------------------------------------------------------------
    |
    | Your Resend API key. Used by the resend/resend-laravel package to
    | authenticate outbound email sends and inbound email retrieval.
    |
    | Find this in: Resend Dashboard → API Keys
    |
    */

    'api_key' => env('RESEND_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Settings used by ResendWebhookController to verify incoming webhook
    | requests from Resend.
    |
    | secret    — The signing secret shown in Resend Dashboard → Webhooks →
    |             (your endpoint) → Signing Secret.
    |             Set RESEND_WEBHOOK_SECRET in your .env file.
    |
    | tolerance — How many seconds of clock skew are tolerated when verifying
    |             the svix-timestamp header. Defaults to 5 minutes (300s).
    |
    | Register your webhook URL in the Resend dashboard:
    |   https://yourdomain.com/api/webhooks/resend
    |
    | Enable these events:
    |   email.received, email.sent, email.delivered, email.delivery_delayed,
    |   email.opened, email.clicked, email.bounced, email.failed, email.complained
    |
    */

    'webhook' => [
        'secret'    => env('RESEND_WEBHOOK_SECRET'),
        'tolerance' => env('RESEND_WEBHOOK_TOLERANCE', 300),
    ],

];