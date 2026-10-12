<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Domain;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ReportController extends Controller
{
    public function __construct(private ReportService $service) {}

    public function store(Domain\StoreReportRequest $request): JsonResponse
    {
        $report = $this->service->create($request->user(), $request->validated());
        $this->service->decorate(collect([$report]), false);

        return (new ReportResource($report))->response()->setStatusCode(201);
    }

    public function index(Domain\ReportListRequest $request): AnonymousResourceCollection
    {
        $admin = $request->is('api/v1/admin/reports');
        if ($admin) {
            Gate::authorize('viewAdmin', Report::class);
        }
        $query = Report::query();
        if ($admin) {
            $query->with(['reporter:id,name,deleted_at', 'handler:id,name,deleted_at']);
        } else {
            $query->where('reporter_user_id', $request->user()->id);
        }
        foreach (['status', 'target_type', 'reason'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->validated($field));
            }
        }
        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->validated('from').' 00:00:00');
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<', CarbonImmutable::parse($request->validated('to'))->addDay()->startOfDay());
        }
        $direction = $request->validated('sort', 'newest') === 'oldest' ? 'asc' : 'desc';
        $page = $query->orderBy('created_at', $direction)->orderBy('id', $direction)->paginate($request->integer('per_page', 20))->withQueryString();
        $this->service->decorate($page->getCollection(), $admin);

        return ReportResource::collection($page);
    }

    public function show(Domain\DomainRequest $request, Report $report): ReportResource
    {
        $admin = $request->is('api/v1/admin/reports/*');
        if ($admin) {
            Gate::authorize('viewAdmin', Report::class);
            $report->load(['reporter:id,name,deleted_at', 'handler:id,name,deleted_at']);
        } else {
            abort_unless($report->reporter_user_id === $request->user()->id, 404);
            Gate::authorize('view', $report);
        }
        $this->service->decorate(collect([$report]), $admin);

        return new ReportResource($report);
    }

    public function updateStatus(Domain\ModerateReportRequest $request, Report $report): ReportResource
    {
        $report = $this->service->moderate($request->user(), $report, $request->validated());

        return $this->show($request, $report);
    }
}
