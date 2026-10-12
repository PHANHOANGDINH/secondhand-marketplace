<?php

namespace App\Services;

use App\Enums\ProductVideoStatus;
use App\Http\Requests\Domain\UploadProductVideoRequest;
use App\Models\Product;
use App\Models\ProductVideo;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProductVideoService
{
    public function __construct(private ProductLocks $locks, private AuditService $audit) {}

    private function normalize(Product $product, ?int $preferred = null): void
    {
        $rows = $product->videos()->orderBy('sort_order')->orderBy('id')->get();
        $ready = $rows->where('status', ProductVideoStatus::Ready);
        $primary = $ready->firstWhere('id', $preferred) ?? $ready->firstWhere('is_primary', true) ?? $ready->first();
        $product->videos()->update(['is_primary' => false]);
        foreach ($rows as $index => $row) {
            $row->is_primary = false;
            $row->syncOriginalAttribute('is_primary');
            $row->forceFill(['sort_order' => $index, 'is_primary' => $row->id === $primary?->id])->save();
        }
    }

    private function changed(User $user, Product $product, string $action): void
    {
        $this->audit->record($user, 'products', $product->id, $action, $product->status->value, $product->status->value, null);
    }

    public function upload(User $user, Product $product, UploadedFile $file, array $metadata = []): ProductVideo
    {
        Gate::forUser($user)->authorize('update', $product);
        Validator::make(['video' => $file, ...$metadata], (new UploadProductVideoRequest)->rules())->validate();
        $mime = $file->getMimeType();
        $extension = $mime === 'video/mp4' ? 'mp4' : 'webm';
        $stream = fopen($file->getRealPath(), 'rb');
        try {
            $head = fread($stream, 4096);
        } finally {
            fclose($stream);
        }
        $container = $extension === 'mp4' ? (strlen($head) >= 12 && substr($head, 4, 4) === 'ftyp') : (str_starts_with($head, "\x1A\x45\xDF\xA3") && str_contains($head, 'webm'));
        if (strtolower($file->getClientOriginalExtension()) !== $extension || ! $container) {
            throw ValidationException::withMessages(['video' => ['The file must be a supported MP4 or WebM container matching its extension.']]);
        }
        $path = Storage::disk('public')->putFileAs('product-videos/'.$product->id, $file, Str::uuid().'.'.$extension);
        if (! $path) {
            throw new \RuntimeException('Video storage failed.');
        }
        try {
            return DB::transaction(function () use ($user, $product, $file, $path, $mime, $metadata): ProductVideo {
                $locked = $this->locks->lock($user, $product, 'update');
                $count = $locked->videos()->count();
                if ($count >= config('product-videos.max_files')) {
                    throw ValidationException::withMessages(['video' => ['A product may have at most 3 videos.']]);
                }
                $video = new ProductVideo;
                $video->forceFill(['product_id' => $locked->id, 'uploaded_by' => $user->id, 'path' => $path, 'mime_type' => $mime, 'file_size' => $file->getSize(), 'status' => ProductVideoStatus::Ready, 'sort_order' => $count, 'title' => $metadata['title'] ?? null, 'description' => $metadata['description'] ?? null])->save();
                $this->normalize($locked);
                $this->changed($user, $locked, 'product.video_uploaded');

                return $video->refresh();
            }, 3);
        } catch (Throwable $error) {
            $this->cleanup($path, $product->id);
            throw $error;
        }
    }

    public function update(User $user, Product $product, ProductVideo $video, array $data): ProductVideo
    {
        return DB::transaction(function () use ($user, $product, $video, $data): ProductVideo {
            $locked = $this->locks->lock($user, $product, 'update');
            abort_unless($video->product_id === $locked->id, 404);
            $video = $locked->videos()->whereKey($video->id)->lockForUpdate()->firstOrFail();
            $video->setRelation('product', $locked);
            Gate::forUser($user)->authorize('update', $video);
            abort_unless(in_array($video->status, [ProductVideoStatus::Ready, ProductVideoStatus::Hidden], true), 409, 'Video is not editable in its current state.');
            if (isset($data['status'])) {
                $video->forceFill(['status' => $data['status'], 'is_primary' => false]);
            }
            if (isset($data['is_primary'])) {
                abort_unless($video->status === ProductVideoStatus::Ready, 409, 'Only READY videos can be primary.');
            }
            $video->forceFill(array_intersect_key($data, array_flip(['title', 'description'])))->save();
            if (isset($data['sort_order'])) {
                $ids = $locked->videos()->orderBy('sort_order')->orderBy('id')->pluck('id')->all();
                if ($data['sort_order'] >= count($ids)) {
                    throw ValidationException::withMessages(['sort_order' => ['Choose a position within this product video list.']]);
                }
                $ids = array_values(array_diff($ids, [$video->id]));
                array_splice($ids, (int) $data['sort_order'], 0, [$video->id]);
                foreach ($ids as $index => $id) {
                    $locked->videos()->whereKey($id)->update(['sort_order' => $index]);
                }
            }
            $this->normalize($locked, isset($data['is_primary']) ? $video->id : null);
            $this->changed($user, $locked, 'product.video_updated');

            return $video->refresh();
        }, 3);
    }

    public function delete(User $user, Product $product, ProductVideo $video): void
    {
        DB::transaction(function () use ($user, $product, $video): void {
            $locked = $this->locks->lock($user, $product, 'update');
            abort_unless($video->product_id === $locked->id, 404);
            $video = $locked->videos()->whereKey($video->id)->lockForUpdate()->firstOrFail();
            $video->setRelation('product', $locked);
            Gate::forUser($user)->authorize('delete', $video);
            $paths = [$video->path, $video->poster_path];
            $video->delete();
            $this->normalize($locked);
            $this->changed($user, $locked, 'product.video_deleted');
            DB::afterCommit(function () use ($paths, $locked): void {
                foreach (array_filter($paths) as $path) {
                    $this->cleanup($path, $locked->id);
                }
            });
        }, 3);
    }

    public function cleanup(string $path, int $productId): void
    {
        if (! preg_match('#^product-videos/'.preg_quote((string) $productId, '#').'/[a-f0-9-]{36}\.(mp4|webm|jpg|png|webp)$#D', $path)) {
            return;
        }
        try {
            if (ProductVideo::where('path', $path)->orWhere('poster_path', $path)->exists()) {
                return;
            }if (! Storage::disk('public')->delete($path)) {
                Log::warning('Product video cleanup pending.');
            }
        } catch (Throwable) {
            Log::warning('Product video cleanup deferred.');
        }
    }
}
