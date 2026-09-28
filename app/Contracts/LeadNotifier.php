<?php

namespace App\Contracts;

use App\Models\Lead;

/**
 * Sends an outbound message (WhatsApp, SMS, ...) when a new lead arrives.
 *
 * Controllers and services depend only on this interface; the concrete
 * provider is chosen in AppServiceProvider, so swapping the mock for a
 * real provider (Twilio, Meta WhatsApp Cloud API, ...) needs no
 * controller changes.
 */
interface LeadNotifier
{
    /**
     * @throws \Throwable when the provider fails to deliver the message
     */
    public function notifyNewLead(Lead $lead): void;
}
