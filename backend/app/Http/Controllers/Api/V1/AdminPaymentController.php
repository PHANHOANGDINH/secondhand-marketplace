<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Domain;
use App\Http\Resources\AdminPaymentResource;
use App\Models\Payment;
use App\Services\PaymentReconciliationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AdminPaymentController extends Controller
{
    private const RELATIONS = ['order:id,order_code,buyer_id,total_amount,payment_method', 'order.buyer:id,name,deleted_at', 'order.shopOrders:id,order_id,shop_id,status', 'order.payments:id,order_id,status,amount', 'resolver:id,name,deleted_at'];

    /** Only payments that were ever flagged for reconciliation belong to this queue. */
    private function flagged(): Builder
    {
        return Payment::where('reconciliation_required', true)->with(self::RELATIONS);
    }

    public function index(Domain\AdminPaymentListRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAdmin', Payment::class);
        $query = $this->flagged();
        $filter = $request->validated('reconciliation', 'all');
        if (in_array($filter, ['1', 'open'], true)) {
            $query->whereNull('reconciliation_resolved_at');
        } elseif ($filter === 'resolved') {
            $query->whereNotNull('reconciliation_resolved_at');
        }

        return AdminPaymentResource::collection($query->orderByDesc('id')->paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function show(Domain\DomainRequest $request, string $payment): AdminPaymentResource
    {
        Gate::authorize('viewAdmin', Payment::class);

        return new AdminPaymentResource($this->flagged()->findOrFail($payment));
    }

    public function resolve(Domain\ResolveReconciliationRequest $request, Payment $payment, PaymentReconciliationService $service): AdminPaymentResource
    {
        $saved = $service->resolve($request->user(), $payment, $request->validated());

        return new AdminPaymentResource($this->flagged()->findOrFail($saved->id));
    }
}
