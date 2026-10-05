<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Domain\SellerDashboardRequest;
use App\Http\Requests\Domain\SellerRevenueRequest;
use App\Http\Resources\SellerDashboardResource;
use App\Http\Resources\SellerRevenueResource;
use App\Services\SellerDashboardService;

class SellerDashboardController extends Controller
{
    public function __construct(private SellerDashboardService $service) {}

    public function show(SellerDashboardRequest $request): SellerDashboardResource
    {
        return new SellerDashboardResource($this->service->dashboard($request->user(), $request->validated()));
    }

    public function revenue(SellerRevenueRequest $request): SellerRevenueResource
    {
        return new SellerRevenueResource($this->service->revenue($request->user(), $request->validated()));
    }
}
