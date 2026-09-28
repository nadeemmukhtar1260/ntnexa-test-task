<?php

namespace App\Services;

use App\Contracts\LeadNotifier;
use App\Enums\LeadStatus;
use App\Models\Lead;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Handles leads arriving from external systems: stores the lead and
 * triggers the new-lead notification.
 */
class LeadIntakeService
{
    public function __construct(private readonly LeadNotifier $notifier) {}

    /**
     * @param  array<string, mixed>  $data  Validated webhook payload
     */
    public function receive(array $data): Lead
    {
        $lead = Lead::create([
            ...$data,
            'source' => $data['source'] ?? 'webhook',
            'status' => LeadStatus::New,
        ]);

        try {
            $this->notifier->notifyNewLead($lead);
        } catch (Throwable $e) {
            // The lead is already saved; a messaging outage must not make the
            // caller retry and create duplicates. Log it for follow-up instead.
            Log::error('Failed to send new lead notification.', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $lead;
    }
}
