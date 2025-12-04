<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Struk Pembayaran</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px dashed #000; padding-bottom: 10px; }
        .info { margin-bottom: 20px; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th { background: #f0f0f0; padding: 8px; text-align: left; border-bottom: 2px solid #000; }
        td { padding: 8px; border-bottom: 1px solid #ddd; }
        .total { font-weight: bold; font-size: 14px; border-top: 2px solid #000; padding-top: 10px; }
        .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 2px dashed #000; font-size: 11px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>CaffeRine</h2>
        <p>Manyar Rejo 14, Surabaya<br>Telp: +62 969 420 333</p>
    </div>

    <div class="info">
        <div class="info-row"><strong>No. Order:</strong> <span>{{ $order->id }}</span></div>
        <div class="info-row"><strong>Tanggal:</strong> <span>{{ $order->created_at->format('d/m/Y H:i') }}</span></div>
        <div class="info-row"><strong>Kasir:</strong> <span>{{ $order->user->name ?? '-' }}</span></div>
        <div class="info-row"><strong>Metode:</strong> <span>{{ strtoupper($order->payment_method) }}</span></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th style="width: 50px; text-align: center;">Qty</th>
                <th style="width: 100px; text-align: right;">Harga</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
            <tr>
                <td>{{ $item->product->pro_name }}</td>
                <td style="text-align: center;">{{ $item->quantity }}</td>
                <td style="text-align: right;">${{ number_format($item->price * $item->quantity, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div style="text-align: right;">
        <div style="margin-bottom: 5px;">Subtotal: ${{ number_format($order->subtotal, 2) }}</div>
        <div style="margin-bottom: 5px;">Pajak: ${{ number_format($order->tax, 2) }}</div>
        <div class="total">TOTAL: ${{ number_format($order->total, 2) }}</div>
    </div>

    <div class="footer">
        <p><strong>✓ Pembayaran Berhasil</strong></p>
        <p>Terima kasih atas pembelian Anda!<br>Simpan struk ini sebagai bukti pembayaran</p>
        <p>www.cafferine.com | @cafferine</p>
    </div>
</body>
</html>