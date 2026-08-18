<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MeController;
use App\Http\Controllers\Auth\RefreshController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\GeoController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PromoController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\ShippingController;
use App\Http\Controllers\TradeController;
use App\Http\Controllers\TradeInController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\WishlistController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', RegisterController::class);
Route::post('/auth/login', LoginController::class);
Route::post('/auth/refresh', RefreshController::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', LogoutController::class);
    Route::get('/me', [MeController::class, 'show']);
    Route::patch('/me', [MeController::class, 'update']);
});

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/featured', [ProductController::class, 'featured']);
Route::get('/products/flash-deals', [ProductController::class, 'flashDeals']);
Route::get('/products/price-drops', [ProductController::class, 'priceDrops']);
Route::get('/products/{sku}', [ProductController::class, 'show']);
Route::get('/products/{sku}/related', [ProductController::class, 'related']);

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{name}/subcategories', [CategoryController::class, 'subcategories']);
Route::get('/brands', [BrandController::class, 'index']);

Route::get('/search/popular', [SearchController::class, 'popular']);

Route::get('/delivery/options', [OrderController::class, 'deliveryOptions']);
Route::get('/payment/methods', [OrderController::class, 'paymentMethods']);

Route::get('/orders/tracking/{orderNumber}', [OrderController::class, 'tracking']);

Route::post('/promo/validate', [PromoController::class, 'validate']);

// community (publik)
Route::get('/community/posts', [CommunityController::class, 'posts']);
Route::get('/community/posts/{post}', [CommunityController::class, 'show']);

// geo (publik)
Route::get('/geo/provinces', [GeoController::class, 'provinces']);
Route::get('/geo/provinces/{provinceId}/cities', [GeoController::class, 'cities']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/products', [ProductController::class, 'store']);
    Route::patch('/products/{sku}', [ProductController::class, 'update']);
    Route::put('/products/{sku}', [ProductController::class, 'update']);
    Route::delete('/products/{sku}', [ProductController::class, 'destroy']);

    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist', [WishlistController::class, 'store']);
    Route::get('/wishlist/check', [WishlistController::class, 'check']);
    Route::delete('/wishlist/{productId}', [WishlistController::class, 'destroy']);

    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{orderNumber}', [OrderController::class, 'show']);
    Route::post('/orders/{orderNumber}/pay', [OrderController::class, 'pay']);

    Route::post('/upload', [UploadController::class, 'store']);
    Route::post('/upload/multiple', [UploadController::class, 'storeMultiple']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);

    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::post('/conversations', [ConversationController::class, 'store']);
    Route::get('/conversations/{id}', [ConversationController::class, 'show']);
    Route::post('/conversations/{id}/messages', [ConversationController::class, 'sendMessage']);

    Route::get('/trade-ins', [TradeInController::class, 'index']);
    Route::post('/trade-ins', [TradeInController::class, 'store']);
    Route::get('/trade-ins/{id}', [TradeInController::class, 'show']);
    Route::patch('/trade-ins/{id}/accept', [TradeInController::class, 'accept']);
    Route::patch('/trade-ins/{id}/reject', [TradeInController::class, 'reject']);

    // Collection routes
    Route::get('/collection', [CollectionController::class, 'index']);
    Route::post('/collection', [CollectionController::class, 'store']);
    Route::get('/collection/{id}', [CollectionController::class, 'show']);
    Route::patch('/collection/{id}', [CollectionController::class, 'update']);
    Route::delete('/collection/{id}', [CollectionController::class, 'destroy']);
    Route::get('/collection/user/{userId}', [CollectionController::class, 'publicIndex']);

    // Trade routes (2-arah real)
    Route::get('/trades', [TradeController::class, 'index']);
    Route::post('/trades', [TradeController::class, 'store']);
    Route::get('/trades/{id}', [TradeController::class, 'show']);
    Route::patch('/trades/{id}/negotiate', [TradeController::class, 'negotiate']);
    Route::patch('/trades/{id}/agree', [TradeController::class, 'agree']);
    Route::patch('/trades/{id}/ship', [TradeController::class, 'ship']);
    Route::patch('/trades/{id}/confirm-received', [TradeController::class, 'confirmReceived']);
    Route::patch('/trades/{id}/cancel', [TradeController::class, 'cancel']);
    Route::patch('/trades/{id}/dispute', [TradeController::class, 'dispute']);
    Route::post('/trades/{id}/rate', [TradeController::class, 'rate']);

    // Transaction history (unified)
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::get('/transactions/{id}', [TransactionController::class, 'show']);
    Route::get('/transactions/export', [TransactionController::class, 'export']);

    // community (protected)
    Route::post('/community/posts', [CommunityController::class, 'storePost']);
    Route::post('/community/posts/{post}/comments', [CommunityController::class, 'addComment']);
    Route::post('/community/posts/{post}/like', [CommunityController::class, 'like']);

    // seller dashboard
    Route::get('/seller/stats', [SellerController::class, 'stats']);

    // shipping labels
    Route::post('/orders/{orderNumber}/shipping-label', [ShippingController::class, 'generateLabel']);

    // admin
    Route::get('/admin/stats', [AdminController::class, 'stats']);
    Route::patch('/admin/verify-seller/{userId}', [AdminController::class, 'verifySeller']);
    Route::patch('/admin/verify-product/{productId}', [AdminController::class, 'verifyProduct']);
});

