<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Meter Reading List</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f5f5f5;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 10px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Meter Reading List</h1>
        <p>Generated on: {{ now()->format('F j, Y') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Meter Number</th>
                <th>Resident Name</th>
                <th>Location</th>
                <th>Last Reading</th>
                <th>Reading Date</th>
                <th>Status</th>
                <th>Latest Reading</th>
            </tr>
        </thead>
        <tbody>
            @foreach($meters as $meter)
            <tr>
                <td>{{ $meter->meter_number }}</td>
                <td>{{ $meter->resident->name ?? 'N/A' }}</td>
                <td>{{ $meter->location }}</td>
                <td>{{ $meter->meterReadings->last()?->reading_value ?? 'N/A' }}</td>
                <td>{{ $meter->meterReadings->last()?->reading_date?->format('Y-m-d') ?? 'N/A' }}</td>
                <td>{{ ucfirst($meter->status) }}</td>
                <td></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>This is an automatically generated document. Please keep it for your records.</p>
    </div>
</body>
</html>