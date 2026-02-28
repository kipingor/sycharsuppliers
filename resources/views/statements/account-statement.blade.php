<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Account Statement — {{ $account['name'] }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #1a1a2e; line-height: 1.4; }
        .page { padding: 28pt 32pt; }

        /* Header */
        .header { border-bottom: 3px solid #1a56db; padding-bottom: 14pt; margin-bottom: 16pt; }
        .header-table { width: 100%; }
        .company-name { font-size: 16pt; font-weight: bold; color: #1a56db; }
        .company-details { font-size: 8pt; color: #555; margin-top: 3pt; line-height: 1.6; }
        .doc-title { font-size: 20pt; font-weight: bold; color: #1a1a2e; text-transform: uppercase; letter-spacing: 1pt; text-align: right; }
        .doc-sub { font-size: 8pt; color: #888; text-align: right; margin-top: 3pt; }

        /* Info boxes */
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 14pt; }
        .info-box { width: 48%; padding: 10pt 12pt; background: #f8faff; border: 1px solid #dde4f5; vertical-align: top; }
        .info-box-spacer { width: 4%; }
        .info-box-title { font-size: 7.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.8pt; color: #1a56db; border-bottom: 1px solid #dde4f5; padding-bottom: 4pt; margin-bottom: 7pt; }
        .info-row { margin-bottom: 3pt; }
        .info-label { font-size: 7.5pt; color: #888; display: inline-block; width: 80pt; }
        .info-value { font-size: 8.5pt; font-weight: bold; color: #1a1a2e; }
        .info-value-blue { font-size: 8.5pt; font-weight: bold; color: #1a56db; }

        /* Summary KPI row */
        .kpi-table { width: 100%; border-collapse: collapse; margin-bottom: 14pt; }
        .kpi-cell { width: 25%; padding: 8pt 10pt; border: 1px solid #dde4f5; vertical-align: top; background: #f8faff; }
        .kpi-cell + .kpi-cell { border-left: none; }
        .kpi-label { font-size: 7pt; color: #888; text-transform: uppercase; letter-spacing: 0.5pt; }
        .kpi-value-blue   { font-size: 13pt; font-weight: bold; color: #1a56db;  margin-top: 2pt; }
        .kpi-value-green  { font-size: 13pt; font-weight: bold; color: #03543f;  margin-top: 2pt; }
        .kpi-value-amber  { font-size: 13pt; font-weight: bold; color: #92400e;  margin-top: 2pt; }
        .kpi-value-red    { font-size: 13pt; font-weight: bold; color: #9b1c1c;  margin-top: 2pt; }
        .kpi-sub { font-size: 7pt; color: #aaa; margin-top: 1pt; }

        /* Section heading */
        .section-heading { font-size: 9pt; font-weight: bold; color: #1a1a2e; text-transform: uppercase; letter-spacing: 0.5pt; padding: 4pt 0; border-bottom: 2px solid #1a56db; margin-bottom: 8pt; margin-top: 14pt; }

        /* Ledger table */
        .ledger { width: 100%; border-collapse: collapse; font-size: 8pt; }
        .ledger thead tr { background: #1a56db; color: #fff; }
        .ledger thead th { padding: 5pt 6pt; text-align: left; font-size: 7.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.3pt; }
        .ledger thead th.r { text-align: right; }
        .ledger tbody tr { border-bottom: 1px solid #eef0f8; }
        .ledger tbody tr:nth-child(even) { background: #f8faff; }
        .ledger td { padding: 4pt 6pt; vertical-align: top; }
        .ledger td.r { text-align: right; font-family: DejaVu Sans Mono, monospace; }
        .ledger td.mono { font-family: DejaVu Sans Mono, monospace; }
        .ledger .opening-row td { background: #eef2fc; font-weight: bold; }
        .ledger .closing-row td { background: #1a56db; color: #fff; font-weight: bold; }
        .ledger .closing-row td.r { font-family: DejaVu Sans Mono, monospace; }

        .type-bill        { color: #1a56db; font-size: 7pt; font-weight: bold; text-transform: uppercase; }
        .type-payment     { color: #03543f; font-size: 7pt; font-weight: bold; text-transform: uppercase; }
        .type-credit-note { color: #92400e; font-size: 7pt; font-weight: bold; text-transform: uppercase; }

        .col-debit   { color: #9b1c1c; }
        .col-credit  { color: #03543f; }
        .bal-positive { color: #9b1c1c; }
        .bal-zero     { color: #03543f; }

        /* Footer */
        .footer { margin-top: 20pt; padding-top: 8pt; border-top: 1px solid #dde4f5; }
        .footer-note { font-size: 7pt; color: #aaa; line-height: 1.6; }
        .footer-right { font-size: 7pt; color: #aaa; text-align: right; }

        .empty-row td { text-align: center; color: #aaa; font-style: italic; padding: 14pt; }
        .mt-0 { margin-top: 0; }
    </style>
</head>
<body>
<div class="page">

    {{-- HEADER --}}
    <div class="header">
        <table class="header-table">
            <tr>
                <td style="width:55%; vertical-align:top;">
                    @if($company['logo'])
                        <img src="{{ $company['logo'] }}" style="max-height:32pt; max-width:110pt; margin-bottom:4pt;" alt="Logo"><br>
                    @endif
                    <div class="company-name">{{ $company['name'] }}</div>
                    <div class="company-details">
                        @if($company['address']){{ $company['address'] }}<br>@endif
                        @if($company['phone'])Tel: {{ $company['phone'] }}<br>@endif
                        @if($company['email'])Email: {{ $company['email'] }}@endif
                    </div>
                </td>
                <td style="vertical-align:top;">
                    <div class="doc-title">Account Statement</div>
                    <div class="doc-sub">Period: {{ $period['start'] }} &mdash; {{ $period['end'] }}</div>
                    <div class="doc-sub">Generated: {{ $generated_at->format('d M Y  g:i A') }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ACCOUNT + STATEMENT INFO --}}
    <table class="info-table">
        <tr>
            <td class="info-box">
                <div class="info-box-title">Account Information</div>
                <div class="info-row">
                    <span class="info-label">Account No.</span>
                    <span class="info-value-blue">{{ $account['number'] }}</span>
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
            <td class="info-box">
                <div class="info-box-title">Statement Summary</div>
                <div class="info-row">
                    <span class="info-label">Period From</span>
                    <span class="info-value">{{ $period['start'] }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Period To</span>
                    <span class="info-value">{{ $period['end'] }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Opening Balance</span>
                    <span class="info-value">{{ number_format($opening_balance, 2) }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Closing Balance</span>
                    @if($closing_balance > 0)
                        <span class="info-value" style="color:#9b1c1c;">{{ number_format($closing_balance, 2) }}</span>
                    @else
                        <span class="info-value" style="color:#03543f;">{{ number_format($closing_balance, 2) }}</span>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    {{-- KPI SUMMARY --}}
    <table class="kpi-table">
        <tr>
            <td class="kpi-cell">
                <div class="kpi-label">Total Billed</div>
                <div class="kpi-value-blue">{{ number_format($total_billed, 2) }}</div>
                <div class="kpi-sub">Charges in period</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-label">Total Paid</div>
                <div class="kpi-value-green">{{ number_format($total_paid, 2) }}</div>
                <div class="kpi-sub">Payments received</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-label">Credits Applied</div>
                <div class="kpi-value-amber">{{ number_format($total_credited, 2) }}</div>
                <div class="kpi-sub">Credit notes</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-label">Closing Balance</div>
                @if($closing_balance > 0)
                    <div class="kpi-value-red">{{ number_format($closing_balance, 2) }}</div>
                    <div class="kpi-sub">Amount owed</div>
                @else
                    <div class="kpi-value-green">0.00</div>
                    <div class="kpi-sub">Fully settled</div>
                @endif
            </td>
        </tr>
    </table>

    {{-- TRANSACTION LEDGER --}}
    <div class="section-heading mt-0">Transaction Ledger</div>
    <table class="ledger">
        <thead>
            <tr>
                <th>Date</th>
                <th>Reference</th>
                <th>Description</th>
                <th class="r">Debit (Charges)</th>
                <th class="r">Credit (Payments)</th>
                <th class="r">Running Balance</th>
            </tr>
        </thead>
        <tbody>
            {{-- Opening balance row --}}
            <tr class="opening-row">
                <td>{{ $period['start'] }}</td>
                <td colspan="2">Opening Balance (brought forward)</td>
                <td class="r"></td>
                <td class="r"></td>
                <td class="r">{{ number_format($opening_balance, 2) }}</td>
            </tr>

            @forelse($transactions as $t)
                <tr>
                    <td class="mono" style="white-space:nowrap;">{{ $t['date'] }}</td>
                    <td>
                        <span class="mono">{{ $t['reference'] }}</span><br>
                        @if($t['type'] === 'bill')
                            <span class="type-bill">Bill</span>
                        @elseif($t['type'] === 'payment')
                            <span class="type-payment">Payment</span>
                        @else
                            <span class="type-credit-note">Credit Note</span>
                        @endif
                    </td>
                    <td style="max-width:160pt;">{{ $t['description'] }}</td>
                    <td class="r col-debit">
                        @if($t['debit'])
                            {{ number_format($t['debit'], 2) }}
                        @else
                            &mdash;
                        @endif
                    </td>
                    <td class="r col-credit">
                        @if($t['credit'])
                            {{ number_format($t['credit'], 2) }}
                        @else
                            &mdash;
                        @endif
                    </td>
                    <td class="r {{ ($opening_balance + $t['running_balance']) > 0 ? 'bal-positive' : 'bal-zero' }}">
                        {{ number_format($opening_balance + $t['running_balance'], 2) }}
                    </td>
                </tr>
            @empty
                <tr class="empty-row">
                    <td colspan="6">No transactions in this period.</td>
                </tr>
            @endforelse

            {{-- Closing balance row --}}
            <tr class="closing-row">
                <td>{{ $period['end'] }}</td>
                <td colspan="2"><strong>Closing Balance</strong></td>
                <td class="r">{{ number_format($total_billed, 2) }}</td>
                <td class="r">{{ number_format($total_paid + $total_credited, 2) }}</td>
                <td class="r">{{ number_format($closing_balance, 2) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- FOOTER --}}
    <div class="footer">
        <table style="width:100%;">
            <tr>
                <td style="vertical-align:top;">
                    <div class="footer-note">
                        This statement shows all charges and payments for account
                        <strong>{{ $account['number'] }}</strong> during the stated period.<br>
                        If you have any queries, please contact us at
                        @if($company['phone']){{ $company['phone'] }}@endif
                        @if($company['email']) / {{ $company['email'] }}@endif.<br>
                        Please quote your account number in all correspondence.
                    </div>
                </td>
                <td style="vertical-align:bottom; text-align:right;">
                    <div class="footer-right">
                        {{ $company['name'] }}<br>
                        Computer-generated statement.<br>
                        No signature required.
                    </div>
                </td>
            </tr>
        </table>
    </div>

</div>
</body>
</html>