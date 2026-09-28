<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lead\ListLeadsRequest;
use App\Http\Requests\Lead\StoreLeadRequest;
use App\Http\Requests\Lead\UpdateLeadRequest;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class LeadController extends Controller
{
    private const DEFAULT_PER_PAGE = 15;

    /**
     * GET /api/leads?status=&source=&per_page=&page=
     */
    public function index(ListLeadsRequest $request): JsonResponse
    {
        $leads = Lead::query()
            ->filter($request->safe()->only(['status', 'source']))
            ->latest()
            ->latest('id')
            ->paginate($request->integer('per_page', self::DEFAULT_PER_PAGE))
            ->withQueryString();

        return ApiResponse::success(
            LeadResource::collection($leads->items()),
            'Leads retrieved successfully.',
            meta: [
                'current_page' => $leads->currentPage(),
                'last_page' => $leads->lastPage(),
                'per_page' => $leads->perPage(),
                'total' => $leads->total(),
                'from' => $leads->firstItem(),
                'to' => $leads->lastItem(),
                'next_page_url' => $leads->nextPageUrl(),
                'prev_page_url' => $leads->previousPageUrl(),
            ],
        );
    }

    public function store(StoreLeadRequest $request): JsonResponse
    {
        $lead = Lead::create($request->validated());

        return ApiResponse::success(new LeadResource($lead), 'Lead created successfully.', 201);
    }

    public function show(Lead $lead): JsonResponse
    {
        return ApiResponse::success(new LeadResource($lead), 'Lead retrieved successfully.');
    }

    public function update(UpdateLeadRequest $request, Lead $lead): JsonResponse
    {
        $lead->update($request->validated());

        return ApiResponse::success(new LeadResource($lead), 'Lead updated successfully.');
    }

    public function destroy(Lead $lead): JsonResponse
    {
        $lead->delete();

        return ApiResponse::success(message: 'Lead deleted successfully.');
    }
}
