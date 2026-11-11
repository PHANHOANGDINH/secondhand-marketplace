<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Address;
use App\Models\Auction;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductView;
use App\Models\SearchLog;
use App\Models\Shop;
use App\Models\User;
use App\Models\Voucher;
use App\Models\Wishlist;
use App\Services\AddressService;
use App\Services\AuctionCheckoutService;
use App\Services\AuctionService;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\MessagingService;
use App\Services\ProductVideoService;
use App\Services\ReportService;
use App\Services\ReviewService;
use App\Services\ShopOrderService;
use App\Services\VideoCommentService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Throwable;

/**
 * The full marketplace data set for the development database: categories, people, the catalogue of
 * MarketplaceCatalogSeeder and everyday activity on top (orders, reviews, auctions, vouchers, chats,
 * wishlists, a report, product videos). Everything reads like a real marketplace; nothing is labelled
 * as sample data. Activity is written through the domain services, so every rule still applies.
 * A second run adds nothing.
 */
class MarketplaceSeeder extends Seeder
{
    private const DOMAIN = 'passlai.local';

    // name, province code, street, ward (two-level addresses since 2025-07-01).
    private const BUYERS = [
        ['Lê Thanh Hương', '79', '12 Nguyễn Trãi', 'Phường Bến Thành'],
        ['Nguyễn Hoàng Việt', '01', '25 Phố Huế', 'Phường Hai Bà Trưng'],
        ['Trần Bảo Ngọc', '48', '88 Bạch Đằng', 'Phường Hải Châu'],
        ['Phạm Quang Khải', '92', '14 Đường 30 Tháng 4', 'Phường Ninh Kiều'],
        ['Võ Thị Mỹ Duyên', '56', '3 Trần Phú', 'Phường Nha Trang'],
        ['Đinh Công Thành', '31', '40 Lạch Tray', 'Phường Gia Viên'],
        ['Huỳnh Gia Hân', '75', '102 Phạm Văn Thuận', 'Phường Trấn Biên'],
        ['Kiều Minh Khoa', '46', '7 Lê Lợi', 'Phường Thuận Hóa'],
    ];

    private const REVIEWS = [
        [5, 'Máy đúng như mô tả, pin còn tốt. Shop đóng gói kỹ, giao nhanh.'],
        [5, 'Hàng đẹp hơn ảnh, người bán nhiệt tình tư vấn.'],
        [4, 'Có vài vết xước nhỏ như shop đã báo trước, dùng vẫn ổn.'],
        [5, 'Giao dịch nhanh gọn, sẽ ủng hộ shop tiếp.'],
        [5, 'Thắng phiên đấu giá với giá hời, nhận hàng đúng hẹn.'],
    ];

    /** @var array<int, User> */
    private array $buyers = [];

    /** @var array<int, Address> */
    private array $addresses = [];

    private User $admin;

    private array $used = [];

