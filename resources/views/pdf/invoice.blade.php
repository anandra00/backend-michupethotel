<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice Booking BKG-{{ $booking->id }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1e1e1e;
            background-color: #ffffff;
            margin: 0;
            padding: 20px;
        }
        .invoice-box {
            border: 4px solid #1e1e1e;
            padding: 30px;
            box-shadow: 6px 6px 0px 0px #1e1e1e;
            background-color: #fff8e7; /* Neo-brutalist beige background */
        }
        .header-table {
            width: 100%;
            border-bottom: 4px solid #1e1e1e;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        .title {
            font-size: 28px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: -1px;
        }
        .subtitle {
            font-size: 12px;
            font-weight: bold;
            color: #555555;
            margin-top: 5px;
        }
        .info-table {
            width: 100%;
            margin-bottom: 30px;
        }
        .info-table td {
            vertical-align: top;
            font-size: 13px;
            line-height: 1.6;
        }
        .info-title {
            font-weight: 900;
            text-transform: uppercase;
            font-size: 11px;
            color: #777777;
            margin-bottom: 5px;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            border: 3px solid #1e1e1e;
            margin-bottom: 30px;
            background-color: #ffffff;
        }
        .details-table th {
            background-color: #ffd84d; /* Neo-brutalist yellow */
            border: 3px solid #1e1e1e;
            padding: 12px;
            font-weight: 900;
            text-align: left;
            text-transform: uppercase;
            font-size: 12px;
        }
        .details-table td {
            border: 3px solid #1e1e1e;
            padding: 12px;
            font-size: 13px;
            font-weight: bold;
        }
        .total-section {
            width: 100%;
            margin-top: 20px;
        }
        .total-box {
            float: right;
            width: 300px;
            border: 3px solid #1e1e1e;
            background-color: #ffffff;
            box-shadow: 4px 4px 0px 0px #1e1e1e;
        }
        .total-row {
            width: 100%;
            border-bottom: 2px solid #1e1e1e;
        }
        .total-row:last-child {
            border-bottom: none;
            background-color: #ffd84d;
        }
        .total-row td {
            padding: 10px 15px;
            font-size: 13px;
            font-weight: bold;
        }
        .total-row .label {
            text-align: left;
        }
        .total-row .val {
            text-align: right;
            font-weight: 900;
        }
        .badge-paid {
            display: inline-block;
            border: 3px solid #1e1e1e;
            background-color: #55ec8c; /* Neo-brutalist green */
            color: #1e1e1e;
            padding: 10px 20px;
            font-weight: 900;
            text-transform: uppercase;
            font-size: 16px;
            margin-top: 15px;
            box-shadow: 3px 3px 0px 0px #1e1e1e;
        }
        .footer-note {
            margin-top: 150px;
            border-top: 3px dashed #1e1e1e;
            padding-top: 15px;
            text-align: center;
            font-size: 11px;
            font-weight: bold;
            color: #666666;
        }
    </style>
</head>
<body>

<div class="invoice-box">
    <table class="header-table">
        <tr>
            <td style="text-align: left;">
                <div class="title">Michu MeowStay</div>
                <div class="subtitle">Premium Cat Boarding & Sitter Services</div>
            </td>
            <td style="text-align: right; font-weight: 900; font-size: 18px; text-transform: uppercase;">
                Invoice #BKG-{{ $booking->id }}
            </td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td style="width: 50%;">
                <div class="info-title">Diterbitkan Untuk:</div>
                <strong>{{ $booking->user->name }}</strong><br>
                Email: {{ $booking->user->email }}<br>
                HP: {{ $booking->user->phone ?? '-' }}<br>
                Alamat: {{ $booking->user->address ?? '-' }}
            </td>
            <td style="width: 50%; text-align: right;">
                <div class="info-title">Detail Pembayaran:</div>
                Tanggal Booking: {{ $booking->created_at->format('d M Y H:i') }}<br>
                Order ID: {{ $booking->midtrans_order_id ?? '-' }}<br>
                Metode Pembayaran: Midtrans SNAP Gateway<br>
                <div class="badge-paid">🟢 LUNAS / PAID</div>
            </td>
        </tr>
    </table>

    <table class="details-table">
        <thead>
            <tr>
                <th>Layanan / Item</th>
                <th>Detail Jadwal</th>
                <th>Kucing</th>
                <th>Harga</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong>
                        {{ $booking->booking_type === 'sitter' ? 'Cat Sitter Visit' : 'Cat Hotel Boarding' }}
                    </strong>
                    <div style="font-size: 11px; font-weight: normal; margin-top: 4px; color: #555555;">
                        @if($booking->booking_type === 'board')
                            Kamar: {{ $booking->room->name }} ({{ $booking->room->type }})
                        @else
                            Paket Visit: {{ $booking->sitter_package === '2x' ? '2x Visit per Hari' : '1x Visit per Hari' }}<br>
                            Sitter: {{ $booking->sitter->name ?? '-' }}
                        @endif
                    </div>
                </td>
                <td>
                    {{ date('d M Y', strtotime($booking->check_in)) }} - {{ date('d M Y', strtotime($booking->check_out)) }}
                    <div style="font-size: 11px; font-weight: normal; margin-top: 4px; color: #555555;">
                        Durasi: 
                        @if($booking->booking_type === 'board')
                            {{ max(1, (new \DateTime($booking->check_in))->diff(new \DateTime($booking->check_out))->days) }} malam
                        @else
                            {{ max(1, (new \DateTime($booking->check_in))->diff(new \DateTime($booking->check_out))->days) }} hari
                        @endif
                    </div>
                </td>
                <td>
                    @if($booking->cats && $booking->cats->count() > 0)
                        {{ $booking->cats->pluck('name')->join(', ') }}
                    @else
                        {{ $booking->total_cats }} Ekor
                    @endif
                </td>
                <td>
                    Rp {{ number_format($booking->total_price + $booking->discount_amount, 0, ',', '.') }}
                </td>
            </tr>
        </tbody>
    </table>

    <div class="total-section">
        <table class="total-box" cellspacing="0">
            <tr class="total-row">
                <td class="label">Subtotal</td>
                <td class="val">Rp {{ number_format($booking->total_price + $booking->discount_amount, 0, ',', '.') }}</td>
            </tr>
            @if($booking->discount_amount > 0)
                <tr class="total-row">
                    <td class="label">Diskon (Promo)</td>
                    <td class="val" style="color: #ff4a4a;">- Rp {{ number_format($booking->discount_amount, 0, ',', '.') }}</td>
                </tr>
            @endif
            <tr class="total-row">
                <td class="label" style="font-size: 15px; font-weight: 900; text-transform: uppercase;">Total Bayar</td>
                <td class="val" style="font-size: 15px; font-weight: 900;">Rp {{ number_format($booking->total_price, 0, ',', '.') }}</td>
            </tr>
        </table>
        <div style="clear: both;"></div>
    </div>

    <div class="footer-note">
        Terima kasih telah memercayakan anabul kesayangan Anda pada Michu MeowStay!<br>
        Dokumen ini sah dan diterbitkan secara digital oleh sistem pembayaran terintegrasi Michu MeowStay.
    </div>
</div>

</body>
</html>
