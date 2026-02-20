<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Meter Reading List</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        h1 { text-align: center; color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .header { text-align: center; margin-bottom: 20px; }
        .date { text-align: right; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Meter Reading List</h1>
        <div class="date">Generated: {{ now()->format('d M Y, H:i') }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Meter Number</th>
                <th>Meter Name</th>
                <th>Account</th>
                <th>Last Reading</th>
                <th>Reading Date</th>
                <th>Current Reading</th>
            </tr>
        </thead>
        <tbody>
            @foreach($meters as $index => $meter)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $meter->meter_number }}</td>
                <td>{{ $meter->meter_name }}</td>
                <td>
                    @if($meter->account)
                        {{ $meter->account->name }}<br>
                        <small>{{ $meter->account->account_number }}</small>
                    @else
                        N/A
                    @endif
                </td>
                <td>
                    @if($meter->readings->isNotEmpty())
                        {{ number_format($meter->readings->first()->reading, 2) }} m³
                    @else
                        No readings
                    @endif
                </td>
                <td>
                    @if($meter->readings->isNotEmpty())
                        {{ $meter->readings->first()->reading_date }}
                    @else
                        -
                    @endif
                </td>
                <td style="width: 100px; border-bottom: 1px solid #000;"></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div style="margin-top: 30px; font-size: 10px; color: #666;">
        <p>Total Meters: {{ count($meters) }}</p>
        <p>Active Meters: {{ $meters->where('status', 'active')->count() }}</p>
    </div>
</body>
</html>