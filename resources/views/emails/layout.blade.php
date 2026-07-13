<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #f7f6f2; /* Soft warm background */
            margin: 0;
            padding: 0;
            color: #2c2c2c;
        }
        .container {
            max-width: 650px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 4px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border-top: 4px solid #C5A880; /* Gold/Champagne accent */
        }
        .header {
            background-color: #111111;
            color: #C5A880;
            padding: 40px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 400;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .content {
            padding: 50px 40px;
            line-height: 1.7;
            font-size: 15px;
        }
        .content h2 {
            color: #111111;
            font-size: 22px;
            font-weight: 500;
            margin-top: 0;
            margin-bottom: 25px;
            text-align: center;
        }
        .footer {
            background-color: #111111;
            color: #888888;
            padding: 30px 40px;
            text-align: center;
            font-size: 13px;
            line-height: 1.6;
        }
        .footer strong {
            color: #C5A880;
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: block;
            margin-bottom: 10px;
        }
        .footer p {
            margin: 4px 0;
        }
        .footer a {
            color: #C5A880;
            text-decoration: none;
        }
        .info-box {
            background-color: #faf9f5;
            border: 1px solid #eae5d9;
            padding: 25px;
            margin: 30px 0;
            border-radius: 2px;
        }
        .button {
            display: inline-block;
            background-color: #C5A880;
            color: #ffffff !important;
            padding: 14px 28px;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-top: 25px;
            border-radius: 2px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table th {
            text-align: left;
            padding: 12px 0;
            color: #666666;
            font-weight: 400;
            width: 35%;
            border-bottom: 1px solid #eae5d9;
            vertical-align: top;
        }
        .info-table td {
            padding: 12px 0;
            font-weight: 500;
            color: #111111;
            border-bottom: 1px solid #eae5d9;
        }
        .policy-box {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px dashed #cccccc;
            font-size: 13px;
            color: #666666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $restaurantName ?? 'RestaurantPOS' }}</h1>
        </div>
        
        <div class="content">
            @yield('content')
        </div>
        
        <div class="footer">
            <strong>{{ $restaurantName ?? 'RestaurantPOS' }}</strong>
            <p>Địa chỉ: 123 Food Street, Culinary District, TP.HCM</p>
            <p>Hotline: 1900 1234 56 | Email: support@restaurantpos.com</p>
            <p style="margin-top: 20px; font-size: 11px; opacity: 0.6;">Email này được gửi tự động từ hệ thống nhà hàng. Quý khách vui lòng không trả lời trực tiếp email này.</p>
        </div>
    </div>
</body>
</html>
