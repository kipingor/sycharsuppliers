<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Account Statement</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            padding: 2rem;
        }

        h2 {
            margin-bottom: 0.5rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        th,
        td {
            border: 1px solid #999;
            padding: 6px 8px;
        }

        th {
            background: #f2f2f2;
        }

        .right {
            text-align: right;
        }

        .muted {
            color: #666;
            font-size: 9px;
        }

    </style>
</head>
<body>

    {{-- Header --}}
    <table style="border: none">
        <tr style="border: none">
            <td style="border: none; width: 30%">
                <img src="{{ public_path('logo.png') }}" style="width: 100%" alt="Logo">
            </td>
            <td style="border: none; width: 70%">
                <h2>Water Billing Statement</h2>
                <p><strong>Account:</strong> {{ $statement['account']['name'] }}</p>
                <p><strong>Email:</strong> {{ $statement['account']['email'] }}</p>
                <p>
                    <strong>Period:</strong>
                    {{ $statement['period']['from']->format('Y-m-d') }}
                    —
                    {{ $statement['period']['to']->format('Y-m-d') }}
                </p>
            </td>
        </tr>
    </table>

    {{-- Ledger --}}
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Reference</th>
                <th>Description</th>
                <th class="right">Debit (KES)</th>
                <th class="right">Credit (KES)</th>
                <th class="right">Balance (KES)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($statement['lines'] as $line)
            <tr>
                <td>{{ $line['date']->format('Y-m-d') }}</td>
                <td>{{ $line['reference'] }}</td>
                <td>{{ $line['description'] }}</td>
                <td class="right">
                    {{ $line['debit'] !== null ? number_format($line['debit'], 2) : '' }}
                </td>
                <td class="right">
                    {{ $line['credit'] !== null ? number_format($line['credit'], 2) : '' }}
                </td>
                <td class="right">
                    {{ number_format($line['balance'], 2) }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Totals --}}
    <table style="margin-top: 1.5rem">
        <tr>
            <td><strong>Total Billed</strong></td>
            <td class="right">{{ number_format($statement['totals']['total_billed'], 2) }}</td>
        </tr>
        <tr>
            <td><strong>Total Paid</strong></td>
            <td class="right">{{ number_format($statement['totals']['total_paid'], 2) }}</td>
        </tr>
        <tr>
            <td><strong>Closing Balance</strong></td>
            <td class="right"><strong>{{ number_format($statement['totals']['closing_balance'], 2) }}</strong></td>
        </tr>
    </table>

    {{-- Footer --}}
    <p class="muted" style="margin-top: 2rem">
        Payments are due within 15 days. Late balances may attract interest as permitted by law.
    </p>

    <p class="muted" style="text-align: center; margin-top: 1rem">
        ALL CHEQUES PAYABLE TO <strong>SYCHAR SUPPLIERS</strong>
    </p>

</body>
</html>
