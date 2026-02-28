<x-mail::message>
# Account Statement

Dear {{ $account['name'] }},

Please find attached your account statement for the period **{{ $period['start'] }}** to **{{ $period['end'] }}**.

<x-mail::panel>
| | |
|---|---|
| **Account Number** | {{ $account['number'] }} |
| **Statement Period** | {{ $period['start'] }} to {{ $period['end'] }} |
| **Opening Balance** | KES {{ number_format($opening_balance, 2) }} |
| **Total Charged** | KES {{ number_format($total_billed, 2) }} |
| **Total Paid** | KES {{ number_format($total_paid, 2) }} |
@if($total_credited > 0)
| **Credits Applied** | KES {{ number_format($total_credited, 2) }} |
@endif
| **Closing Balance** | **KES {{ number_format($closing_balance, 2) }}** |
</x-mail::panel>

@if($closing_balance <= 0)
🎉 **Your account is fully settled.** Thank you for keeping your payments up to date!
@else
Your full statement with detailed transaction history is attached as a PDF.
Please make a payment of **KES {{ number_format($closing_balance, 2) }}** at your earliest convenience.

If you have already made a payment that is not yet reflected, kindly disregard this notice.
@endif

If you have any questions about this statement, please contact us at
@if(!empty($company['phone'])){{ $company['phone'] }}@endif
@if(!empty($company['email'])) or {{ $company['email'] }}@endif.

Thank you for being a valued customer!

Warm regards,
**{{ $company['name'] }}**
</x-mail::message>