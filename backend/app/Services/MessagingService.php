<?php

namespace App\Services;

use App\Events\ConversationRead;
use App\Events\MessageSent;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MessagingService
{
    public function __construct(private ProductQuery $products, private ShopService $shops) {}

    public function start(User $user, array $data): Conversation
    {
        return DB::transaction(function () use ($user, $data): Conversation {
            $buyer = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($buyer)->authorize('create', Conversation::class);
            $product = isset($data['product_id']) ? $this->products->publicVisible()->whereKey($data['product_id'])->firstOrFail() : null;
            $shop = $this->shops->publicQuery()->when($product, fn (Builder $query) => $query->whereKey($product->shop_id))
                ->when(! $product, fn (Builder $query) => $query->where('owner_id', $data['seller_id']))->firstOrFail();
            if ($buyer->id === $shop->owner_id) {
                throw ValidationException::withMessages(['product_id' => 'Bạn không thể nhắn tin cho chính mình.']);
            }
            $key = $buyer->id.':'.$shop->owner_id.':'.($product?->id ?? 0);
            $conversation = Conversation::where('context_key', $key)->first();
            if (! $conversation) {
                $conversation = new Conversation;
                $conversation->forceFill(['buyer_id' => $buyer->id, 'seller_id' => $shop->owner_id, 'product_id' => $product?->id, 'context_key' => $key])->save();
                foreach ([$buyer->id, $shop->owner_id] as $id) {
                    $participant = $conversation->participants()->make();
                    $participant->forceFill(['user_id' => $id, 'last_read_message_id' => 0])->save();
                }
            }

            return $conversation;
        }, 3);
    }

    public function forUser(User $user): Builder
    {
        return Conversation::whereHas('participants', fn (Builder $query) => $query->where('user_id', $user->id))
            ->with(['participants.user:id,name', 'latestMessage'])
            ->withCount(['messages as unread_count' => fn (Builder $query) => $this->unread($query, $user)]);
    }

    private function unread(Builder $query, User $user): Builder
    {
        return $query->where('sender_id', '!=', $user->id)
            ->where('messages.id', '>', DB::table('conversation_participants')->select('last_read_message_id')
                ->whereColumn('conversation_participants.conversation_id', 'messages.conversation_id')->where('user_id', $user->id));
    }

    public function unreadTotal(User $user): int
    {
        return $this->unread(Message::query(), $user)->whereHas('conversation.participants', fn (Builder $query) => $query->where('user_id', $user->id))->count();
    }

    public function productContexts(Collection $conversations): void
    {
        $products = $this->products->publicVisible()->whereIn('id', $conversations->pluck('product_id')->filter())->get(['id', 'name', 'slug', 'price'])->keyBy('id');
        foreach ($conversations as $conversation) {
            $conversation->setRelation('visibleProduct', $products->get($conversation->product_id));
        }
    }

    public function send(User $user, Conversation $conversation, string $body): Message
    {
        return DB::transaction(function () use ($user, $conversation, $body): Message {
            $locked = Conversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($user)->authorize('send', $locked);
            $message = $locked->messages()->make();
            $message->forceFill(['sender_id' => $user->id, 'body' => $body])->save();
            $locked->forceFill(['last_message_at' => $message->created_at])->save();
            event(new MessageSent($locked->participants()->pluck('user_id')->all(), ['conversation_id' => (string) $locked->id, 'message' => (new MessageResource($message))->resolve()]));

            return $message;
        }, 3);
    }

    public function markRead(User $user, Conversation $conversation, int $lastMessageId): int
    {
        return DB::transaction(function () use ($user, $conversation, $lastMessageId): int {
            $locked = Conversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($user)->authorize('view', $locked);
            if ($lastMessageId !== 0 && ! $locked->messages()->whereKey($lastMessageId)->exists()) {
                throw ValidationException::withMessages(['last_message_id' => 'Tin nhắn không thuộc hội thoại này.']);
            }
            $participant = $locked->participants()->where('user_id', $user->id)->firstOrFail();
            $previous = $participant->last_read_message_id;
            $participant->forceFill(['last_read_message_id' => max($previous, $lastMessageId)])->save();
            if ($participant->last_read_message_id > $previous) {
                event(new ConversationRead([$user->id], ['conversation_id' => (string) $locked->id, 'last_read_message_id' => (string) $participant->last_read_message_id]));
            }

            return $participant->last_read_message_id;
        }, 3);
    }
}
