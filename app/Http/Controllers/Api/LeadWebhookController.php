<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WebhookLeadRequest;
use App\Http\Resources\LeadResource;
use App\Services\LeadIntakeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class LeadWebhookController extends Controller
{
    /**
     * POST /api/webhooks/leads
     *
     * Receives a new lead from an external system (signature verified by
     * middleware), stores it and triggers the WhatsApp/SMS notification.
     */
    public function __invoke(WebhookLeadRequest $request, LeadIntakeService $intake): JsonResponse
    {
        $lead = $intake->receive($request->validated());

        return ApiResponse::success(new LeadResource($lead), 'Lead received successfully.', 201);
    }
}