    private array $files = [];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || ! in_array(DB::connection()->getDatabaseName(), ['secondhand_marketplace', 'secondhand_marketplace_test'], true)) {
            throw new RuntimeException('Marketplace seed is only allowed in the project local/test databases.');
        }
        $password = config('demo.password');
        if (! is_string($password) || strlen($password) < 12) {
            throw new RuntimeException('Configure a private DEMO_PASSWORD of at least 12 characters before seeding.');
        }
        $this->call([CategorySeeder::class, MarketplaceCatalogSeeder::class]);
        if (Order::where('checkout_key', $this->key('order-pending'))->exists()) {
            $admin = User::where('email', 'admin@'.self::DOMAIN)->first();
            if ($admin) {
                DB::transaction(fn () => $this->platformVoucher($admin));
            }
            $this->command?->info('Marketplace activity already present; nothing added.');

            return;
        }
        $clock = [Carbon::getTestNow(), CarbonImmutable::getTestNow()];
        try {
            DB::transaction(function () use ($password): void {
                $this->people($password);
                $this->vouchers();
                $this->auctions();
                $this->orders();
                $this->engagement();
                $this->videos();
                $this->reconciliation();
            });
        } catch (Throwable $exception) {
            foreach ($this->files as [$path, $productId]) {
                app(ProductVideoService::class)->cleanup($path, $productId);
            }
            throw $exception;
        } finally {
            Carbon::setTestNow($clock[0]);
            CarbonImmutable::setTestNow($clock[1]);
        }
        $this->command?->info('Marketplace activity added: orders, reviews, auctions, vouchers, chats and wishlists.');
    }

    private function email(string $name): string
    {
        return Str::slug($name, '.').'@'.self::DOMAIN;
    }

    private function key(string $name): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'secondhand-marketplace/marketplace-seed/'.$name)->toString();
    }

    private function account(string $name, string $email, UserRole $role, string $password): User
    {
        $user = User::withTrashed()->where('email', $email)->first();
        if (! $user) {
            $user = new User;
            $user->forceFill(['name' => $name, 'email' => $email, 'password' => $password, 'email_verified_at' => now(), 'role' => $role, 'status' => UserStatus::ACTIVE])->save();
        }

        return $user;
    }

    private function people(string $password): void
    {
        $this->admin = $this->account('Ban quản trị Passdi', 'admin@'.self::DOMAIN, UserRole::ADMIN, $password);
        foreach (self::BUYERS as $index => [$name, $province, $street, $ward]) {
            $buyer = $this->account($name, $this->email($name), UserRole::USER, $password);
            $this->buyers[$index] = $buyer;
            $this->addresses[$index] = $buyer->addresses()->first() ?? app(AddressService::class)->create($buyer, [
                'recipient_name' => $name, 'phone' => '0903'.str_pad((string) (215407 + $index * 1371), 6, '0', STR_PAD_LEFT),
                'address_line' => $street, 'ward' => $ward, 'district' => null, 'province' => config('provinces.list.'.$province),
                'country_code' => 'VN', 'is_default' => true,
            ]);
        }
    }

    /** An unused live listing of the catalogue, optionally from one category (top-level or not) and listed for some days. */
    private function pick(?string $parent = null, int $listedDaysAgo = 0, int $minStock = 1): Product
    {
        $product = Product::query()->where('slug', 'like', 'vn-%')->where('status', 'ACTIVE')->where('stock_quantity', '>=', $minStock)
            ->where('published_at', '<=', now()->subDays($listedDaysAgo))->whereNotIn('id', $this->used)
            ->when($parent, fn ($query) => $query->whereHas('category', fn ($category) => $category->where('slug', $parent)->orWhereHas('parent', fn ($top) => $top->where('slug', $parent))))
            ->whereDoesntHave('auctionClaim')->orderBy('id')->first();
        if (! $product) {
            throw new RuntimeException("No listing available for the marketplace activity ({$parent}).");
        }
        $this->used[] = $product->id;

        return $product;
    }

    /** The platform code follows the brand: the old PASSLAI10 is kept (orders may reference it) but switched off. */
    private function platformVoucher(User $admin): void
    {
        Voucher::where('code', 'PASSLAI10')->where('status', Voucher::ACTIVE)->update(['status' => Voucher::INACTIVE]);
        Voucher::firstOrCreate(['code' => 'PASSDI10'], ['scope' => Voucher::PLATFORM, 'type' => Voucher::PERCENT, 'value' => '10.00', 'max_discount' => '50000.00', 'min_order' => '200000.00', 'starts_at' => now()->subDay(), 'ends_at' => now()->addYear(), 'status' => Voucher::ACTIVE, 'created_by' => $admin->id]);
    }

    private function vouchers(): void
    {
        $this->platformVoucher($this->admin);
        $shop = Shop::where('slug', 'vn-goc-tao-cu')->firstOrFail();
        Voucher::firstOrCreate(['code' => 'GOCTAO50K'], ['scope' => Voucher::SHOP, 'shop_id' => $shop->id, 'type' => Voucher::FIXED, 'value' => '50000.00', 'min_order' => '1000000.00', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonths(3), 'status' => Voucher::ACTIVE, 'created_by' => $shop->owner_id]);
        $style = Shop::where('slug', 'vn-tu-do-secondhand')->firstOrFail();
        Voucher::firstOrCreate(['code' => 'TUDO10'], ['scope' => Voucher::SHOP, 'shop_id' => $style->id, 'type' => Voucher::PERCENT, 'value' => '10.00', 'max_discount' => '30000.00', 'min_order' => '150000.00', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonths(2), 'status' => Voucher::ACTIVE, 'created_by' => $style->owner_id]);
    }

    private function auction(Product $product, CarbonImmutable $start, CarbonImmutable $end): Auction
    {
        $start_price = (string) max(50000, intdiv((int) ($product->price * 0.5), 10000) * 10000);
        $increment = (string) max(10000, intdiv((int) ($product->price * 0.02), 10000) * 10000);

        return app(AuctionService::class)->create($product->shop->owner, ['product_id' => $product->id, 'start_price' => $start_price, 'bid_increment' => $increment, 'starts_at' => $start->toISOString(), 'ends_at' => $end->toISOString()]);
    }

    private function bid(Auction $auction, array $buyerIndexes): Auction
    {
        foreach ($buyerIndexes as $step => $index) {
            $auction->refresh();
            $amount = $step === 0 ? $auction->start_price : bcadd($auction->current_price, $auction->bid_increment, 2);
            app(AuctionService::class)->placeBid($this->buyers[$index], $auction, $amount);
        }

        return $auction->refresh();
    }

    private function travel(CarbonImmutable $to): void
    {
        Carbon::setTestNow($to);
        CarbonImmutable::setTestNow($to);
    }

    private function auctions(): void
    {
        $present = CarbonImmutable::now();
        $this->auction($this->pick('nhac-cu'), $present->addHours(3), $present->addDays(7));
        $this->bid($this->auction($this->pick('dien-thoai'), $present, $present->addDays(2)->addHours(5)), [0, 1, 0, 1]);
        $this->bid($this->auction($this->pick('bong-vot'), $present, $present->addDays(3)), [2, 3]);

        // Two auctions that ran last week: one won and paid, one without bids.
        $past = $present->subDays(4);
        $this->travel($past);
        $won = $this->bid($this->auction($this->pick('xe-co', 6), $past, $past->addDays(3)), [3, 2, 3]);
        $quiet = $this->auction($this->pick('giuong-tu', 6), $past, $past->addDays(3));
        $this->travel($past->addDays(3)->addMinute());
        $won = app(AuctionService::class)->finalize($won);
        app(AuctionService::class)->finalize($quiet);
        $order = app(AuctionCheckoutService::class)->checkout($this->buyers[3], $won, $this->addresses[3]->id, $this->key('auction-won'))['order'];
        $this->travel($past->addDays(3)->addHours(6));
        $this->fulfill($order, 'COMPLETED');
        $this->review($this->buyers[3], $order, self::REVIEWS[4]);
        $this->travel($present);
    }

    private function fulfill(Order $order, string $target): void
    {
        if ($target === 'PENDING') {
            return;
        }
        $part = $order->shopOrders()->with('shop.owner')->sole();
        foreach (['CONFIRMED', 'PROCESSING', 'SHIPPING', 'COMPLETED'] as $status) {
            $part = app(ShopOrderService::class)->transition($part->shop->owner, $part, ['expected_status' => $part->status->value, 'status' => $status, 'cod_collected' => $status === 'COMPLETED']);
            if ($target === $status) {
                break;
            }
        }
    }

    private function review(User $buyer, Order $order, array $review): void
    {
        [$rating, $comment] = $review;
        app(ReviewService::class)->create($buyer, $order->shopOrders()->sole()->orderItems()->sole(), ['rating' => $rating, 'comment' => $comment]);
    }

    private function orders(): void
    {
        $present = CarbonImmutable::now();
        $plan = [
            // key, buyer, category, days ago, final status, review
            ['completed-1', 0, 'dong-ho-thong-minh', 9, 'COMPLETED', 0],
            ['completed-2', 1, 'thoi-trang', 7, 'COMPLETED', 1],
            ['completed-3', 2, 'gia-dung', 6, 'COMPLETED', 2],
            ['completed-4', 7, 'sach-hoc-tap', 5, 'COMPLETED', 3],
            ['shipping', 6, 'me-va-be', 2, 'SHIPPING', null],
            ['confirmed', 5, 'dien-lanh', 1, 'CONFIRMED', null],
            ['order-pending', 4, 'thu-cung', 0, 'PENDING', null],
        ];
        foreach ($plan as [$key, $index, $parent, $daysAgo, $status, $review]) {
            $product = $this->pick($parent, $daysAgo + 1);
            $this->travel($present->subDays($daysAgo)->subHours(3));
            app(CartService::class)->add($this->buyers[$index], $product->id, 1);
            $order = app(CheckoutService::class)->checkout($this->buyers[$index], $this->addresses[$index]->id, $this->key($key), PaymentMethod::COD)['order'];
            $this->fulfill($order, $status);
            if ($review !== null) {
                $this->review($this->buyers[$index], $order, self::REVIEWS[$review]);
            }
        }
        $this->travel($present);
    }

    private function engagement(): void
    {
        foreach ([0 => ['dien-tu', 'xe-co'], 1 => ['den', 'nhac-cu'], 2 => ['me-va-be', 'thoi-trang'], 3 => ['the-thao', 'van-phong']] as $index => $parents) {
            foreach ($parents as $parent) {
                $product = $this->pick($parent);
                Wishlist::firstOrCreate(['user_id' => $this->buyers[$index]->id, 'product_id' => $product->id]);
                ProductView::create(['user_id' => $this->buyers[$index]->id, 'product_id' => $product->id, 'viewed_at' => now()->subHours(2 + $index)]);
            }
        }
        foreach ([0 => ['iphone 13', 'tai nghe sony'], 1 => ['sofa', 'đàn guitar'], 2 => ['xe đẩy em bé']] as $index => $queries) {
            foreach ($queries as $query) {
                SearchLog::firstOrCreate(['user_id' => $this->buyers[$index]->id, 'query' => $query]);
            }
        }
        $chats = [
            [1, 'am-thanh', 'Chào bạn, loa còn bảo hành không? Mình ở gần, chiều nay qua xem được không?', 'Chào bạn, loa hết bảo hành hãng nhưng mình bảo hành 1 tháng. Chiều nay sau 5 giờ bạn qua nhé.'],
            [4, 'xe-may', 'Xe chính chủ không anh? Giấy tờ đầy đủ chứ ạ?', 'Xe chính chủ, đủ giấy tờ, bao sang tên. Em muốn xem xe thì nhắn trước giúp anh.'],
            [6, 'sofa', 'Sofa có ship về Biên Hòa không shop?', 'Có bạn nhé, phí giao trong tỉnh mình hỗ trợ một nửa.'],
        ];
        foreach ($chats as [$index, $parent, $question, $answer]) {
            $product = $this->pick($parent);
            $conversation = app(MessagingService::class)->start($this->buyers[$index], ['product_id' => $product->id]);
            app(MessagingService::class)->send($this->buyers[$index], $conversation, $question);
            app(MessagingService::class)->send($product->shop->owner, $conversation, $answer);
        }
        $reported = $this->pick('dien-tu');
        app(ReportService::class)->create($this->buyers[5], ['target_type' => 'PRODUCT', 'target_id' => $reported->id, 'reason' => 'MISLEADING', 'description' => 'Ảnh trong tin có vẻ lấy từ trên mạng, đề nghị người bán đăng ảnh chụp thật.']);
    }

    private function videos(): void
    {
        $file = base_path('tests/Fixtures/product-review.webm');
        if (! is_file($file)) {
            return;
        }
        $comments = ['Máy còn đẹp quá, shop có giảm thêm không?', 'Amply còn kêu tốt không shop, có bị rè không?'];
        foreach (['may-anh', 'thiet-bi-am-nhac'] as $step => $parent) {
            $product = $this->pick($parent);
            $video = app(ProductVideoService::class)->upload($product->shop->owner, $product, new UploadedFile($file, 'quay-thuc-te.webm', 'video/webm', null, true), ['title' => 'Quay thực tế tình trạng sản phẩm', 'description' => 'Video quay nhanh trước khi đăng bán để bạn xem rõ tình trạng.']);
            $this->files[] = [$video->path, $product->id];
            app(VideoCommentService::class)->create($this->buyers[$step], $video, $comments[$step]);
        }
    }

    /** One online payment that arrived after the buyer cancelled: it waits for an admin decision. */
    private function reconciliation(): void
    {
        $product = $this->pick('van-phong');
        $address = $this->addresses[0];
        $shipping = [];
        foreach (['recipient_name', 'phone', 'address_line', 'ward', 'district', 'province', 'country_code'] as $field) {
            $shipping['shipping_'.$field] = $address->$field;
        }
        $order = Order::create([...$shipping, 'buyer_id' => $this->buyers[0]->id, 'order_code' => 'SHM-'.Str::upper(substr(hash('sha256', $this->key('reconcile')), 0, 24)), 'checkout_key' => $this->key('reconcile'), 'request_hash' => hash('sha256', $this->key('reconcile')), 'subtotal' => $product->price, 'shipping_fee' => '0.00', 'discount_amount' => '0.00', 'total_amount' => $product->price, 'currency' => 'VND', 'payment_method' => PaymentMethod::ZALOPAY]);
        $part = $order->shopOrders()->create(['shop_id' => $product->shop_id, 'status' => 'CANCELLED', 'subtotal' => $product->price, 'shipping_fee' => '0.00', 'discount_amount' => '0.00', 'total_amount' => $product->price, 'cancelled_at' => now(), 'cancellation_reason' => 'Người mua hủy đơn trước khi thanh toán hoàn tất', 'stock_restored_at' => now()]);
        $part->orderItems()->create(['product_id' => $product->id, 'product_name' => $product->name, 'product_image' => $product->images()->where('is_primary', true)->value('image_path'), 'product_price' => $product->price, 'quantity' => 1, 'subtotal' => $product->price]);
        $stamp = now()->format('ymd');
        $order->payments()->create(['attempt_number' => 1, 'method' => PaymentMethod::ZALOPAY, 'amount' => $product->price, 'currency' => 'VND', 'status' => PaymentStatus::CANCELLED, 'reconciliation_required' => true, 'gateway_transaction_id' => $stamp.'000184527', 'transaction_reference' => $stamp.'_'.substr(preg_replace('/\D/', '', $this->key('reconcile')), 0, 10), 'gateway_requested_at' => now(), 'expires_at' => now()->addMinutes(15)]);
    }
}
