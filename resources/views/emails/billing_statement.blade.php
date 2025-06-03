<x-mail::message>
# Your Latest Billing Statement

Dear {{ $resident->name }},

We hope this message finds you well! Here's your most recent billing update for your water meter **{{ $meter->meter_number }}** located at **{{ $meter->location }}**.

<x-mail::panel>
- **Latest Billing Amount:** KES {{ number_format($billing->amount_due, 2) }}  
- **Last Meter Reading:** {{ $details->previous_reading_value ?? 'N/A' }}  
- **Current Reading:** {{ $details->current_reading_value ?? 'N/A' }}  
- **Units Used Since Last Reading:** {{ $details->units_used ?? 'N/A' }}  
- **Total Amount Billed to Date:** KES {{ number_format($total_billed, 2) }}  
- **Total Payments Made:** KES {{ number_format($total_paid, 2) }}  
- **🔴 Outstanding Balance:** <strong>KES {{ number_format($balance_due, 2) }}</strong>

Please find attached your account statement from the beginning of the year.
</x-mail::panel>

@if ($balance_due <= 0)
🎉 Fantastic news! Your account is up to date and there’s no outstanding balance. Thank you for staying current with your payments — we truly appreciate it!
@else
Please review your balance above and make a payment at your earliest convenience. If you have already made a payment that’s not reflected yet, kindly ignore this message.
@endif

<x-mail::button :url="route('billing.statement', ['meter' => $meter->id])">
View Full Statement
</x-mail::button>

Thank you for being a valued Sychar Suppliers customer!  
We’re always here to serve you with excellence.

Warm regards,  
**{{ config('app.name') }}**
</x-mail::message>
