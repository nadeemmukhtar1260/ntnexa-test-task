<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies that an incoming webhook was sent by a trusted caller.
 *
 * The caller signs the raw request body with a shared secret using
 * HMAC-SHA256 and sends the hex digest in the X-Webhook-Signature header:
 *
 *   X-Webhook-Signature: sha256=<hex digest>
 */
class VerifyWebhookSignature
{
    public const HEADER = 'X-Webhook-Signature';

    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.lead_webhook.secret');

        if ($secret === '') {
            // Fail closed: never accept unsigned webhooks because of a missing config value.
            Log::error('Lead webhook rejected: LEAD_WEBHOOK_SECRET is not configured.');

            return ApiResponse::error('Webhook is not configured.', 503);
        }

        $signature = (string) $request->header(self::HEADER, '');

        if ($signature === '') {
            return ApiResponse::error('Missing webhook signature.', 401);
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            return ApiResponse::error('Invalid webhook signature.', 403);
        }

        return $next($request);
    }
}
