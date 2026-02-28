<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Tax Report {{ $year }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9pt;
            color: #1a1a2e;
            background: #fff;
            line-height: 1.4;
        }

        .page { padding: 28pt 32pt; }

        .header {
            border-bottom: 3px solid #235478;
            padding-bottom: 14pt;
            margin-bottom: 16pt;
        }
        .header-table { width: 100%; }
        .company-name {
            font-size: 16pt;
            font-weight: bold;
            color: #235478;
        }
        .company-details {
            font-size: 8pt;
            color: #44403d;
            margin-top: 3pt;
            line-height: 1.6;
        }
        .report-title-block { text-align: right; }
        .report-title {
            font-size: 18pt;
            font-weight: bold;
            color: #1a1a2e;
            text-transform: uppercase;
            letter-spacing: 1pt;
        }
        .report-subtitle {
            font-size: 9pt;
            color: #44403d;
            margin-top: 3pt;
        }
        .report-period {
            font-size: 8pt;
            color: #888;
            margin-top: 2pt;
        }

        .section-heading {
            font-size: 9pt;
            font-weight: bold;
            color: #1a1a2e;
            text-transform: uppercase;
            letter-spacing: 0.5pt;
            padding: 4pt 0;
            border-bottom: 2px solid #235478;
            margin-bottom: 8pt;
            margin-top: 16pt;
        }

        .kpi-table { width: 100%; border-collapse: collapse; margin-bottom: 16pt; }
        .kpi-cell {
            width: 25%;
            padding: 10pt 12pt;
            background: #f8faff;
            border: 1px solid #dde4f5;
            vertical-align: top;
        }
        .kpi-cell + .kpi-cell { border-left: none; }
        .kpi-label {
            font-size: 7.5pt;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5pt;
        }
        .kpi-value-green  { font-size: 14pt; font-weight: bold; color: #37847a; margin-top: 3pt; }
        .kpi-value-blue   { font-size: 14pt; font-weight: bold; color: #235478; margin-top: 3pt; }
        .kpi-value-orange { font-size: 14pt; font-weight: bold; color: #f29b55; margin-top: 3pt; }
        .kpi-sub {
            font-size: 7pt;
            color: #aaa;
            margin-top: 2pt;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14pt;
            font-size: 8.5pt;
        }
        .data-table thead tr { background: #235478; color: #fff; }
        .data-table thead th {
            padding: 5pt 7pt;
            text-align: left;
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4pt;
        }
        .data-table thead th.r { text-align: right; }
        .data-table tbody tr { border-bottom: 1px solid #e8eef8; }
        .data-table tbody tr:nth-child(even) { background: #f8faff; }
        .data-table td { padding: 5pt 7pt; }
        .data-table td.r { text-align: right; font-family: DejaVu Sans Mono, monospace; }
        .data-table .total-row td {
            font-weight: bold;
            border-top: 2px solid #235478;
            background: #eef2fc;
        }
        .col-positive { color: #37847a; }
        .col-negative { color: #9b1c1c; }

        .two-col { width: 100%; border-collapse: collapse; margin-bottom: 14pt; }
        .two-col-left  { width: 48%; vertical-align: top; }
        .two-col-right { width: 48%; vertical-align: top; padding-left: 4%; }

        .footer {
            margin-top: 24pt;
            padding-top: 8pt;
            border-top: 1px solid #dde4f5;
        }
        .footer-table { width: 100%; }
        .footer-note { font-size: 7pt; color: #aaa; line-height: 1.6; }
        .footer-right { font-size: 7pt; color: #aaa; text-align: right; }

        .disclaimer {
            background: #fef3c7;
            border: 1px solid #ffd83f;
            border-left: 4px solid #ffcb00;
            padding: 6pt 10pt;
            margin-bottom: 14pt;
            font-size: 7.5pt;
            color: #78350f;
        }

        .empty-row td {
            text-align: center;
            color: #aaa;
            font-style: italic;
            padding: 14pt;
        }
    </style>
</head>
<body>
<div class="page">

    <div class="header">
        <table class="header-table">
            <tr>
                <td style="width:55%; vertical-align:top;">
                    @if($company['logo'])
                        <img src="{{ $company['logo'] }}" style="max-height:32pt; max-width:110pt; margin-bottom:4pt;" alt="Logo">
                        <br>
                    @endif
                    {{-- <img src="{{ public_path('logo.png') }}" style="width: 25%" alt="Logo"> --}}
                    <div class="company-name">{{ $company['name'] }}</div>
                    <div class="company-details">
                        @if($company['address'])
                            {{ $company['address'] }}<br>
                        @endif
                        @if($company['phone'])
                            Tel: {{ $company['phone'] }}<br>
                        @endif
                        @if($company['email'])
                            Email: {{ $company['email'] }}<br>
                        @endif
                        @if($company['pin'])
                            Tax PIN / KRA PIN: <strong>{{ $company['pin'] }}</strong>
                        @endif
                    </div>
                </td>
                <td class="report-title-block" style="vertical-align:top;">
                    <div class="report-title">Tax Report</div>
                    <div class="report-subtitle">Income Tax Statement &mdash; {{ $year }}</div>
                    <div class="report-period">Period: {{ $start_date }} to {{ $end_date }}</div>
                    <div class="report-period">Generated: {{ $generated_at->format('d M Y  g:i A') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="disclaimer">
        <strong>Note:</strong> This report summarises revenue billed and cash collected within the stated period.
        It is prepared to assist in income tax filing. Please verify figures with your accountant before submission.
        Outstanding receivables are shown as at the end date of the period.
    </div>

    <div class="section-heading">Revenue Summary</div>
    <table class="kpi-table">
        <tr>
            <td class="kpi-cell">
                <div class="kpi-label">Gross Revenue Billed</div>
                <div class="kpi-value-blue">{{ number_format($summary['total_billed'], 2) }}</div>
                <div class="kpi-sub">{{ number_format($summary['total_bill_count']) }} bills issued</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-label">Cash Collected</div>
                <div class="kpi-value-green">{{ number_format($summary['total_collected'], 2) }}</div>
                <div class="kpi-sub">{{ number_format($summary['total_payment_count']) }} payments received</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-label">Outstanding Receivables</div>
                <div class="kpi-value-orange">{{ number_format($summary['outstanding_balance'], 2) }}</div>
                <div class="kpi-sub">Unpaid as at {{ $end_date }}</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-label">Collection Rate</div>
                @if($summary['collection_rate'] >= 80)
                    <div class="kpi-value-green">{{ $summary['collection_rate'] }}%</div>
                @else
                    <div class="kpi-value-orange">{{ $summary['collection_rate'] }}%</div>
                @endif
                <div class="kpi-sub">Collected / Billed</div>
            </td>
        </tr>
    </table>

    <div class="section-heading">Monthly Revenue Breakdown</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Month</th>
                <th class="r">Bills Issued</th>
                <th class="r">Amount Billed</th>
                <th class="r">Payments</th>
                <th class="r">Amount Collected</th>
                <th class="r">Variance</th>
            </tr>
        </thead>
        <tbody>
            @php
                $grandBilled    = 0;
                $grandCollected = 0;
                $grandVariance  = 0;
            @endphp
            @forelse($summary['monthly'] as $row)
                @php
                    $grandBilled    += $row['billed'];
                    $grandCollected += $row['collected'];
                    $grandVariance  += $row['variance'];
                @endphp
                <tr>
                    <td>{{ $row['label'] }}</td>
                    <td class="r">{{ number_format($row['bill_count']) }}</td>
                    <td class="r">{{ number_format($row['billed'], 2) }}</td>
                    <td class="r">{{ number_format($row['payment_count']) }}</td>
                    <td class="r">{{ number_format($row['collected'], 2) }}</td>
                    @if($row['variance'] > 0)
                        <td class="r col-negative">{{ number_format($row['variance'], 2) }}</td>
                    @else
                        <td class="r col-positive">{{ number_format($row['variance'], 2) }}</td>
                    @endif
                </tr>
            @empty
                <tr class="empty-row">
                    <td colspan="6">No data for this period.</td>
                </tr>
            @endforelse
            <tr class="total-row">
                <td>TOTAL</td>
                <td class="r">{{ number_format($summary['total_bill_count']) }}</td>
                <td class="r">{{ number_format($grandBilled, 2) }}</td>
                <td class="r">{{ number_format($summary['total_payment_count']) }}</td>
                <td class="r">{{ number_format($grandCollected, 2) }}</td>
                @if($grandVariance > 0)
                    <td class="r col-negative">{{ number_format($grandVariance, 2) }}</td>
                @else
                    <td class="r col-positive">{{ number_format($grandVariance, 2) }}</td>
                @endif
            </tr>
        </tbody>
    </table>

    <table class="two-col">
        <tr>
            <td class="two-col-left">
                <div class="section-heading" style="margin-top:0;">Collections by Payment Method</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Method</th>
                            <th class="r">Transactions</th>
                            <th class="r">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($summary['by_method'] as $m)
                            <tr>
                                <td>{{ $m['method'] }}</td>
                                <td class="r">{{ number_format($m['count']) }}</td>
                                <td class="r">{{ number_format($m['total'], 2) }}</td>
                            </tr>
                        @empty
                            <tr class="empty-row">
                                <td colspan="3">No payments.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
            <td class="two-col-right">
                <div class="section-heading" style="margin-top:0;">Bills by Status</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th class="r">Count</th>
                            <th class="r">Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($summary['by_status'] as $s)
                            <tr>
                                <td>{{ ucfirst(str_replace('_', ' ', $s['status'])) }}</td>
                                <td class="r">{{ number_format($s['count']) }}</td>
                                <td class="r">{{ number_format($s['total'], 2) }}</td>
                            </tr>
                        @empty
                            <tr class="empty-row">
                                <td colspan="3">No bills.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <div class="footer">
        <table class="footer-table">
            <tr>
                <td style="vertical-align:top;">
                    <div class="footer-note">
                        This report was generated automatically by {{ $company['name'] }}.<br>
                        All amounts are in the local currency. Figures are based on system records only.<br>
                        For income tax filing purposes &mdash; consult your tax advisor for deductible expenses.
                    </div>
                </td>
                <td style="vertical-align:bottom;">
                    <div class="footer-right">
                        {{ $company['name'] }}<br>
                        Tax Year: {{ $year }}<br>
                        Computer-generated &mdash; no signature required.
                    </div>
                </td>
            </tr>
        </table>
    </div>

</div>
</body>
</html>