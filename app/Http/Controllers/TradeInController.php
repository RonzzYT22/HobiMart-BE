<?php

namespace App\Http\Controllers;

use App\Models\TradeIn;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TradeInController extends Controller
{
    // daftar trade-in user
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $status = $request->query('status');

        $query = TradeIn::with(['product' => function ($q) {
            $q->with('seller:id,name,avatar');
        }])->where('user_id', $user->id)->orderByDesc('created_at');

        if ($status) {
            $query->where('status', $status);
        }

        $items = $query->get()->map(function ($t) {
            return [
                'id' => $t->id,
                'productId' => $t->product_id,
                'productName' => $t->product->name ?? null,
                'productImage' => $t->product->image ?? null,
                'sellerName' => $t->product->seller->name ?? null,
                'offerItemName' => $t->offer_item_name,
                'offerItemCondition' => $t->offer_item_condition,
                'offerDescription' => $t->offer_description,
                'offerImages' => $t->offer_images,
                'status' => $t->status,
                'note' => $t->note,
                'createdAt' => $t->created_at?->toISOString(),
            ];
        });

        return response()->json(['items' => $items]);
    }

    // ajukan trade-in
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'offer_item_name' => ['required', 'string', 'max:255'],
            'offer_item_condition' => ['required', 'string', 'in:Mint,Near Mint,Excellent,Good,Played'],
            'offer_description' => ['required', 'string', 'max:2000'],
            'offer_images' => ['nullable', 'array'],
            'offer_images.*' => ['string'],
        ]);

        $user = $request->user();
        $product = Product::findOrFail($request->product_id);

        // cek produk bisa ditrade
        if (! $product->trade_available) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_TRADEABLE',
                    'message' => 'Produk ini tidak tersedia untuk trade.',
                ],
            ], 422);
        }

        // tidak boleh trade produk sendiri
        if ($product->seller_id === $user->id) {
            return response()->json([
                'error' => [
                    'code' => 'OWN_PRODUCT',
                    'message' => 'Tidak bisa trade produk sendiri.',
                ],
            ], 422);
        }

        $tradeIn = TradeIn::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'offer_description' => $request->offer_description,
            'offer_item_name' => $request->offer_item_name,
            'offer_item_condition' => $request->offer_item_condition,
            'offer_images' => $request->offer_images,
            'status' => 'pending',
        ]);

        return response()->json([
            'id' => $tradeIn->id,
            'status' => $tradeIn->status,
        ], 201);
    }

    // detail trade-in
    public function show(Request $request, int $id): JsonResponse
    {
        $tradeIn = TradeIn::with(['product.seller:id,name,avatar'])->find($id);

        if (! $tradeIn) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Trade-in tidak ditemukan.'],
            ], 404);
        }

        return response()->json([
            'id' => $tradeIn->id,
            'productId' => $tradeIn->product_id,
            'productName' => $tradeIn->product->name ?? null,
            'productImage' => $tradeIn->product->image ?? null,
            'sellerName' => $tradeIn->product->seller->name ?? null,
            'offerItemName' => $tradeIn->offer_item_name,
            'offerItemCondition' => $tradeIn->offer_item_condition,
            'offerDescription' => $tradeIn->offer_description,
            'offerImages' => $tradeIn->offer_images,
            'status' => $tradeIn->status,
            'note' => $tradeIn->note,
            'createdAt' => $tradeIn->created_at?->toISOString(),
        ]);
    }

    // terima trade-in
    public function accept(Request $request, int $id): JsonResponse
    {
        $tradeIn = TradeIn::find($id);

        if (! $tradeIn) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Trade-in tidak ditemukan.'],
            ], 404);
        }

        if ($tradeIn->status !== 'pending') {
            return response()->json([
                'error' => ['code' => 'NOT_PENDING', 'message' => 'Trade-in sudah diproses.'],
            ], 422);
        }

        $tradeIn->update(['status' => 'accepted', 'note' => 'Trade diterima oleh seller.']);

        return response()->json(['status' => $tradeIn->status]);
    }

    // tolak trade-in
    public function reject(Request $request, int $id): JsonResponse
    {
        $tradeIn = TradeIn::find($id);

        if (! $tradeIn) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Trade-in tidak ditemukan.'],
            ], 404);
        }

        if ($tradeIn->status !== 'pending') {
            return response()->json([
                'error' => ['code' => 'NOT_PENDING', 'message' => 'Trade-in sudah diproses.'],
            ], 422);
        }

        $tradeIn->update([
            'status' => 'rejected',
            'note' => $request->input('note', 'Trade ditolak oleh seller.'),
        ]);

        return response()->json(['status' => $tradeIn->status]);
    }
}