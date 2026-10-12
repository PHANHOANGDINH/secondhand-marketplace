<?php

use App\Http\Controllers\Api\V1\AddressController;
use App\Http\Controllers\Api\V1\AdminCategoryController;
use App\Http\Controllers\Api\V1\AdminDashboardController;
use App\Http\Controllers\Api\V1\AdminOrderController;
use App\Http\Controllers\Api\V1\AdminProductController;
use App\Http\Controllers\Api\V1\AdminReviewController;
use App\Http\Controllers\Api\V1\AdminShopController;
use App\Http\Controllers\Api\V1\AdminUserController;
use App\Http\Controllers\Api\V1\AuctionCheckoutController;
use App\Http\Controllers\Api\V1\AuctionController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductImageController;
use App\Http\Controllers\Api\V1\ProductVideoController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\PublicCategoryController;
use App\Http\Controllers\Api\V1\PublicProductController;
use App\Http\Controllers\Api\V1\PublicShopController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\SellerDashboardController;
use App\Http\Controllers\Api\V1\SellerInventoryController;
use App\Http\Controllers\Api\V1\SellerOrderController;
use App\Http\Controllers\Api\V1\SellerProductController;
use App\Http\Controllers\Api\V1\SellerShopController;
use App\Http\Controllers\Api\V1\VideoCommentController;
use App\Http\Controllers\Api\V1\WishlistController;
use App\Http\Controllers\Api\V1\ZaloPayController;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureSpaSession;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'Secondhand Marketplace API is running',
    ]);
});

Route::prefix('auth')->name('auth.')->group(function (): void {
    Route::middleware(EnsureSpaSession::class)->group(function (): void {
        Route::post('/forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:forgot-password')->name('forgot-password');
        Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:reset-password')->name('reset-password');
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register')->name('register');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login');
    });
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me'])->middleware(EnsureActiveAccount::class)->name('me');
        Route::post('/logout', [AuthController::class, 'logout'])->middleware(EnsureSpaSession::class)->name('logout');
    });
});

Route::middleware('throttle:public-read')->group(function (): void {
    Route::get('/shops', [PublicShopController::class, 'index']);
    Route::get('/shops/{slug}', [PublicShopController::class, 'show']);
    Route::get('/categories', [PublicCategoryController::class, 'index']);
    Route::get('/categories/{slug}', [PublicCategoryController::class, 'show']);
});
Route::middleware(['auth:sanctum', EnsureActiveAccount::class, EnsureSpaSession::class, 'throttle:domain'])->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::patch('/profile', [ProfileController::class, 'update']);
    Route::apiResource('/addresses', AddressController::class);
    Route::patch('/addresses/{address}/default', [AddressController::class, 'setDefault']);
    Route::post('/shops', [SellerShopController::class, 'store']);
    Route::get('/seller/shop', [SellerShopController::class, 'show']);
    Route::patch('/seller/shop', [SellerShopController::class, 'update']);
    Route::post('/seller/shop/submit', [SellerShopController::class, 'submit']);
    Route::prefix('admin')->middleware(EnsureAdmin::class)->group(function (): void {
        Route::get('/shops', [AdminShopController::class, 'index']);
        Route::get('/shops/{shop}', [AdminShopController::class, 'show'])->withTrashed();
        Route::patch('/shops/{shop}/status', [AdminShopController::class, 'updateStatus']);
        Route::get('/categories', [AdminCategoryController::class, 'index']);
        Route::post('/categories', [AdminCategoryController::class, 'store']);
        Route::patch('/categories/{category}', [AdminCategoryController::class, 'update']);
        Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy']);
    });
});

