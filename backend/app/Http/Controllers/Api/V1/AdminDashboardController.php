<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Domain\DomainRequest;
use App\Http\Resources\AdminDashboardResource;
use App\Models\User;
use App\Services\AdminDashboardService;
use Illuminate\Support\Facades\Gate;

class AdminDashboardController extends Controller
{
    public function __invoke(DomainRequest $request, AdminDashboardService $service): AdminDashboardResource
    {
        Gate::authorize('viewAny', User::class);

        return new AdminDashboardResource($service->summary());
    }
}
