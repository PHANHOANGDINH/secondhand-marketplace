<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProductImageService
{
    public function __construct(private ProductLocks $locks, private AuditService $audit, private FixedPriceEligibility $claims) {}

    private function storeImage(Product $product, UploadedFile $file, int $index): string
    {
        $size = @getimagesize($file->getRealPath());
        $limit = ini_parse_quantity(ini_get('memory_limit'));
        $estimate = $size ? $size[0] * $size[1] * 8 + $file->getSize() * 2 + 16777216 : PHP_INT_MAX;
        if (! $size || $size[0] * $size[1] > 20000000 || ($limit > 0 && memory_get_usage(true) + $estimate > $limit)) {
            throw ValidationException::withMessages(['images.'.$index => ['This image exceeds the pixel or processing memory limit. Please resize it.']]);
        }
        $bytes = file_get_contents($file->getRealPath());
        $decoded = @imagecreatefromstring($bytes);
        if (! $decoded) {
            throw ValidationException::withMessages(['images.'.$index => ['The image could not be decoded.']]);
        }
        $mime = $file->getMimeType();
        $extension = match ($mime) {
            'image/jpeg' => 'jpg','image/png' => 'png','image/webp' => 'webp'
        };
        ob_start();
        try {
            imagesavealpha($decoded, true);
            $encoded = match ($mime) {
                'image/jpeg' => imagejpeg($decoded, null, 90),'image/png' => imagepng($decoded, null, 6),'image/webp' => imagewebp($decoded, null, 90)
            };
            $clean = ob_get_contents();
        } finally {
            ob_end_clean();
            imagedestroy($decoded);
        }
        if (! $encoded || ! $clean) {
            throw ValidationException::withMessages(['images.'.$index => ['The image could not be encoded safely.']]);
        }
        $path = 'products/'.$product->id.'/'.Str::uuid().'.'.$extension;
        if (! Storage::disk('public')->put($path, $clean)) {
            throw new \RuntimeException('Image storage failed.');
        }

        return $path;
    }

    public function cleanupUnreferenced(string $path): void
    {
        if (! preg_match('#^products/[1-9][0-9]*/[a-f0-9-]{36}\.(jpg|png|webp)$#D', $path)) {
            return;
        }
        try {
            if (ProductImage::where('image_path', $path)->exists() || OrderItem::where('product_image', $path)->exists()) {
                return;
            }
            if (! Storage::disk('public')->delete($path)) {
                Log::warning('Product image cleanup pending.', ['path' => $path]);
            }
        } catch (Throwable) {
            Log::warning('Product image cleanup deferred.', ['path' => $path]);
        }
    }

    private function normalize(Product $product): void
    {
        $images = $product->images()->orderBy('sort_order')->orderBy('id')->get();
        $primary = $images->firstWhere('is_primary', true) ?? $images->first();
        foreach ($images as $index => $image) {
            $image->update(['sort_order' => $index, 'is_primary' => $image->id === $primary->id]);
        }
    }

    private function changed(User $user, Product $product, string $action): void
    {
        $from = $product->status;
        if (in_array($from, [ProductStatus::ACTIVE, ProductStatus::HIDDEN], true)) {
            $product->status = ProductStatus::PENDING;
        }
        $product->touch();
        $this->audit->record($user, 'products', $product->id, $action, $from->value, $product->status->value, null);
    }

    public function upload(User $user, Product $product, array $files): Product
    {
        Gate::forUser($user)->authorize('update', $product);
        $paths = [];
        try {
            foreach ($files as $index => $file) {
                $paths[] = $this->storeImage($product, $file, $index);
            }

            return DB::transaction(function () use ($user, $product, $paths): Product {
                $locked = $this->locks->lock($user, $product, 'update');
                $this->claims->assertAvailable($locked->id);
                $count = $locked->images()->count();
                if ($count + count($paths) > 8) {
                    throw ValidationException::withMessages(['images' => ['A product may have at most 8 images.']]);
                }
                foreach ($paths as $index => $path) {
                    $locked->images()->create(['image_path' => $path, 'is_primary' => $count === 0 && $index === 0, 'sort_order' => $count + $index]);
                }
                $this->normalize($locked);
                $this->changed($user, $locked, 'product.images_uploaded');

                return $locked;
            }, 3);
        } catch (Throwable $error) {
            foreach ($paths as $path) {
                $this->cleanupUnreferenced($path);
            } throw $error;
        }
    }

    public function update(User $user, Product $product, ProductImage $image, array $data): ProductImage
    {
        return DB::transaction(function () use ($user, $product, $image, $data): ProductImage {
            $locked = $this->locks->lock($user, $product, 'update');
            $this->claims->assertAvailable($locked->id);
            abort_unless($image->product_id === $locked->id, 404);
            $image = $locked->images()->whereKey($image->id)->firstOrFail();
            $image->setRelation('product', $locked);
            Gate::forUser($user)->authorize('update', $image);
            if (isset($data['is_primary'])) {
                $locked->images()->update(['is_primary' => false]);
                $image->update(['is_primary' => true]);
            } else {
                $ids = $locked->images()->orderBy('sort_order')->orderBy('id')->pluck('id')->all();
                if ($data['sort_order'] >= count($ids)) {
                    throw ValidationException::withMessages(['sort_order' => ['Choose a position within this product image list.']]);
                }
                $ids = array_values(array_diff($ids, [$image->id]));
                array_splice($ids, (int) $data['sort_order'], 0, [$image->id]);
                $this->applyOrder($locked, $ids);
            }
            $this->normalize($locked);
            $this->changed($user, $locked, 'product.image_updated');

            return $image->refresh();
        }, 3);
    }

    private function applyOrder(Product $product, array $ids): void
    {
        foreach ($ids as $index => $id) {
            $product->images()->whereKey($id)->update(['sort_order' => $index]);
        }
    }

    public function reorder(User $user, Product $product, array $ids): Product
    {
        return DB::transaction(function () use ($user, $product, $ids): Product {
            $locked = $this->locks->lock($user, $product, 'update');
            $this->claims->assertAvailable($locked->id);
            $actual = $locked->images()->pluck('id')->map(fn ($id) => (string) $id)->all();
            $requested = array_map('strval', $ids);
            $sorted = $requested;
            sort($sorted);
            sort($actual);
            if ($sorted !== $actual || count($requested) !== count(array_unique($requested))) {
                throw ValidationException::withMessages(['image_ids' => ['Supply every image of this product exactly once.']]);
            }
            $this->applyOrder($locked, $requested);
            $this->normalize($locked);
            $this->changed($user, $locked, 'product.images_reordered');

            return $locked;
        }, 3);
    }

    public function delete(User $user, Product $product, ProductImage $image): void
    {
        DB::transaction(function () use ($user, $product, $image): void {
            $locked = $this->locks->lock($user, $product, 'update');
            $this->claims->assertAvailable($locked->id);
            abort_unless($image->product_id === $locked->id, 404);
            $image = $locked->images()->whereKey($image->id)->firstOrFail();
            $image->setRelation('product', $locked);
            Gate::forUser($user)->authorize('delete', $image);
            $path = $image->image_path;
            $image->delete();
            $this->normalize($locked);
            $this->changed($user, $locked, 'product.image_deleted');
            DB::afterCommit(fn () => $this->cleanupUnreferenced($path));
        }, 3);
    }
}
