<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Domain\DomainRequest;
use App\Http\Requests\Domain\NotificationListRequest;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    public function index(NotificationListRequest $request): AnonymousResourceCollection
    {
        $query = $request->user()->notifications();
        $filter = $request->validated('filter', 'all');
        if ($filter === 'unread') {
            $query->whereNull('read_at');
        }
        if ($filter === 'orders') {
            $query->where('type', 'AUCTION_ORDER_CREATED');
        }
        if ($filter === 'auction') {
            $query->where('type', '!=', 'AUCTION_ORDER_CREATED');
        }

        return NotificationResource::collection($query->orderByDesc('id')->paginate($request->integer('per_page', 20))->withQueryString())->additional(['unread_count' => $request->user()->unreadNotifications()->count()]);
    }

    public function count(Request $request): JsonResponse
    {
        return response()->json(['data' => ['unread_count' => $request->user()->unreadNotifications()->count()]]);
    }

    public function read(DomainRequest $request, string $notification): NotificationResource
    {
        $row = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $request->user()->notifications()->whereKey($row->id)->whereNull('read_at')->update(['read_at' => now()]);

        return new NotificationResource($row->refresh());
    }

    public function readAll(DomainRequest $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['data' => ['unread_count' => $request->user()->unreadNotifications()->count()]]);
    }
}