Route::middleware('throttle:public-read')->group(function (): void {
    Route::get('/products', [PublicProductController::class, 'index']);
    Route::get('/products/suggestions', [PublicProductController::class, 'suggestions']);
    Route::get('/products/{slug}', [PublicProductController::class, 'show']);
});
Route::middleware(['auth:sanctum', EnsureActiveAccount::class, EnsureSpaSession::class, 'throttle:domain'])->group(function (): void {
    Route::prefix('seller')->group(function (): void {
        Route::apiResource('products', SellerProductController::class);
        foreach (['submit', 'hide', 'withdraw', 'revise'] as $action) {
            Route::post('/products/{product}/'.$action, [SellerProductController::class, $action]);
        }
        Route::post('/products/{product}/images', [ProductImageController::class, 'store'])->middleware('throttle:upload');
        Route::patch('/products/{product}/images/reorder', [ProductImageController::class, 'reorder']);
        Route::patch('/products/{product}/images/{image}', [ProductImageController::class, 'update']);
        Route::delete('/products/{product}/images/{image}', [ProductImageController::class, 'destroy']);
    });
    Route::prefix('admin')->middleware(EnsureAdmin::class)->group(function (): void {
        Route::get('/products', [AdminProductController::class, 'index']);
        Route::get('/products/{product}', [AdminProductController::class, 'show'])->withTrashed();
        Route::patch('/products/{product}/status', [AdminProductController::class, 'updateStatus']);
    });
});

Route::middleware(['auth:sanctum', EnsureActiveAccount::class, EnsureSpaSession::class, 'throttle:domain'])->group(function (): void {
    Route::get('/cart', [CartController::class, 'show']);
    Route::post('/cart/items', [CartController::class, 'store']);
    Route::patch('/cart/items/{item}', [CartController::class, 'update']);
    Route::delete('/cart/items/{item}', [CartController::class, 'destroy']);
    Route::delete('/cart', [CartController::class, 'clear']);
});

Route::middleware(['auth:sanctum', EnsureActiveAccount::class, EnsureSpaSession::class, 'throttle:domain'])->group(function (): void {
    Route::post('/orders/{order}/payments/zalopay', [ZaloPayController::class, 'retry'])->middleware('throttle:payment');
    Route::post('/orders/{order}/payments/zalopay/query', [ZaloPayController::class, 'sync'])->middleware('throttle:payment');
    Route::post('/checkout', CheckoutController::class)->middleware('throttle:payment');
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::get('/orders/{order}/payments', [OrderController::class, 'payments']);
    Route::post('/orders/{order}/shop-orders/{shopOrder}/cancel', [OrderController::class, 'cancel']);
    Route::get('/seller/orders', [SellerOrderController::class, 'index']);
    Route::get('/seller/orders/{shopOrder}', [SellerOrderController::class, 'show']);
    Route::patch('/seller/orders/{shopOrder}/status', [SellerOrderController::class, 'updateStatus']);
});

Route::get('/products/{slug}/reviews', [ReviewController::class, 'index'])->middleware('throttle:public-read');
Route::middleware(['auth:sanctum', EnsureActiveAccount::class, EnsureSpaSession::class, 'throttle:domain'])->group(function (): void {
    Route::get('/products/{slug}/review-eligibility', [ReviewController::class, 'eligibility']);
    Route::post('/order-items/{orderItem}/review', [ReviewController::class, 'store']);
    Route::patch('/reviews/{review}', [ReviewController::class, 'update']);
});

Route::middleware(['auth:sanctum', EnsureActiveAccount::class, EnsureSpaSession::class, 'throttle:domain'])->group(function (): void {
    Route::get('/seller/inventory', [SellerInventoryController::class, 'index']);
    Route::get('/seller/action-center', [SellerInventoryController::class, 'summary']);
    Route::get('/seller/dashboard', [SellerDashboardController::class, 'show']);
    Route::get('/seller/dashboard/revenue', [SellerDashboardController::class, 'revenue']);
});

Route::prefix('admin')->middleware(['auth:sanctum', EnsureActiveAccount::class, EnsureSpaSession::class, EnsureAdmin::class, 'throttle:domain'])->group(function (): void {
    Route::get('/dashboard', AdminDashboardController::class);
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::get('/users/{user}', [AdminUserController::class, 'show'])->withTrashed();
    Route::patch('/users/{user}/status', [AdminUserController::class, 'updateStatus'])->withTrashed();
    Route::get('/reviews', [AdminReviewController::class, 'index']);
    Route::get('/reviews/{review}', [AdminReviewController::class, 'show']);
    Route::patch('/reviews/{review}/status', [AdminReviewController::class, 'updateStatus']);
    Route::get('/orders', [AdminOrderController::class, 'index']);
    Route::get('/orders/{order}', [AdminOrderController::class, 'show']);
});

Route::post('/payments/zalopay/callback', [ZaloPayController::class, 'callback'])->middleware('throttle:public-read');

