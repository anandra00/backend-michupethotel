<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Booking</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .header { text-align: center; margin-bottom: 30px; }
        .total { font-weight: bold; margin-top: 20px; text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Laporan Reservasi Michu Pet Hotel</h2>
        <p>Dicetak pada: {{ now()->format('d M Y H:i') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Tipe</th>
                <th>Pelanggan</th>
                <th>Check In</th>
                <th>Check Out</th>
                <th>Status</th>
                <th>Total Harga</th>
            </tr>
        </thead>
        <tbody>
            @php $totalRevenue = 0; @endphp
            @foreach($bookings as $b)
                @if($b->payment_status === 'paid' && in_array($b->status, ['approved', 'checked_in', 'checked_out']))
                    @php $totalRevenue += $b->total_price; @endphp
                @endif
                <tr>
                    <td>#{{ $b->id }}</td>
                    <td>{{ $b->booking_type === 'board' ? 'Hotel' : 'Sitter' }}</td>
                    <td>{{ $b->user->name ?? 'N/A' }}</td>
                    <td>{{ $b->check_in }}</td>
                    <td>{{ $b->check_out }}</td>
                    <td>{{ ucfirst($b->status) }}</td>
                    <td>Rp {{ number_format($b->total_price, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total">
        <p>Total Pendapatan (Paid & Active): Rp {{ number_format($totalRevenue, 0, ',', '.') }}</p>
    </div>
</body>
</html>
