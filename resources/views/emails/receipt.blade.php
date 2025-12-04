<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #00f5ff 0%, #ff00ea 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9f9f9; padding: 30px; }
        .button { display: inline-block; padding: 12px 30px; background: #00f5ff; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Terima Kasih!</h1>
            <p>Pembayaran Anda telah berhasil</p>
        </div>
        <div class="content">
            <h2>Order #{{ $order->id }}</h2>
            <p>Hai {{ $order->user->name ?? 'Customer' }},</p>
            <p>Terima kasih telah berbelanja di CaffeRine. Pembayaran Anda sebesar <strong>${{ number_format($order->total, 2) }}</strong> telah kami terima.</p>
            <p>Struk pembayaran terlampir dalam email ini sebagai file PDF.</p>
            <p>Jika ada pertanyaan, jangan ragu untuk menghubungi kami.</p>
        </div>
        <div class="footer">
            <p>CaffeRine - Manyar Rejo 14, Surabaya<br>www.cafferine.com | @cafferine</p>
        </div>
    </div>
</body>
</html>