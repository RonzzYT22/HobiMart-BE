<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    // buat pesanan baru dari cart
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'shipping_address' => ['required', 'array'],
            'shipping_address.fullName' => ['required', 'string', 'max:255'],
            'shipping_address.phone' => ['required', 'string', 'max:20'],
            'shipping_address.address1' => ['required', 'string', 'max:500'],
            'shipping_address.address2' => ['nullable', 'string', 'max:500'],
            'shipping_address.city' => ['required', 'string', 'max:100'],
            'shipping_address.province' => ['required', 'string', 'max:100'],
            'shipping_address.postalCode' => ['required', 'string', 'max:10'],
            'delivery' => ['required', 'in:regular,express,sameday'],
            'payment_method' => ['required', 'in:qris,bank,ewallet,cc'],
        ]);

        $user = $request->user();
        $items = $request->input('items');

        $subtotal = 0;
        $orderItems = [];
        $productIds = array_column($items, 'product_id');
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        foreach ($items as $item) {
            $product = $products[$item['product_id']] ?? null;
            if (! $product) {
                return response()->json([
                    'error' => [
                        'code' => 'PRODUCT_NOT_FOUND',
                        'message' => 'Produk dengan ID ' . $item['product_id'] . ' tidak ditemukan.',
                    ],
                ], 422);
            }
            $lineTotal = $product->price * $item['quantity'];
            $subtotal += $lineTotal;

            $orderItems[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'quantity' => $item['quantity'],
                'price' => $product->price,
                'image' => $product->image,
            ];
        }

        $delivery = $request->input('delivery');
        $shipping = match ($delivery) {
            'express' => 45000,
            'sameday' => 80000,
            default => 20000,
        };

        $total = $subtotal + $shipping;

        // generate nomor pesanan
        $today = now()->format('Ymd');
        $lastToday = Order::where('order_number', 'like', "HM-ORD-{$today}-%")
            ->orderByDesc('id')
            ->first();
        $num = $lastToday ? ((int) substr($lastToday->order_number, -4)) + 1 : 1;
        $orderNumber = 'HM-ORD-' . $today . '-' . str_pad((string) $num, 4, '0', STR_PAD_LEFT);

        $timeline = [
            [
                'status' => 'Placed',
                'label' => 'Pesanan dibuat',
                'time' => now()->toISOString(),
            ],
        ];

        $estDays = match ($delivery) {
            'express' => 2,
            'sameday' => 1,
            default => 5,
        };
        $estArrival = now()->addDays($estDays);

        $order = Order::create([
            'order_number' => $orderNumber,
            'user_id' => $user->id,
            'status' => 'Placed',
            'items' => $orderItems,
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'total' => $total,
            'shipping_address' => $request->input('shipping_address'),
            'payment_method' => $request->input('payment_method'),
            'payment_status' => 'Unpaid',
            'courier' => $delivery,
            'est_arrival' => $estArrival,
            'timeline' => $timeline,
        ]);

        foreach ($orderItems as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['product_id'],
                'name' => $item['name'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'image' => $item['image'],
            ]);
        }

        $paymentInstructions = $this->generatePaymentInstructions($order);

        return response()->json([
            'order' => [
                'id' => $order->id,
                'orderNumber' => $order->order_number,
                'status' => $order->status,
                'subtotal' => (int) $order->subtotal,
                'shipping' => (int) $order->shipping,
                'total' => (int) $order->total,
                'paymentMethod' => $order->payment_method,
                'paymentStatus' => $order->payment_status,
                'delivery' => $order->courier,
                'estArrival' => $order->est_arrival?->format('Y-m-d'),
                'timeline' => $order->timeline,
                'createdAt' => $order->created_at?->toISOString(),
            ],
            'payment' => $paymentInstructions,
        ], 201);
    }

    // daftar pesanan user
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $orders = Order::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'orderNumber' => $order->order_number,
                    'status' => $order->status,
                    'total' => (int) $order->total,
                    'paymentMethod' => $order->payment_method,
                    'paymentStatus' => $order->payment_status,
                    'delivery' => $order->courier,
                    'estArrival' => $order->est_arrival?->format('Y-m-d'),
                    'itemCount' => count($order->items ?? []),
                    'timeline' => $order->timeline,
                    'createdAt' => $order->created_at?->toISOString(),
                ];
            });

        return response()->json(['items' => $orders]);
    }

    // detail pesanan
    public function show(Request $request, string $orderNumber): JsonResponse
    {
        $order = Order::with('items.product')->where('order_number', $orderNumber)->first();

        if (! $order) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Pesanan tidak ditemukan.',
                ],
            ], 404);
        }

        return response()->json([
            'id' => $order->id,
            'orderNumber' => $order->order_number,
            'status' => $order->status,
            'items' => $order->items,
            'subtotal' => (int) $order->subtotal,
            'shipping' => (int) $order->shipping,
            'total' => (int) $order->total,
            'shippingAddress' => $order->shipping_address,
            'paymentMethod' => $order->payment_method,
            'paymentStatus' => $order->payment_status,
            'delivery' => $order->courier,
            'trackingNumber' => $order->tracking_number,
            'estArrival' => $order->est_arrival?->format('Y-m-d'),
            'timeline' => $order->timeline,
            'createdAt' => $order->created_at?->toISOString(),
        ]);
    }

    // dummy payment confirmation
    public function pay(Request $request, string $orderNumber): JsonResponse
    {
        $order = Order::where('order_number', $orderNumber)->first();

        if (! $order) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Pesanan tidak ditemukan.',
                ],
            ], 404);
        }

        if ($order->payment_status === 'Paid') {
            return response()->json([
                'error' => [
                    'code' => 'ALREADY_PAID',
                    'message' => 'Pesanan ini sudah dibayar.',
                ],
            ], 422);
        }

        $timeline = $order->timeline ?? [];
        $timeline[] = [
            'status' => 'Paid',
            'label' => 'Pembayaran diterima',
            'time' => now()->toISOString(),
        ];

        $order->update([
            'payment_status' => 'Paid',
            'status' => 'Processing',
            'timeline' => $timeline,
        ]);

        return response()->json([
            'paid' => true,
            'orderNumber' => $order->order_number,
            'status' => $order->status,
            'paymentStatus' => $order->payment_status,
        ]);
    }

    // tracking pesanan (publik)
    public function tracking(string $orderNumber): JsonResponse
    {
        $order = Order::where('order_number', $orderNumber)->first();

        if (! $order) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Pesanan tidak ditemukan.',
                ],
            ], 404);
        }

        return response()->json([
            'orderNumber' => $order->order_number,
            'status' => $order->status,
            'delivery' => $order->courier,
            'trackingNumber' => $order->tracking_number,
            'estArrival' => $order->est_arrival?->format('Y-m-d'),
            'shippingAddress' => $order->shipping_address,
            'timeline' => $order->timeline,
        ]);
    }

    // daftar opsi pengiriman
    public function deliveryOptions(): JsonResponse
    {
        return response()->json([
            'options' => [
                [
                    'id' => 'regular',
                    'label' => 'Regular',
                    'price' => 20000,
                    'days' => '3-5 hari',
                    'desc' => 'Pengiriman standar via JNE / J&T',
                ],
                [
                    'id' => 'express',
                    'label' => 'Express',
                    'price' => 45000,
                    'days' => '1-2 hari',
                    'desc' => 'Pengiriman cepat via JNE YES / SiCepat BEST',
                ],
                [
                    'id' => 'sameday',
                    'label' => 'Same Day',
                    'price' => 80000,
                    'days' => 'Hari ini',
                    'desc' => 'Pengiriman hari yang sama via GoSend / GrabExpress',
                ],
            ],
        ]);
    }

    // daftar metode pembayaran
    public function paymentMethods(): JsonResponse
    {
        return response()->json([
            'methods' => [
                [
                    'id' => 'qris',
                    'label' => 'QRIS',
                    'desc' => 'Scan & bayar pakai aplikasi apa saja',
                    'badge' => null,
                ],
                [
                    'id' => 'bank',
                    'label' => 'Bank Transfer',
                    'desc' => 'BCA / BRI / Mandiri',
                    'badge' => null,
                ],
                [
                    'id' => 'ewallet',
                    'label' => 'E-Wallet',
                    'desc' => 'GoPay / OVO / Dana',
                    'badge' => null,
                ],
                [
                    'id' => 'cc',
                    'label' => 'Credit Card',
                    'desc' => 'Visa, Mastercard, JCB',
                    'badge' => 'Popular',
                ],
            ],
        ]);
    }

    // generate dummy payment instructions
    protected function generatePaymentInstructions(Order $order): array
    {
        $method = $order->payment_method;

        $instructions = match ($method) {
            'qris' => [
                'method' => 'QRIS',
                'icon' => 'qr',
                'qrCode' => 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=DUMMY' . $order->order_number,
                'instructions' => 'Scan QR code menggunakan aplikasi e-wallet atau mobile banking kamu.',
                'amount' => (int) $order->total,
            ],
            'bank' => [
                'method' => 'Bank Transfer',
                'icon' => 'bank',
                'bank' => 'BCA',
                'accountNumber' => '1234567890',
                'accountName' => 'HobiMart Official',
                'instructions' => 'Transfer ke rekening di atas dan simpan bukti transfer.',
                'amount' => (int) $order->total,
            ],
            'ewallet' => [
                'method' => 'E-Wallet',
                'icon' => 'wallet',
                'wallet' => 'GoPay',
                'phoneNumber' => '081234567890',
                'instructions' => 'Kirim saldo ke nomor GoPay di atas.',
                'amount' => (int) $order->total,
            ],
            'cc' => [
                'method' => 'Credit Card',
                'icon' => 'card',
                'instructions' => 'Masukkan detail kartu kredit kamu di halaman pembayaran.',
                'amount' => (int) $order->total,
            ],
            default => [
                'method' => 'Unknown',
                'instructions' => 'Silakan lakukan pembayaran.',
                'amount' => (int) $order->total,
            ],
        };

        $instructions['status'] = 'Unpaid';
        $instructions['deadline'] = now()->addHours(24)->toISOString();

        return $instructions;
    }
}