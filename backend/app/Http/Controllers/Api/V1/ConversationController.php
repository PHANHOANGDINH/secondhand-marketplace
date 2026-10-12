<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Domain;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Services\MessagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ConversationController extends Controller
{
    public function __construct(private MessagingService $service) {}

    public function index(Domain\ConversationListRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('create', Conversation::class);
        $conversations = $this->service->forUser($request->user())->orderByRaw('COALESCE(last_message_at, created_at) DESC')->orderByDesc('id')
            ->paginate($request->integer('per_page', 20))->withQueryString();
        $this->service->productContexts($conversations->getCollection());

        return ConversationResource::collection($conversations)->additional(['unread_total' => $this->service->unreadTotal($request->user())]);
    }

    public function store(Domain\StartConversationRequest $request): JsonResponse
    {
        $conversation = $this->service->start($request->user(), $request->validated());
        $resource = $this->service->forUser($request->user())->whereKey($conversation->id)->firstOrFail();
        $this->service->productContexts(collect([$resource]));

        return (new ConversationResource($resource))->response()->setStatusCode($conversation->wasRecentlyCreated ? 201 : 200);
    }

    public function messages(Domain\MessageListRequest $request, Conversation $conversation): AnonymousResourceCollection
    {
        Gate::authorize('view', $conversation);
        $query = $conversation->messages();
        if ($request->has('before_id')) {
            $query->where('id', '<', $request->integer('before_id'));
        }
        if ($request->has('after_id')) {
            $query->where('id', '>', $request->integer('after_id'))->orderBy('id');
        } else {
            $query->orderByDesc('id');
        }

        return MessageResource::collection($query->paginate($request->integer('per_page', 30))->withQueryString());
    }

    public function send(Domain\SendMessageRequest $request, Conversation $conversation): JsonResponse
    {
        return (new MessageResource($this->service->send($request->user(), $conversation, $request->validated('body'))))->response()->setStatusCode(201);
    }

    public function read(Domain\ReadConversationRequest $request, Conversation $conversation): JsonResponse
    {
        $checkpoint = $this->service->markRead($request->user(), $conversation, $request->integer('last_message_id'));

        return response()->json(['data' => ['last_read_message_id' => (string) $checkpoint, 'unread_count' => $this->service->forUser($request->user())->whereKey($conversation->id)->firstOrFail()->unread_count]]);
    }
}
