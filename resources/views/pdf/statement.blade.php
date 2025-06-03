<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Water Billing Statement</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 2rem;
        }
        h2, h4 {
            margin: 0;
            padding: 0.5rem 0;
        }
        .header {
            margin-bottom: 1rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        th, td {
            border: 1px solid #999;
            padding: 6px 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .right { text-align: right; }
        .footer {
            margin-top: 2rem;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <div class="header">
        <h2>Water Billing Statement</h2>
        <p><strong>Resident:</strong> {{ $resident->name }}</p>
        <p><strong>Email:</strong> {{ $resident->email }}</p>
        <p><strong>Statement Period:</strong> {{ $startDate }} to {{ $endDate }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Description</th>
                <th class="right">Bill (KES)</th>
                <th class="right">Payment (KES)</th>
                <th class="right">Running Balance (KES)</th>
            </tr>
        </thead>
        <tbody>
            @php $runningBalance = $carryForward; @endphp
            <tr>
                <td colspan="5"><strong>Carry Forward from Previous Years</strong></td>
                <td class="right">{{ number_format($carryForward, 2) }}</td>
            </tr>

            @foreach ($transactions->sortBy('created_at') as $transaction)
                @php
                    $isBill = isset($transaction->amount_due);
                    $date = $transaction->created_at->format('Y-m-d');
                    $description = $isBill 
                        ? 'Bill - ' . ($transaction->details->description ?? 'Water usage') 
                        : 'Payment - ' . ($transaction->method ?? 'Unknown');
                    $bill = $isBill ? $transaction->amount_due : 0;
                    $payment = $isBill ? 0 : $transaction->amount;
                    $runningBalance += $bill - $payment;
                @endphp
                <tr>
                    <td>{{ $date }}</td>
                    <td>{{ $isBill ? 'Bill' : 'Payment' }}</td>
                    <td>{{ $description }}</td>
                    <td class="right">{{ $bill ? number_format($bill, 2) : '' }}</td>
                    <td class="right">{{ $payment ? number_format($payment, 2) : '' }}</td>
                    <td class="right">{{ number_format($runningBalance, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Total Due as of {{ now()->format('Y-m-d') }}: KES {{ number_format($runningBalance, 2) }}</p>
    </div>

</body>
</html>