Route::middleware(['auth:sanctum', EnsureActiveAccount::class, EnsureSpaSession::class, 'throttle:domain'])->group(function (): void {
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::post('/conversations', [ConversationController::class, 'store'])->middleware('throttle:messaging');
    Route::get('/conversations/{conversation}/messages', [ConversationController::class, 'messages']);
    Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'send'])->middleware('throttle:messaging');
    Route::post('/conversations/{conversation}/read', [ConversationController::class, 'read']);
});

Route::middleware('throttle:public-read')->group(function (): void {
    Route::get('/auctions', [AuctionController::class, 'index']);
    Route::get('/auctions/{auction}', [AuctionController::class, 'show']);
    Route::get('/auctions/{auction}/bids', [AuctionController::class, 'bids']);
});
Route::middleware(['auth:sanctum', EnsureActiveAccount::class, EnsureSpaSession::class, 'throttle:domain'])->group(function (): void {
    Route::get('/seller/auctions', [AuctionController::class, 'sellerIndex']);
    Route::post('/seller/auctions', [AuctionController::class, 'store']);
    Route::get('/seller/auctions/{auction}', [AuctionController::class, 'sellerShow']);
    Route::post('/seller/auctions/{auction}/cancel', [AuctionController::class, 'cancel']);
    Route::get('/auctions/{auction}/checkout', [AuctionCheckoutController::class, 'show']);
    Route::post('/auctions/{auction}/checkout', [AuctionCheckoutController::class, 'store'])->middleware('throttle:payment');
    Route::post('/auctions/{auction}/bids', [AuctionController::class, 'placeBid'])->middleware('throttle:auction-bid');
});

Route::middleware(['auth:sanctum', EnsureActiveAccount::class, EnsureSpaSession::class, 'throttle:domain'])->group(function (): void {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'count']);
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->whereUuid('notification');
});

Route::get('/products/{slug}/videos', [ProductVideoController::class, 'publicIndex'])->middleware('throttle:public-read');
Route::get('/videos/{video}/comments', [VideoCommentController::class, 'index'])->middleware('throttle:public-read');
Route::middleware(['auth:sanctum', EnsureActiveAccount::class, EnsureSpaSession::class, 'throttle:domain'])->group(function (): void {
    Route::post('/videos/{video}/comments', [VideoCommentController::class, 'store'])->middleware('throttle:video-comment');
    Route::patch('/video-comments/{comment}', [VideoCommentController::class, 'update']);
});
Route::middleware(['auth:sanctum', EnsureActiveAccount::class, EnsureSpaSession::class, 'throttle:domain'])->group(function (): void {
    Route::get('/seller/products/{product}/videos', [ProductVideoController::class, 'index']);
    Route::post('/seller/products/{product}/videos', [ProductVideoController::class, 'store'])->middleware('throttle:upload');
    Route::patch('/seller/products/{product}/videos/{video}', [ProductVideoController::class, 'update']);
    Route::delete('/seller/products/{product}/videos/{video}', [ProductVideoController::class, 'destroy']);
});

Route::middleware('throttle:public-read')->group(function (): void {
    Route::get('/videos', [ProductVideoController::class, 'discovery']);
    Route::get('/videos/{video}', [ProductVideoController::class, 'discoveryShow'])->whereNumber('video');
});

Route::middleware(['auth:sanctum', EnsureActiveAccount::class, EnsureSpaSession::class, 'throttle:domain'])->group(function (): void {
    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/products/{product}/wishlist', [WishlistController::class, 'store'])->whereNumber('product');
    Route::delete('/products/{product}/wishlist', [WishlistController::class, 'destroy'])->whereNumber('product');
});

Route::middleware(['auth:sanctum', EnsureActiveAccount::class, EnsureSpaSession::class, 'throttle:domain'])->group(function (): void {
    Route::post('/reports', [ReportController::class, 'store'])->middleware('throttle:reports');
    Route::get('/my-reports', [ReportController::class, 'index']);
    Route::get('/my-reports/{report}', [ReportController::class, 'show'])->whereNumber('report');
    Route::prefix('admin')->middleware(EnsureAdmin::class)->group(function (): void {
        Route::get('/reports', [ReportController::class, 'index']);
        Route::get('/reports/{report}', [ReportController::class, 'show'])->whereNumber('report');
        Route::patch('/reports/{report}/status', [ReportController::class, 'updateStatus'])->whereNumber('report');
    });
});
