<?php

namespace App\Services\Notifications;

use App\Contracts\LeadNotifier;
use App\Models\Lead;
use Illuminate\Support\Facades\Log;

/**
 * Stub WhatsApp/SMS provider used for local development and this demo.
 *
 * No real message is sent: the "delivery" is written to the application
 * log (storage/logs/laravel.log) so it can be inspected.
 */
class MockLeadNotifier implements LeadNotifier
{
    public function notifyNewLead(Lead $lead): void
    {
        Log::info(
            "Mock WhatsApp/SMS notification sent to {$lead->phone} for lead {$lead->name}.",
            [
                'lead_id' => $lead->id,
                'channel' => 'whatsapp/sms (mock)',
                'message' => $this->message($lead),
            ],
        );
    }

    private function message(Lead $lead): string
    {
        return "Hi {$lead->name}, thanks for your interest! Our team will contact you shortly.";
    }
}
