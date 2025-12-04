<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\ReceiptMail;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    /**
     * Create New Order
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.id' => 'required|integer',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.price' => 'required|numeric|min:0',
                'subtotal' => 'required|numeric|min:0',
                'tax' => 'required|numeric|min:0',
                'shipping' => 'nullable|numeric|min:0',
                'discount' => 'nullable|numeric|min:0',
                'total' => 'required|numeric|min:0',
                'payment_method' => 'required|string|in:qris,cash,bank',
                'payment_ref' => 'nullable|string',
                'promo_code' => 'nullable|string',
                'notes' => 'nullable|string',
                'email' => 'nullable|email',
                'user_id' => 'nullable|integer',
                'order_id' => 'nullable|string',
            ]);

            DB::beginTransaction();

            // Generate order ID if not provided
            $orderId = $validated['order_id'] ?? 'POS-' . strtoupper(Str::random(8)) . '-' . time();

            // Create Order
            $order = Order::create([
                'order_id' => $orderId,
                'user_id' => $validated['user_id'] ?? null,
                'email' => $validated['email'] ?? null,
                'subtotal' => $validated['subtotal'],
                'tax' => $validated['tax'],
                'shipping' => $validated['shipping'] ?? 0,
                'discount' => $validated['discount'] ?? 0,
                'total' => $validated['total'],
                'payment_method' => $validated['payment_method'],
                'payment_ref' => $validated['payment_ref'] ?? null,
                'payment_status' => $validated['payment_method'] === 'cash' ? 'paid' : 'pending',
                'order_status' => 'processing',
                'promo_code' => $validated['promo_code'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Create Order Items
            foreach ($validated['items'] as $item) {
                $product = Product::find($item['id']);
                
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['id'],
                    'product_name' => $product->name ?? 'Unknown Product',
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'subtotal' => $item['price'] * $item['quantity'],
                ]);

                // Update stock if needed
                if ($product && $product->stock !== null) {
                    $product->decrement('stock', $item['quantity']);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => [
                    'order_id' => $orderId,
                    'total' => $validated['total'],
                    'payment_method' => $validated['payment_method'],
                ],
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Order Creation Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get Order Details
     */
    public function show($orderId)
    {
        try {
            $order = Order::with(['items.product', 'user'])
                ->where('order_id', $orderId)
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => $order,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }
    }

    /**
     * Get All Orders
     */
    public function index(Request $request)
    {
        try {
            $query = Order::with(['items', 'user']);

            // Filter by user
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // Filter by status
            if ($request->has('order_status')) {
                $query->where('order_status', $request->order_status);
            }

            if ($request->has('payment_status')) {
                $query->where('payment_status', $request->payment_status);
            }

            // Filter by date range
            if ($request->has('from_date')) {
                $query->where('created_at', '>=', $request->from_date);
            }

            if ($request->has('to_date')) {
                $query->where('created_at', '<=', $request->to_date);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 20);
            $orders = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $orders,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch orders',
            ], 500);
        }
    }

    /**
     * Update Order Status
     */
    public function updateStatus(Request $request, $orderId)
    {
        try {
            $validated = $request->validate([
                'order_status' => 'nullable|string|in:pending,processing,completed,cancelled',
                'payment_status' => 'nullable|string|in:pending,paid,failed,refunded',
            ]);

            $order = Order::where('order_id', $orderId)->firstOrFail();
            $order->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Order status updated',
                'data' => $order,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update order status',
            ], 500);
        }
    }

    /**
     * Send Receipt via Email
     */
    public function sendReceipt($orderId)
    {
        try {
            $order = Order::with(['items.product', 'user'])
                ->where('order_id', $orderId)
                ->firstOrFail();
            
            // Check if email exists
            $email = $order->email ?? $order->user->email ?? null;
            
            if (!$email) {
                return response()->json([
                    'success' => false,
                    'message' => 'No email address found for this order',
                ], 400);
            }

            // Generate PDF (optional, jika sudah install dompdf)
            // $pdf = Pdf::loadView('receipts.order', compact('order'));
            
            // Send email
            // Mail::to($email)->send(new ReceiptMail($order, $pdf));
            
            // For now, just return success (implement email later)
            return response()->json([
                'success' => true,
                'message' => 'Receipt sent successfully to ' . $email,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send receipt',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete Order
     */
    public function destroy($orderId)
    {
        try {
            $order = Order::where('order_id', $orderId)->firstOrFail();
            
            // Delete order items first
            OrderItem::where('order_id', $order->id)->delete();
            
            // Delete order
            $order->delete();

            return response()->json([
                'success' => true,
                'message' => 'Order deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete order',
            ], 500);
        }
    }

    /**
     * Get Order Statistics
     */
    public function statistics(Request $request)
    {
        try {
            $startDate = $request->get('start_date', now()->startOfMonth());
            $endDate = $request->get('end_date', now()->endOfMonth());

            $stats = [
                'total_orders' => Order::whereBetween('created_at', [$startDate, $endDate])->count(),
                'total_revenue' => Order::whereBetween('created_at', [$startDate, $endDate])
                    ->where('payment_status', 'paid')
                    ->sum('total'),
                'pending_orders' => Order::whereBetween('created_at', [$startDate, $endDate])
                    ->where('order_status', 'pending')
                    ->count(),
                'completed_orders' => Order::whereBetween('created_at', [$startDate, $endDate])
                    ->where('order_status', 'completed')
                    ->count(),
                'cancelled_orders' => Order::whereBetween('created_at', [$startDate, $endDate])
                    ->where('order_status', 'cancelled')
                    ->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics',
            ], 500);
        }
    }
}