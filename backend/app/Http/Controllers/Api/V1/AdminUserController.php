<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Domain;
use App\Http\Resources\AdminUserResource;
use App\Models\User;
use App\Services\AdminUserService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AdminUserController extends Controller
{
    public function __construct(private AdminUserService $service) {}

    public function index(Domain\AdminUserListRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        return AdminUserResource::collection($this->service->query($request->validated())->paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function show(Domain\DomainRequest $request, User $user): AdminUserResource
    {
        Gate::authorize('viewAny', User::class);

        return new AdminUserResource($user);
    }

    public function updateStatus(Domain\ModerateUserRequest $request, User $user): AdminUserResource
    {
        return new AdminUserResource($this->service->moderate($request->user(), $user, $request->validated()));
    }
}
