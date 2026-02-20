<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Bill Statement #{{ $billing['id'] }}</title>
    <style>
        /* ── Reset & Base ─────────────────────────────────────────── */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, sans-serif; /* DomPDF-safe font with full UTF-8 support */
            font-size: 9pt;
            color: #1a1a2e;
            background: #fff;
            line-height: 1.4;
        }

        /* ── Page Layout ─────────────────────────────────────────── */
        .page {
            padding: 28pt 32pt 28pt 32pt;
        }

        /* ── Header ──────────────────────────────────────────────── */
        .header {
            border-bottom: 3px solid #1a56db;
            padding-bottom: 14pt;
            margin-bottom: 16pt;
        }

        .header-table {
            width: 100%;
        }

        .company-name {
            font-size: 18pt;
            font-weight: bold;
            color: #1a56db;
            letter-spacing: 0.5pt;
        }

        .company-details {
            font-size: 8pt;
            color: #555;
            margin-top: 3pt;
            line-height: 1.6;
        }

        .bill-title-block {
            text-align: right;
        }

        .bill-title {
            font-size: 20pt;
            font-weight: bold;
            color: #1a1a2e;
            letter-spacing: 1pt;
            text-transform: uppercase;
        }

        .bill-number {
            font-size: 9pt;
            color: #555;
            margin-top: 3pt;
        }

        .generated-date {
            font-size: 7.5pt;
            color: #888;
            margin-top: 2pt;
        }

        /* ── Status Badge ─────────────────────────────────────────── */
        .status-badge {
            display: inline-block;
            padding: 2pt 8pt;
            border-radius: 3pt;
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5pt;
            margin-top: 5pt;
        }

        .status-paid     { background: #def7ec; color: #03543f; border: 1px solid #84e1bc; }
        .status-pending  { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
        .status-overdue  { background: #fde8e8; color: #9b1c1c; border: 1px solid #f98080; }
        .status-voided   { background: #f3f4f6; color: #6b7280; border: 1px solid #d1d5db; }
        .status-partially_paid { background: #e1effe; color: #1e429f; border: 1px solid #76a9fa; }

        /* ── Info Grid (Account / Bill Details) ───────────────────── */
        .info-section {
            margin-bottom: 16pt;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-box {
            width: 48%;
            vertical-align: top;
            padding: 10pt 12pt;
            background: #f8faff;
            border: 1px solid #dde4f5;
            border-radius: 4pt;
        }

        .info-box-spacer {
            width: 4%;
        }

        .info-box-title {
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.8pt;
            color: #1a56db;
            border-bottom: 1px solid #dde4f5;
            padding-bottom: 4pt;
            margin-bottom: 7pt;
        }

        .info-row {
            margin-bottom: 3pt;
        }

        .info-label {
            font-size: 7.5pt;
            color: #888;
            display: inline-block;
            width: 90pt;
        }

        .info-value {
            font-size: 8.5pt;
            font-weight: bold;
            color: #1a1a2e;
        }

        /* Overdue alert */
        .overdue-alert {
            background: #fde8e8;
            border: 1px solid #f98080;
            border-left: 4px solid #e02424;
            padding: 6pt 10pt;
            margin-bottom: 12pt;
            border-radius: 3pt;
        }

        .overdue-alert-text {
            font-size: 8.5pt;
            font-weight: bold;
            color: #9b1c1c;
        }

        /* ── Section Headings ─────────────────────────────────────── */
        .section-heading {
            font-size: 9pt;
            font-weight: bold;
            color: #1a1a2e;
            text-transform: uppercase;
            letter-spacing: 0.5pt;
            padding: 4pt 0;
            border-bottom: 2px solid #1a56db;
            margin-bottom: 8pt;
        }

        /* ── Consumption Table ────────────────────────────────────── */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14pt;
            font-size: 8.5pt;
        }

        .data-table thead tr {
            background: #1a56db;
            color: #fff;
        }

        .data-table thead th {
            padding: 5pt 7pt;
            text-align: left;
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4pt;
            white-space: nowrap;
        }

        .data-table thead th.text-right {
            text-align: right;
        }

        .data-table tbody tr {
            border-bottom: 1px solid #e8eef8;
        }

        .data-table tbody tr:nth-child(even) {
            background: #f8faff;
        }

        .data-table tbody tr:last-child {
            border-bottom: none;
        }

        .data-table td {
            padding: 5pt 7pt;
            vertical-align: middle;
        }

        .data-table td.text-right {
            text-align: right;
        }

        .data-table td.mono {
            font-family: DejaVu Sans Mono, monospace;
        }

        .data-table .meter-name {
            font-size: 7.5pt;
            color: #888;
            display: block;
        }

        /* Empty state */
        .empty-row td {
            text-align: center;
            color: #aaa;
            font-style: italic;
            padding: 14pt;
        }

        /* ── Amounts Summary ──────────────────────────────────────── */
        .amounts-section {
            margin-bottom: 16pt;
        }

        .amounts-table-wrap {
            width: 100%;
        }

        .amounts-spacer {
            width: 55%;
        }

        .amounts-table {
            width: 45%;
            border-collapse: collapse;
            font-size: 8.5pt;
        }

        .amounts-table tr {
            border-bottom: 1px solid #e8eef8;
        }

        .amounts-table td {
            padding: 4pt 8pt;
        }

        .amounts-table td:last-child {
            text-align: right;
            font-family: DejaVu Sans Mono, monospace;
        }

        .amounts-table .label-col {
            color: #555;
        }

        .amounts-table .subtotal-row td {
            color: #333;
        }

        .amounts-table .late-fee-row td {
            color: #9b1c1c;
        }

        .amounts-table .total-row {
            background: #1a56db;
        }

        .amounts-table .total-row td {
            color: #fff;
            font-weight: bold;
            font-size: 9.5pt;
            padding: 5pt 8pt;
        }

        .amounts-table .paid-row td {
            color: #03543f;
        }

        .amounts-table .balance-row td {
            font-weight: bold;
            font-size: 9pt;
            border-top: 2px solid #1a56db;
        }

        /* ── Payment History ──────────────────────────────────────── */
        .payment-badge {
            display: inline-block;
            background: #def7ec;
            color: #03543f;
            border: 1px solid #84e1bc;
            border-radius: 2pt;
            padding: 1pt 5pt;
            font-size: 7pt;
            font-weight: bold;
        }

        /* ── Footer ──────────────────────────────────────────────── */
        .footer {
            margin-top: 20pt;
            padding-top: 10pt;
            border-top: 1px solid #dde4f5;
        }

        .footer-table {
            width: 100%;
        }

        .footer-note {
            font-size: 7.5pt;
            color: #888;
            line-height: 1.6;
        }

        .footer-company {
            font-size: 7.5pt;
            color: #888;
            text-align: right;
        }

        /* ── Utility ──────────────────────────────────────────────── */
        .text-right  { text-align: right; }
        .text-center { text-align: center; }
        .font-bold   { font-weight: bold; }
        .text-blue   { color: #1a56db; }
        .mt-4        { margin-top: 4pt; }
        .mt-8        { margin-top: 8pt; }
    </style>
</head>
<body>
<div class="page">

    {{-- ══════════════════════════════════════════════════════ --}}
    {{-- HEADER                                                 --}}
    {{-- ══════════════════════════════════════════════════════ --}}
    <div class="header">
        <table class="header-table">
            <tr>
                {{-- Company info --}}
                <td style="width:60%; vertical-align:top;">
                    @if($company['logo'])
                        <img src="{{ $company['logo'] }}"
                             style="max-height:36pt; max-width:120pt; margin-bottom:5pt;" alt="Logo">
                        <br>
                    @endif
                    <div class="company-name">{{ $company['name'] }}</div>
                    <div class="company-details">
                        @if($company['address']){{ $company['address'] }}<br>@endif
                        @if($company['phone'])Tel: {{ $company['phone'] }}{{ $company['email'] ? '  |  ' : '' }}@endif
                        @if($company['email'])Email: {{ $company['email'] }}@endif
                        @if($company['website'])<br>{{ $company['website'] }}@endif
                    </div>
                </td>

                {{-- Bill title --}}
                <td class="bill-title-block" style="vertical-align:top;">
                    <div class="bill-title">Bill Statement</div>
                    <div class="bill-number">Bill #{{ $billing['id'] }}</div>
                    <div class="generated-date">Generated: {{ $generated_at->format('F j, Y  g:i A') }}</div>
                    @php
                        $statusKey   = strtolower(str_replace(' ', '_', $billing['status']));
                        $statusClass = 'status-' . $statusKey;
                    @endphp
                    <div><span class="status-badge {{ $statusClass }}">{{ $billing['status'] }}</span></div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ══════════════════════════════════════════════════════ --}}
    {{-- OVERDUE ALERT                                          --}}
    {{-- ══════════════════════════════════════════════════════ --}}
    @if($billing['is_overdue'])
    <div class="overdue-alert">
        <span class="overdue-alert-text">
            &#9888; OVERDUE — This bill is {{ $billing['days_overdue'] }} day{{ $billing['days_overdue'] != 1 ? 's' : '' }} past due.
            Immediate payment is required to avoid service interruption.
        </span>
    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════ --}}
    {{-- ACCOUNT / BILL INFO                                    --}}
    {{-- ══════════════════════════════════════════════════════ --}}
    <div class="info-section">
        <table class="info-table">
            <tr>
                {{-- Account details --}}
                <td class="info-box">
                    <div class="info-box-title">Account Information</div>

                    <div class="info-row">
                        <span class="info-label">Account No.</span>
                        <span class="info-value text-blue">{{ $account['number'] }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Name</span>
                        <span class="info-value">{{ $account['name'] }}</span>
                    </div>
                    @if($account['address'])
                    <div class="info-row">
                        <span class="info-label">Address</span>
                        <span class="info-value">{{ $account['address'] }}</span>
                    </div>
                    @endif
                    @if($account['phone'])
                    <div class="info-row">
                        <span class="info-label">Phone</span>
                        <span class="info-value">{{ $account['phone'] }}</span>
                    </div>
                    @endif
                    @if($account['email'])
                    <div class="info-row">
                        <span class="info-label">Email</span>
                        <span class="info-value">{{ $account['email'] }}</span>
                    </div>
                    @endif
                </td>

                <td class="info-box-spacer"></td>

                {{-- Bill details --}}
                <td class="info-box">
                    <div class="info-box-title">Bill Details</div>

                    <div class="info-row">
                        <span class="info-label">Billing Period</span>
                        <span class="info-value text-blue">
                            @php
                                // e.g. "2026-02" → "February 2026"
                                try {
                                    echo \Carbon\Carbon::createFromFormat('Y-m', $billing['period'])->format('F Y');
                                } catch (\Exception $e) {
                                    echo $billing['period'];
                                }
                            @endphp
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Issue Date</span>
                        <span class="info-value">{{ $billing['issued_date'] }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Due Date</span>
                        <span class="info-value {{ $billing['is_overdue'] ? 'text-red' : '' }}">
                            {{ $billing['due_date'] }}
                            @if($billing['is_overdue'])
                                <span style="color:#e02424; font-size:7pt;">(OVERDUE)</span>
                            @endif
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Status</span>
                        <span class="info-value">{{ $billing['status'] }}</span>
                    </div>

                    {{-- Outstanding balance from account summary --}}
                    @if(isset($account_summary))
                    <div class="info-row mt-4" style="border-top:1px solid #dde4f5; padding-top:5pt;">
                        <span class="info-label">Acc. Balance</span>
                        <span class="info-value" style="color:{{ ($account_summary['balance'] ?? 0) > 0 ? '#e02424' : '#03543f' }}">
                            {{ number_format($account_summary['balance'] ?? 0, 2) }}
                        </span>
                    </div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- ══════════════════════════════════════════════════════ --}}
    {{-- CONSUMPTION DETAILS                                    --}}
    {{-- ══════════════════════════════════════════════════════ --}}
    <div class="section-heading">Consumption Details</div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Meter</th>
                <th class="text-right">Prev. Reading</th>
                <th class="text-right">Curr. Reading</th>
                <th class="text-right">Units Used</th>
                <th class="text-right">Rate</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($details as $detail)
            <tr>
                <td>
                    <span class="font-bold">{{ $detail['meter_number'] }}</span>
                    @if($detail['meter_name'])
                        <span class="meter-name">{{ $detail['meter_name'] }}</span>
                    @endif
                </td>
                <td class="text-right mono">{{ $detail['previous_reading'] }} m³</td>
                <td class="text-right mono">{{ $detail['current_reading'] }} m³</td>
                <td class="text-right mono font-bold">{{ $detail['consumption'] }} m³</td>
                <td class="text-right mono">{{ $detail['rate'] }}</td>
                <td class="text-right mono font-bold">{{ $detail['amount'] }}</td>
            </tr>
            @empty
            <tr class="empty-row">
                <td colspan="6">No consumption details recorded for this billing period.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    {{-- ══════════════════════════════════════════════════════ --}}
    {{-- AMOUNTS SUMMARY                                        --}}
    {{-- ══════════════════════════════════════════════════════ --}}
    <div class="amounts-section">
        <table class="amounts-table-wrap">
            <tr>
                <td class="amounts-spacer"></td>
                <td>
                    <table class="amounts-table">
                        <tr class="subtotal-row">
                            <td class="label-col">Subtotal</td>
                            <td>{{ $amounts['subtotal'] }}</td>
                        </tr>

                        @if($amounts['late_fee'])
                        <tr class="late-fee-row">
                            <td class="label-col">Late Fee</td>
                            <td>{{ $amounts['late_fee'] }}</td>
                        </tr>
                        @endif

                        <tr class="total-row">
                            <td>Total Due</td>
                            <td>{{ $amounts['total'] }}</td>
                        </tr>

                        @if((float) str_replace(',', '', $amounts['paid']) > 0)
                        <tr class="paid-row">
                            <td class="label-col">Paid</td>
                            <td>({{ $amounts['paid'] }})</td>
                        </tr>
                        @endif

                        <tr class="balance-row">
                            <td class="label-col">Balance Due</td>
                            <td style="color:{{ (float) str_replace(',', '', $amounts['balance']) > 0 ? '#e02424' : '#03543f' }};">
                                {{ $amounts['balance'] }}
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    {{-- ══════════════════════════════════════════════════════ --}}
    {{-- PAYMENT HISTORY                                        --}}
    {{-- ══════════════════════════════════════════════════════ --}}
    @if(count($payments) > 0)
    <div class="section-heading mt-8">Payment History</div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Reference</th>
                <th>Method</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payments as $payment)
            <tr>
                <td>{{ $payment['date'] }}</td>
                <td>
                    <span class="payment-badge">{{ $payment['reference'] }}</span>
                </td>
                <td>{{ $payment['method'] }}</td>
                <td class="text-right mono font-bold">{{ $payment['amount'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- ══════════════════════════════════════════════════════ --}}
    {{-- FOOTER                                                 --}}
    {{-- ══════════════════════════════════════════════════════ --}}
    <div class="footer">
        <table class="footer-table">
            <tr>
                <td style="width:60%; vertical-align:top;">
                    <div class="footer-note">
                        <strong>Payment Instructions:</strong><br>
                        Please include your account number <strong>{{ $account['number'] }}</strong>
                        as reference when making payment.<br>
                        @if($company['phone'])
                            For queries, contact us on {{ $company['phone'] }}.
                        @endif
                        @if($company['email'])
                            Email: {{ $company['email'] }}
                        @endif
                    </div>
                </td>
                <td style="vertical-align:bottom;">
                    <div class="footer-company">
                        {{ $company['name'] }}<br>
                        This is a computer-generated statement.<br>
                        No signature required.
                    </div>
                </td>
            </tr>
        </table>
    </div>

</div>
</body>
</html>