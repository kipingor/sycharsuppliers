<x-mail::message>
# Your Latest Billing Statement

Dear {{ $account->name }},

We hope this message finds you well! Here is your most recent billing update
for **{{ $billing_period }}**.

<x-mail::panel>
@foreach ($details as $detail)
**Meter: {{ $detail['meter_number'] }}**@if($detail['meter_name']) — {{ $detail['meter_name'] }}@endif

| | |
|---|---|
| Previous Reading | {{ $detail['previous_reading'] }} m³ |
| Current Reading | {{ $detail['current_reading'] }} m³ |
| Units Used | {{ $detail['units'] }} m³ |
| Rate | KES {{ number_format($detail['rate'], 2) }} |
| Amount | KES {{ number_format($detail['amount'], 2) }} |

@endforeach
</x-mail::panel>

<x-mail::panel>
| | |
|---|---|
| **Total Billed** | KES {{ number_format($total_billed, 2) }} |
| **Total Paid** | KES {{ number_format($total_paid, 2) }} |
| **Due Date** | {{ $due_date }} |
| **Outstanding Balance** | **KES {{ number_format($balance_due, 2) }}** |
</x-mail::panel>

@if ($balance_due <= 0)
🎉 **Your account is fully up to date!** There is no outstanding balance.
Thank you for staying current with your payments — we truly appreciate it!
@else
@if ($is_overdue)
⚠️ **This bill is overdue.** Please make a payment as soon as possible to
avoid service interruption.
@else
Please review your balance above and make a payment before **{{ $due_date }}**.
If you have already made a payment that is not reflected yet, kindly ignore this message.
@endif
@endif

Your full statement is attached to this email as a PDF.

Thank you for being a valued {{ config('app.name') }} customer!
We are always here to serve you with excellence.

Warm regards,
**{{ config('app.name') }}**
</x-mail::message>