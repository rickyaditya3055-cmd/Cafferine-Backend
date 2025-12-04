<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    /**
     * Generate QRIS Payment
     * Development Mode: Auto-generate QR Code
     * Production: Integrate dengan Xendit/Midtrans/FLIP
     */
    public function createQris(Request $request)
    {
        try {
            $validated = $request->validate([
                'order_id' => 'required|string',
                'amount' => 'required|numeric|min:0.01',
                'description' => 'nullable|string',
                'user_id' => 'nullable|integer',
            ]);

            // Generate unique reference
            $ref = 'QRIS-' . strtoupper(Str::random(10)) . '-' . time();
            
            // Save to database
            $payment = Payment::create([
                'reference' => $ref,
                'order_id' => $validated['order_id'],
                'amount' => $validated['amount'],
                'payment_method' => 'qris',
                'status' => 'pending',
                'user_id' => $validated['user_id'] ?? null,
                'description' => $validated['description'] ?? 'POS Payment',
                'expires_at' => now()->addMinutes(5), // 5 menit expired
            ]);

            // ====================================
            // DEVELOPMENT MODE: Auto-generate QR Code
            // ====================================
            $qrContent = "00020101021226670016ID.CO.QRIS.WWW0118QRIS-{$ref}0215ID10200000000003031565802ID5912CAFFERINE POS6008SURABAYA61056000162070703A016304";
            
            // Generate QR menggunakan Google Charts API
            $qrImageUrl = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=" . urlencode($qrContent);

            // Simpan QR string
            $payment->update([
                'qr_string' => $qrImageUrl,
            ]);

            Log::info('QRIS Payment Created', [
                'ref' => $ref,
                'order_id' => $validated['order_id'],
                'amount' => $validated['amount'],
            ]);

            return response()->json([
                'success' => true,
                'ref' => $ref,
                'qr_image' => $qrImageUrl,
                'amount' => $validated['amount'],
                'expires_at' => $payment->expires_at,
                'message' => 'QRIS generated successfully',
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'messages' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('QRIS Creation Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to create QRIS',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check QRIS Payment Status
     */
    public function checkQrisStatus($ref)
    {
        try {
            $payment = Payment::where('reference', $ref)->first();

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'error' => 'Payment not found'
                ], 404);
            }

            // Cek expired
            if (now()->greaterThan($payment->expires_at) && $payment->status === 'pending') {
                $payment->update(['status' => 'expired']);
            }

            // ====================================
            // DEVELOPMENT MODE: Auto-success setelah 10 detik (simulasi)
            // Untuk testing, uncomment baris berikut:
            // ====================================
            /*
            if ($payment->status === 'pending' && 
                now()->diffInSeconds($payment->created_at) >= 10) {
                $payment->update([
                    'status' => 'success',
                    'paid_at' => now(),
                ]);
            }
            */

            return response()->json([
                'success' => true,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'paid_at' => $payment->paid_at,
                'expires_at' => $payment->expires_at,
            ]);

        } catch (\Exception $e) {
            Log::error('Check QRIS Status Failed', [
                'ref' => $ref,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to check payment status',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Manual Payment Confirmation (untuk testing/development)
     * Endpoint ini untuk simulasi pembayaran berhasil
     */
    public function confirmPayment($ref)
    {
        try {
            $payment = Payment::where('reference', $ref)->first();

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'error' => 'Payment not found'
                ], 404);
            }

            if ($payment->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'error' => 'Payment already processed',
                    'status' => $payment->status,
                ], 400);
            }

            // Update status ke success
            $payment->update([
                'status' => 'success',
                'paid_at' => now(),
            ]);

            Log::info('Payment Confirmed', [
                'ref' => $ref,
                'order_id' => $payment->order_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment confirmed successfully',
                'payment' => $payment,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to confirm payment',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Webhook untuk terima notifikasi dari payment gateway
     * Contoh: Xendit akan hit endpoint ini saat payment berhasil
     */
    public function webhook(Request $request)
    {
        try {
            // Validasi signature dari payment gateway (implement sesuai provider)
            
            $externalId = $request->input('external_id'); // reference kita
            $status = $request->input('status'); // 'COMPLETED'
            
            $payment = Payment::where('reference', $externalId)->first();
            
            if ($payment && in_array($status, ['COMPLETED', 'PAID', 'SUCCESS'])) {
                $payment->update([
                    'status' => 'success',
                    'paid_at' => now(),
                ]);
                
                Log::info('Payment Webhook Received', [
                    'ref' => $externalId,
                    'status' => $status,
                ]);
            }
            
            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('Webhook Failed', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json(['success' => false], 500);
        }
    }

    /**
     * Get Payment History
     */
    public function getPaymentHistory(Request $request)
    {
        try {
            $query = Payment::query();

            // Filter by user
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by date range
            if ($request->has('from_date')) {
                $query->where('created_at', '>=', $request->from_date);
            }

            if ($request->has('to_date')) {
                $query->where('created_at', '<=', $request->to_date);
            }

            $payments = $query->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $payments,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch payment history',
            ], 500);
        }
    }
}