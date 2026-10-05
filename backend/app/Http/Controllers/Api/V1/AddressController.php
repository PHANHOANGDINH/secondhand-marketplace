<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Domain;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use App\Services\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class AddressController extends Controller
{
    public function __construct(private AddressService $service) {}

    public function index(Domain\AddressListRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Address::class);

        return AddressResource::collection($request->user()->addresses()->orderByDesc('is_default')->orderBy('id')->paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function store(Domain\StoreAddressRequest $request): JsonResponse
    {
        Gate::authorize('create', Address::class);

        return (new AddressResource($this->service->create($request->user(), $request->validated())))->response()->setStatusCode(201);
    }

    public function show(Address $address): AddressResource
    {
        Gate::authorize('view', $address);

        return new AddressResource($address);
    }

    public function update(Domain\UpdateAddressRequest $request, Address $address): AddressResource
    {
        return new AddressResource($this->service->update($request->user(), $address, $request->validated()));
    }

    public function setDefault(Domain\DomainRequest $request, Address $address): AddressResource
    {
        Gate::authorize('setDefault', $address);

        return new AddressResource($this->service->setDefault($request->user(), $address));
    }

    public function destroy(Request $request, Address $address): Response
    {
        Gate::authorize('delete', $address);
        $this->service->delete($request->user(), $address);

        return response()->noContent();
    }
}