Route::get('/test-cors', function () {
    return response()->json(['status' => 'ok']);
});

// admin dashboard (butuh auth + role admin)
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', \App\Http\Controllers\Admin\DashboardController::class);

    // Users
    Route::get('/users', [\App\Http\Controllers\Admin\AdminUserController::class, 'index']);
    Route::get('/users/{id}', [\App\Http\Controllers\Admin\AdminUserController::class, 'show']);
    Route::patch('/users/{id}', [\App\Http\Controllers\Admin\AdminUserController::class, 'update']);
    Route::delete('/users/{id}', [\App\Http\Controllers\Admin\AdminUserController::class, 'destroy']);

    // Products
    Route::get('/products', [\App\Http\Controllers\Admin\AdminProductController::class, 'index']);
    Route::get('/products/{id}', [\App\Http\Controllers\Admin\AdminProductController::class, 'show']);
    Route::patch('/products/{id}', [\App\Http\Controllers\Admin\AdminProductController::class, 'update']);
    Route::post('/products/bulk-verify', [\App\Http\Controllers\Admin\AdminProductController::class, 'bulkVerify']);
    Route::delete('/products/{id}', [\App\Http\Controllers\Admin\AdminProductController::class, 'destroy']);

    // Orders
    Route::get('/orders', [\App\Http\Controllers\Admin\AdminOrderController::class, 'index']);
    Route::get('/orders/{id}', [\App\Http\Controllers\Admin\AdminOrderController::class, 'show']);
    Route::patch('/orders/{id}', [\App\Http\Controllers\Admin\AdminOrderController::class, 'update']);
    Route::post('/orders/{id}/refund', [\App\Http\Controllers\Admin\AdminOrderController::class, 'refund']);
    Route::get('/orders/export', [\App\Http\Controllers\Admin\AdminOrderController::class, 'export']);

    // Trades
    Route::get('/trades', [\App\Http\Controllers\Admin\AdminTradeController::class, 'index']);
    Route::get('/trades/{id}', [\App\Http\Controllers\Admin\AdminTradeController::class, 'show']);
    Route::post('/trades/{id}/force-complete', [\App\Http\Controllers\Admin\AdminTradeController::class, 'forceComplete']);
    Route::post('/trades/{id}/force-cancel', [\App\Http\Controllers\Admin\AdminTradeController::class, 'forceCancel']);
    Route::post('/trades/{id}/resolve-dispute', [\App\Http\Controllers\Admin\AdminTradeController::class, 'resolveDispute']);
    Route::get('/trades/export', [\App\Http\Controllers\Admin\AdminTradeController::class, 'export']);

    // Analytics
    Route::get('/analytics/overview', [\App\Http\Controllers\Admin\AdminAnalyticsController::class, 'overview']);
    Route::get('/analytics/categories', [\App\Http\Controllers\Admin\AdminAnalyticsController::class, 'categories']);
    Route::get('/analytics/sellers', [\App\Http\Controllers\Admin\AdminAnalyticsController::class, 'sellers']);
    Route::get('/analytics/charts', [\App\Http\Controllers\Admin\AdminAnalyticsController::class, 'charts']);
    Route::get('/analytics/export', [\App\Http\Controllers\Admin\AdminAnalyticsController::class, 'export']);
});