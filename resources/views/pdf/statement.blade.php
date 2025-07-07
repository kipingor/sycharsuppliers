<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Water Billing Statement</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
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
        <div>
            <img src="{{ public_path('logo.png') }}" style="width: 20%; height: auto;" alt="Sychar Suppliers Logo" />
        </div>
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
        <hr style="border-top: 1px solid #bbb; margin-top: 2rem">
        <p style="margin-bottom: 1rem; margin-top: 2rem; font-size: 8px; color: gray">Services will be billed in accordance with the Service Description. You must pay all undisputed bills in full within 15 days of the billing date, unless otherwise specified under the Terms and Conditions. All bills shall be paid in the currency of the bill</p>
        <p style="font-size: 8px; color: gray">Sychar Suppliers retains the right to decline to extend credit and to require that the bill amount be paid immediately or water supply will be discontinued. Sychar Suppliers reserves the right to charge interest of 1.5% per month or the maximum allowable by applicable law, whichever is less, for any undisputed past due bills. You are responsible for all costs of collection, including reasonable attorneys' fees, for any payment default on undisputed bills. In addition, Sychar Suppliers may disconnect supply of water if payment is not received in a timely manner.</p>
        <hr style="border-top: 1px solid #bbb; margin-top: 2rem">
        <p style="margin-top: 1rem; text-transform: uppercase; text-align: center; font-size: 10px">ALL CHEQUES PAYABLE TO SYCHAR SUPPLIERS</p>
        <hr style="border-top: 1px solid #bbb; margin-top: 1rem">
        <table style="margin-top: 1rem;">
            <tr>
                <td>Direct deposite to <span style="font-weight: bold">NCBA Bank</span></td>
                <td>ACCOUNT NUMBER: <span style="font-weight: bold">1001821276. GALLERIA BRANCH</span></td>
                <td>PayBill No: <span style="font-weight: bold">880100</span> Account: <span style="font-weight: bold">1001821276</span></td>
            </tr>
        </table>
        <table>
            <tr>
                <td style="width: 5%;">
                    <img src="{{ public_path('icon.png') }}" style="height: 2.5em; width: auto;" alt="Sychar Suppliers Logo" />
                </td>
                <td style="width: 15%;">(+254)0772059705</td>
                <td style="width: 80%;">sales@sycharsuppliers.com</td>
            </tr>
        </table>
    </div>

</body>
</html>
