<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\VideoCommentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Domain;
use App\Http\Resources\VideoCommentResource;
use App\Models\ProductVideo;
use App\Models\VideoComment;
use App\Services\VideoCommentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class VideoCommentController extends Controller
{
    public function __construct(private VideoCommentService $service) {}

    public function index(Domain\ListVideoCommentsRequest $request, ProductVideo $video): AnonymousResourceCollection
    {
        $video = $this->service->publicVideos()->whereKey($video->id)->with('product.shop')->firstOrFail();
        $comments = VideoComment::where('product_video_id', $video->id)->where('status', VideoCommentStatus::Published)
            ->with('user:id,name,deleted_at')->when($request->filled('before_id'), fn (Builder $q) => $q->where('id', '<', $request->integer('before_id')))
            ->latest('id')->paginate($request->integer('per_page', 20))->withQueryString();
        foreach ($comments as $comment) {
            $comment->setRelation('video', $video);
        }
        $probe = new VideoComment;
        $probe->forceFill(['user_id' => 0])->setRelation('video', $video);

        return VideoCommentResource::collection($comments)->additional(['permissions' => ['can_moderate' => (bool) $request->user()?->can('hide', $probe)]]);
    }

    public function store(Domain\StoreVideoCommentRequest $request, ProductVideo $video): JsonResponse
    {
        return (new VideoCommentResource($this->service->create($request->user(), $video, $request->validated('body'))))->response()->setStatusCode(201);
    }

    public function update(Domain\HideVideoCommentRequest $request, VideoComment $comment): Response
    {
        $this->service->hide($request->user(), $comment);

        return response()->noContent();
    }
}
