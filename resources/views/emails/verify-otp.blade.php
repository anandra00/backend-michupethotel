<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Verifikasi OTP Michu Pet Hotel</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f3f4f6; padding: 20px; }
        .container { max-width: 500px; margin: 0 auto; background-color: #ffffff; padding: 30px; border-radius: 8px; border: 4px solid #1E1E1E; box-shadow: 4px 4px 0px 0px #1E1E1E; }
        h2 { color: #1E1E1E; text-transform: uppercase; font-weight: 900; }
        p { color: #4b5563; font-size: 16px; line-height: 1.5; }
        .otp-box { background-color: #FFB5C6; border: 3px solid #1E1E1E; padding: 15px; font-size: 32px; font-weight: bold; text-align: center; letter-spacing: 10px; margin: 20px 0; border-radius: 8px; }
        .footer { font-size: 12px; color: #9ca3af; text-align: center; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Verifikasi Email Kamu 🐾</h2>
        <p>Halo,</p>
        <p>Terima kasih sudah mendaftar di Michu Pet Hotel! Untuk menyelesaikan proses pendaftaran, silakan masukkan kode OTP 6 digit di bawah ini:</p>
        
        <div class="otp-box">
            {{ $otp }}
        </div>

        <p>Kode ini berlaku selama <strong>15 menit</strong>. Jangan berikan kode ini kepada siapapun.</p>
        
        <p>Jika kamu tidak merasa mendaftar di Michu Pet Hotel, abaikan email ini.</p>
    </div>
    <div class="footer">
        &copy; {{ date('Y') }} Michu Pet Hotel. All rights reserved.
    </div>
</body>
</html>
