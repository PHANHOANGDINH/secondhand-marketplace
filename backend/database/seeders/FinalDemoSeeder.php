<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReportTargetType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Auction;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductView;
use App\Models\Report;
use App\Models\SearchLog;
use App\Models\Shop;
use App\Models\User;
use App\Models\VideoComment;
use App\Models\Voucher;
use App\Models\Wishlist;
use App\Services\AddressService;
use App\Services\AuctionCheckoutService;
use App\Services\AuctionService;
use App\Services\CartService;
use App\Services\CategoryService;
use App\Services\CheckoutService;
use App\Services\MessagingService;
use App\Services\ProductImageService;
use App\Services\ProductQuery;
use App\Services\ProductService;
use App\Services\ProductVideoService;
use App\Services\ReportService;
use App\Services\ReviewService;
use App\Services\ShopOrderService;
use App\Services\ShopService;
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

class FinalDemoSeeder extends Seeder
{
    private array $users = [];

    private array $products = [];

    private array $addresses = [];

    private array $createdFiles = [];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || ! in_array(DB::connection()->getDatabaseName(), ['secondhand_marketplace', 'secondhand_marketplace_test'], true)) {
            throw new RuntimeException('Final demo seed requires the project local/test database.');
        }
        $password = config('demo.password');
        if (! is_string($password) || strlen($password) < 12) {
            throw new RuntimeException('Configure a private DEMO_PASSWORD of at least 12 characters before seeding.');
        }
        $this->users = $this->products = $this->addresses = [];
        $oldCarbon = Carbon::getTestNow();
        $oldImmutable = CarbonImmutable::getTestNow();
        $present = CarbonImmutable::now();
        $this->createdFiles = [];
        try {
            Carbon::setTestNow($present->subDays(7));
            CarbonImmutable::setTestNow($present->subDays(7));
            DB::transaction(function () use ($password, $present): void {
                foreach (['admin', 'seller-1', 'seller-2', 'buyer-a', 'buyer-b', 'buyer-c'] as $key) {
                    $name = 'C1 Demo · '.Str::title(str_replace('-', ' ', $key));
                    $role = $key === 'admin' ? UserRole::ADMIN : UserRole::USER;
                    $user = User::withTrashed()->where('email', 'c1-'.$key.'@example.test')->first();
                    if ($user && ($user->name !== $name || $user->role !== $role || $user->trashed())) {
                        throw new RuntimeException('Reserved C1 account conflicts; no account is overwritten.');
                    }
                    if (! $user) {
                        $user = new User;
                        $user->forceFill(['name' => $name, 'email' => 'c1-'.$key.'@example.test', 'password' => $password, 'role' => $role, 'status' => UserStatus::ACTIVE])->save();
                    }
                    $this->users[$key] = $user;
                    if (str_starts_with($key, 'buyer')) {
                        $this->addresses[$key] = $user->addresses()->where('address_line', '123 Đường Demo C1')->first() ?? app(AddressService::class)->create($user, ['recipient_name' => $name, 'phone' => '0900000000', 'address_line' => '123 Đường Demo C1', 'ward' => 'Phường Demo', 'district' => 'Quận Demo', 'province' => 'TP. Hồ Chí Minh', 'country_code' => 'VN', 'is_default' => ! $user->addresses()->exists()]);
                    }
                }
                $shops = [];
                foreach (['seller-1' => 'Góc Công Nghệ', 'seller-2' => 'Nhà & Phong Cách'] as $key => $name) {
                    $seller = $this->users[$key];
                    $shop = Shop::withTrashed()->where('owner_id', $seller->id)->first();
                    if ($shop && ($shop->name !== 'C1 Demo · '.$name || $shop->trashed())) {
                        throw new RuntimeException('Reserved C1 shop conflicts; existing shop is preserved.');
                    }
                    if (! $shop) {
                        $shop = app(ShopService::class)->create($seller, ['name' => 'C1 Demo · '.$name, 'description' => 'Gian hàng minh họa local. Sản phẩm và địa chỉ đều là dữ liệu demo.']);
                        $shop = app(ShopService::class)->moderate($this->users['admin'], $shop, ['expected_status' => 'PENDING', 'status' => 'ACTIVE', 'reason' => 'Local C1 demo approved']);
                    }
                    $shops[$key] = $shop;
                }
                // D6 demo vouchers: one platform code and one shop code. Created once and never overwritten.
                Voucher::firstOrCreate(['code' => 'PASSLAI10'], ['scope' => Voucher::PLATFORM, 'type' => Voucher::PERCENT, 'value' => '10.00', 'max_discount' => '50000.00', 'min_order' => '200000.00', 'starts_at' => $present->subDay(), 'ends_at' => $present->addYear(), 'status' => Voucher::ACTIVE, 'created_by' => $this->users['admin']->id]);
                Voucher::firstOrCreate(['code' => 'CONGNGHE20K'], ['scope' => Voucher::SHOP, 'shop_id' => $shops['seller-1']->id, 'type' => Voucher::FIXED, 'value' => '20000.00', 'min_order' => '100000.00', 'starts_at' => $present->subDay(), 'ends_at' => $present->addYear(), 'status' => Voucher::ACTIVE, 'created_by' => $this->users['seller-1']->id]);
                $catalog = json_decode(file_get_contents(resource_path('demo-marketplace/catalog.json')), true, flags: JSON_THROW_ON_ERROR);
                $groups = ['phones' => ['dien-thoai', 'Điện thoại & máy tính bảng'], 'laptops' => ['laptop', 'Laptop & máy tính'], 'accessories' => ['phu-kien', 'Phụ kiện công nghệ'], 'home' => ['gia-dung', 'Đồ gia dụng'], 'fashion' => ['thoi-trang', 'Thời trang'], 'books' => ['sach-hoc-tap', 'Sách & học tập']];
                $index = 0;
                foreach ($catalog as $entry) {
                    if (in_array($entry['key'], ['galaxy-s22', 'thinkpad-t14'], true)) {
                        continue;
                    }
                    $index++;
                    $key = 'c1-demo-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT);
                    $name = 'C1 Demo '.str_pad((string) $index, 2, '0', STR_PAD_LEFT).' — '.$entry['name'];
                    $sellerKey = in_array($entry['group'], ['phones', 'laptops', 'accessories'], true) ? 'seller-1' : 'seller-2';
                    $seller = $this->users[$sellerKey];
                    $product = Product::withTrashed()->where('slug', Str::slug($name))->first();
                    if ($product && $product->shop_id !== $shops[$sellerKey]->id) {
                        throw new RuntimeException('Reserved C1 product conflicts; existing product is preserved.');
                    }
                    if (! $product) {
                        [$slug, $categoryName] = $groups[$entry['group']];
                        $category = app(CategoryService::class)->publicQuery()->whereIn('slug', [$slug, 'demo-pack-'.$entry['group'], 'c1-demo-'.$entry['group']])->first();
                        if (! $category) {
                            $category = Category::firstOrCreate(['slug' => 'c1-demo-'.$entry['group']], ['name' => $categoryName, 'status' => 'ACTIVE']);
                            if (! app(CategoryService::class)->publicQuery()->whereKey($category->id)->exists()) {
                                throw new RuntimeException('Reserved demo category is unavailable.');
                            }
                        }
                        $product = app(ProductService::class)->create($seller, ['name' => $name, 'description' => $entry['description']."\nDữ liệu local C1 demo; hình minh họa thuộc repository.", 'category_id' => $category->id, 'price' => $entry['price'], 'stock_quantity' => in_array($index, [17, 18], true) ? 1 : 8, 'condition' => $entry['condition']]);
                        $files = [];
                        for ($view = 1; $view <= $entry['images']; $view++) {
                            $files[] = new UploadedFile(resource_path('demo-marketplace/images/'.$entry['key'].'-'.$view.'.webp'), $entry['key'].'-'.$view.'.webp', 'image/webp', null, true);
                        }
                        app(ProductImageService::class)->upload($seller, $product, $files);
                        foreach ($product->images()->pluck('image_path') as $path) {
                            $this->createdFiles[] = ['image', $path, $product->id];
                        }
                        $product = app(ProductService::class)->action($seller, $product, 'submit');
                        $product = app(ProductService::class)->moderate($this->users['admin'], $product, ['expected_status' => 'PENDING', 'status' => 'ACTIVE', 'reason' => 'Local C1 demo approved']);
                    }
                    $this->products[$index] = $product;
                }
                Carbon::setTestNow($present);
                CarbonImmutable::setTestNow($present);
                $this->auctions();
                $this->orders();
                $this->media();
                $this->messaging();
                $this->reports();
                $this->inventory();
                foreach (['buyer-a' => [7, 14], 'buyer-b' => [2, 17]] as $buyer => $indices) {
                    foreach ($indices as $index) {
                        if (app(ProductQuery::class)->publicVisible()->whereKey($this->products[$index]->id)->exists()) {
                            Wishlist::firstOrCreate(['user_id' => $this->users[$buyer]->id, 'product_id' => $this->products[$index]->id]);
                        }
                    }
                }
                $this->integration();
            });
        } catch (Throwable $exception) {
            foreach ($this->createdFiles as [$kind, $path, $productId]) {
                if ($kind === 'video') {
                    app(ProductVideoService::class)->cleanup($path, $productId);
                } else {
                    app(ProductImageService::class)->cleanupUnreferenced($path);
                }
            }
            throw $exception;
        } finally {
            Carbon::setTestNow($oldCarbon);
            CarbonImmutable::setTestNow($oldImmutable);
        }
        $this->command?->info('C1 demo seed complete. Existing accounts/passwords, progress and unrelated records are preserved.');
    }

    /** D8: data the D5 and D7 screens need so they are not empty in a demo. Additive and repeatable. */
    private function integration(): void
    {
        // D7: a few product views and searches, so "Gợi ý cho bạn" is personal for the demo buyers.
        foreach (['buyer-a' => [[9, 10, 16], ['bàn phím cơ', 'tai nghe sony']], 'buyer-b' => [[15, 17], ['giày adidas']]] as $buyerKey => [$indices, $queries]) {
            $buyer = $this->users[$buyerKey];
            foreach ($indices as $index) {
                if (! ProductView::where('user_id', $buyer->id)->where('product_id', $this->products[$index]->id)->exists()) {
                    ProductView::create(['user_id' => $buyer->id, 'product_id' => $this->products[$index]->id, 'viewed_at' => now()]);
                }
            }
            foreach ($queries as $query) {
                SearchLog::firstOrCreate(['user_id' => $buyer->id, 'query' => $query]);
            }
        }
        // D5: one online order that was cancelled and then received money, waiting for an admin decision.
        // Written as new rows only: no cart, stock or existing record is touched and no gateway is called.
        $buyer = $this->users['buyer-c'];
        if (! Order::where('buyer_id', $buyer->id)->where('checkout_key', $this->key('reconcile'))->exists()) {
            $product = $this->products[15];
            $address = $this->addresses['buyer-c'];
            $shipping = [];
            foreach (['recipient_name', 'phone', 'address_line', 'ward', 'district', 'province', 'country_code'] as $field) {
                $shipping['shipping_'.$field] = $address->$field;
            }
            $order = Order::create([...$shipping, 'buyer_id' => $buyer->id, 'order_code' => 'SHM-C1DEMORECONCILE0001', 'checkout_key' => $this->key('reconcile'), 'request_hash' => hash('sha256', 'c1-demo-reconcile'), 'subtotal' => $product->price, 'shipping_fee' => '0.00', 'discount_amount' => '0.00', 'total_amount' => $product->price, 'currency' => 'VND', 'payment_method' => PaymentMethod::ZALOPAY]);
            $part = $order->shopOrders()->create(['shop_id' => $product->shop_id, 'status' => 'CANCELLED', 'subtotal' => $product->price, 'shipping_fee' => '0.00', 'discount_amount' => '0.00', 'total_amount' => $product->price, 'cancelled_at' => now(), 'cancellation_reason' => 'Demo: người mua hủy trước khi tiền về', 'stock_restored_at' => now()]);
            $part->orderItems()->create(['product_id' => $product->id, 'product_name' => $product->name, 'product_image' => $product->images()->where('is_primary', true)->value('image_path'), 'product_price' => $product->price, 'quantity' => 1, 'subtotal' => $product->price]);
            $order->payments()->create(['attempt_number' => 1, 'method' => PaymentMethod::ZALOPAY, 'amount' => $product->price, 'currency' => 'VND', 'status' => PaymentStatus::CANCELLED, 'reconciliation_required' => true, 'gateway_transaction_id' => 'DEMO-RECONCILE-0001', 'transaction_reference' => 'demo_c1_reconcile_0001', 'gateway_requested_at' => now(), 'expires_at' => now()->addMinutes(15)]);
        }
    }

    private function auction(int $index, string $kind): ?Auction
    {
        $product = $this->products[$index];
        if (Auction::where('product_id', $product->id)->exists()) {
            return null;
        }
        $seller = $this->users[$index <= 10 ? 'seller-1' : 'seller-2'];
        $start = now()->addMinutes($kind === 'scheduled' ? 15 : -1);
        $end = now()->addDays($kind === 'scheduled' ? 7 : 3);

        return app(AuctionService::class)->create($seller, ['product_id' => $product->id, 'start_price' => '100000.00', 'bid_increment' => '10000.00', 'starts_at' => $start->toISOString(), 'ends_at' => $end->toISOString()]);
    }

    private function auctions(): void
    {
        $this->auction(1, 'scheduled');
        foreach ([2, 3] as $index) {
            if ($auction = $this->auction($index, 'live')) {
                app(AuctionService::class)->placeBid($this->users['buyer-a'], $auction, '100000.00');
                app(AuctionService::class)->placeBid($this->users['buyer-b'], $auction, '110000.00');
            }
        }
        $oldCarbon = Carbon::getTestNow();
        $oldImmutable = CarbonImmutable::getTestNow();
        $past = CarbonImmutable::now()->subDays(4);
        try {
            foreach ([4, 5, 6] as $index) {
                if (Auction::where('product_id', $this->products[$index]->id)->exists()) {
                    continue;
                }
                Carbon::setTestNow($past);
                CarbonImmutable::setTestNow($past);
                $auction = $this->auction($index, 'live');
                if ($index === 6) {
                    app(AuctionService::class)->cancel($this->users['seller-1'], $auction);

                    continue;
                }
                if ($index === 4) {
                    app(AuctionService::class)->placeBid($this->users['buyer-a'], $auction, '100000.00');
                    app(AuctionService::class)->placeBid($this->users['buyer-b'], $auction, '120000.00');
                }
                $finish = $past->addDays(3)->addSecond();
                Carbon::setTestNow($finish);
                CarbonImmutable::setTestNow($finish);
                $auction = app(AuctionService::class)->finalize($auction);
                if ($index === 4) {
                    $order = app(AuctionCheckoutService::class)->checkout($this->users['buyer-b'], $auction, $this->addresses['buyer-b']->id, $this->key('auction-order'))['order'];
                    $this->fulfill($order, 'COMPLETED');
                    $this->users['buyer-a']->notifications()->where('type', 'OUTBID')->where('data->auction_id', (string) $auction->id)->update(['read_at' => now()]);
                }
            }
        } finally {
            Carbon::setTestNow($oldCarbon);
            CarbonImmutable::setTestNow($oldImmutable);
        }
    }

    private function key(string $name): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'secondhand-marketplace/c1-demo/'.$name)->toString();
    }

    private function orders(): void
    {
        foreach ([['pending', 11, 'buyer-a', 'PENDING'], ['processing', 12, 'buyer-b', 'PROCESSING'], ['completed', 13, 'buyer-a', 'COMPLETED'], ['sold', 18, 'buyer-c', 'COMPLETED']] as [$key, $index, $buyerKey, $status]) {
            $buyer = $this->users[$buyerKey];
            $order = Order::where('buyer_id', $buyer->id)->where('checkout_key', $this->key($key))->first();
            if (! $order) {
                abort_if($buyer->cart?->items()->exists(), 409, 'Demo buyer has an existing cart; preserve it and finish that cart first.');
                app(CartService::class)->add($buyer, $this->products[$index]->id, 1);
                $order = app(CheckoutService::class)->checkout($buyer, $this->addresses[$buyerKey]->id, $this->key($key), PaymentMethod::COD)['order'];
                $this->fulfill($order, $status);
                if (in_array($key, ['completed', 'sold'], true)) {
                    app(ReviewService::class)->create($buyer, $order->shopOrders->sole()->orderItems->sole(), ['rating' => 5, 'comment' => 'Sản phẩm đúng mô tả, đóng gói cẩn thận. Giao dịch demo C1 hoàn tất.']);
                }
            }
        }
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

    private function media(): void
    {
        $file = base_path('tests/Fixtures/product-review.webm');
        if (! is_file($file)) {
            $this->command?->warn('No local demo video; upload a valid MP4/WebM through Seller UI.');

            return;
        }
        foreach ([3, 7] as $index) {
            $product = $this->products[$index];
            if ($product->videos()->exists()) {
                continue;
            }
            $video = app(ProductVideoService::class)->upload($product->shop->owner, $product, new UploadedFile($file, 'demo-review.webm', 'video/webm', null, true), ['title' => 'C1 Demo · video kiểm tra local', 'description' => 'Clip minh họa nhỏ, không phải video review thật của sản phẩm.']);
            $this->createdFiles[] = ['video', $video->path, $product->id];
            foreach (['buyer-a' => 'Món này còn đúng như mô tả không shop?', 'buyer-b' => 'Video demo rõ ràng, mình sẽ xem thêm thông tin sản phẩm.'] as $buyer => $body) {
                app(VideoCommentService::class)->create($this->users[$buyer], $video, $body);
            }
        }
    }

    private function messaging(): void
    {
        foreach ([['buyer-a', 7], ['buyer-b', 11], ['buyer-c', 14]] as [$buyerKey, $index]) {
            $buyer = $this->users[$buyerKey];
            $product = $this->products[$index];
            if (! app(ProductQuery::class)->publicVisible()->whereKey($product->id)->exists()) {
                continue;
            }
            $conversation = app(MessagingService::class)->start($buyer, ['product_id' => $product->id]);
            if (! $conversation->messages()->exists()) {
                $service = app(MessagingService::class);
                $service->send($buyer, $conversation, 'Chào shop, sản phẩm còn không? Mình muốn hỏi thêm trước khi mua.');
                $reply = $service->send($product->shop->owner, $conversation, 'Chào bạn, sản phẩm còn. Bạn xem mô tả và ảnh minh họa demo nhé.');
                if ($buyerKey === 'buyer-b') {
                    $service->markRead($buyer, $conversation, $reply->id);
                }
            }
        }
    }

    private function inventory(): void
    {
        $seller = $this->users['seller-1'];
        $base = $this->products[7];
        $buyer = $this->users['buyer-a'];
        if (! Order::where('buyer_id', $buyer->id)->where('checkout_key', $this->key('d4-pending-order'))->exists()) {
            abort_if($buyer->cart?->items()->exists(), 409, 'Demo buyer has an existing cart; preserve it first.');
            app(CartService::class)->add($buyer, $base->id, 1);
            app(CheckoutService::class)->checkout($buyer, $this->addresses['buyer-a']->id, $this->key('d4-pending-order'), PaymentMethod::COD);
        }

        foreach (['low' => ['Sắp hết hàng', 3, 'ACTIVE'], 'out' => ['Hết hàng', 1, 'SOLD'], 'pending' => ['Chờ duyệt', 8, 'PENDING'], 'rejected' => ['Cần bổ sung mô tả', 8, 'REJECTED']] as $key => [$label, $stock, $status]) {
            $name = 'D4 Demo — '.$label;
            $slug = Str::slug($name);
            $existing = Product::withTrashed()->where('slug', $slug)->first();
            if ($existing) {
                if ($existing->shop_id !== $base->shop_id || $existing->name !== $name) {
                    throw new RuntimeException('Reserved D4 inventory product conflicts; existing record is preserved.');
                }

                continue;
            }
            $product = app(ProductService::class)->create($seller, ['name' => $name, 'description' => 'Sản phẩm minh họa tồn kho local D4. Hình ảnh minh họa thuộc repository, không phải tin bán thực tế.', 'category_id' => $base->category_id, 'price' => '350000.00', 'stock_quantity' => $stock, 'condition' => 'GOOD']);
            app(ProductImageService::class)->upload($seller, $product, [new UploadedFile(resource_path('demo-marketplace/images/sony-wh1000xm4-1.webp'), 'inventory-demo.webp', 'image/webp', null, true)]);
            foreach ($product->images()->pluck('image_path') as $path) {
                $this->createdFiles[] = ['image', $path, $product->id];
            }
            $product = app(ProductService::class)->action($seller, $product, 'submit');
            if ($status === 'PENDING') {
                continue;
            }
            $product = app(ProductService::class)->moderate($this->users['admin'], $product, ['expected_status' => 'PENDING', 'status' => $status === 'REJECTED' ? 'REJECTED' : 'ACTIVE', 'reason' => $status === 'REJECTED' ? 'Demo D4: bổ sung mô tả tình trạng sản phẩm.' : 'Local D4 demo approved']);
            if ($status === 'SOLD') {
                app(ProductService::class)->update($seller, $product, ['stock_quantity' => 0, 'expected_updated_at' => $product->updated_at->toISOString()]);
            }
        }
    }

    private function reports(): void
    {
        $service = app(ReportService::class);
        $comment = $this->products[7]->videos()->first()?->id;
        $comment = $comment ? VideoComment::where('product_video_id', $comment)->oldest('id')->first() : null;
        foreach ([['PRODUCT', $this->products[7]->id, 'buyer-a', 'PENDING', 'product'], ['SHOP', $this->products[14]->shop_id, 'buyer-b', 'IN_REVIEW', 'shop'], ['VIDEO_COMMENT', $comment?->id, 'buyer-c', 'DISMISSED', 'comment']] as [$type,$id,$buyer,$status,$key]) {
            if (! $id) {
                continue;
            }
            $marker = '[D3 Demo '.$key.'] Báo cáo minh họa để trình diễn quy trình, không phải kết luận vi phạm.';
            $existing = Report::where('reporter_user_id', $this->users[$buyer]->id)->where('target_type', $type)->where('target_id', $id);
            if ((clone $existing)->where('description', $marker)->exists() || (clone $existing)->whereIn('status', ['PENDING', 'IN_REVIEW'])->exists() || ! $service->publicTargets(ReportTargetType::from($type))->whereKey($id)->exists()) {
                continue;
            }
            $report = $service->create($this->users[$buyer], ['target_type' => $type, 'target_id' => $id, 'reason' => 'MISLEADING', 'description' => $marker]);
            if ($status !== 'PENDING') {
                $service->moderate($this->users['admin'], $report, ['expected_status' => 'PENDING', 'status' => $status, 'admin_note' => 'Báo cáo minh họa local D3; nội dung không bị thay đổi.']);
            }
        }
    }
}
