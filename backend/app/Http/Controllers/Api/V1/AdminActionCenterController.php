<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Domain\DomainRequest;
use App\Models\User;
use App\Services\AdminActionCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AdminActionCenterController extends Controller
{
    public function __invoke(DomainRequest $request, AdminActionCenterService $service): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        return response()->json(['data' => $service->summary()]);
    }
}
