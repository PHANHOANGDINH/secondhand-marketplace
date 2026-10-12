<?php

namespace App\Models;

use App\Enums\VideoCommentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoComment extends Model
{
    use HasFactory;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['status' => VideoCommentStatus::class];
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(ProductVideo::class, 'product_video_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
