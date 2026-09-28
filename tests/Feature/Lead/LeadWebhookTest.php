<?php

namespace Tests\Feature\Lead;

use App\Contracts\LeadNotifier;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class LeadWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-webhook-secret';

    private array $payload = [
        'name' => 'Ali Khan',
        'phone' => '+923001234567',
        'email' => 'ali@example.com',
        'source' => 'facebook_ads',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.lead_webhook.secret' => self::SECRET]);
    }

    /**
     * Sends the payload exactly as an external system would: raw JSON body
     * plus an HMAC-SHA256 signature of that body.
     */
    private function sendWebhook(array $payload, ?string $secret = self::SECRET, bool $sign = true): TestResponse
    {
        $body = json_encode($payload);
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

        if ($sign) {
            $headers['HTTP_X_WEBHOOK_SIGNATURE'] = 'sha256='.hash_hmac('sha256', $body, (string) $secret);
        }

        return $this->call('POST', '/api/webhooks/leads', [], [], [], $headers, $body);
    }

    #[Test]
    public function the_webhook_creates_a_new_lead_and_triggers_the_notification(): void
    {
        $this->mock(LeadNotifier::class, function (MockInterface $mock) {
            $mock->shouldReceive('notifyNewLead')
                ->once()
                ->with(Mockery::on(fn (Lead $lead) => $lead->phone === '+923001234567'));
        });

        $this->sendWebhook($this->payload)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Lead received successfully.')
            ->assertJsonPath('data.name', 'Ali Khan')
            ->assertJsonPath('data.source', 'facebook_ads')
            ->assertJsonPath('data.status', 'new');

        $this->assertDatabaseHas('leads', ['phone' => '+923001234567', 'status' => 'new']);
    }

    #[Test]
    public function the_mock_notifier_logs_the_whatsapp_sms_message(): void
    {
        Log::spy();

        $this->sendWebhook($this->payload)->assertCreated();

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(fn (string $message) => $message === 'Mock WhatsApp/SMS notification sent to +923001234567 for lead Ali Khan.');
    }

    #[Test]
    public function the_webhook_always_creates_leads_as_new_and_defaults_the_source(): void
    {
        $this->sendWebhook(['name' => 'Sara', 'phone' => '+923331112233', 'status' => 'closed'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'new')
            ->assertJsonPath('data.source', 'webhook');
    }

    #[Test]
    public function the_webhook_validates_the_payload(): void
    {
        $this->mock(LeadNotifier::class, fn (MockInterface $mock) => $mock->shouldNotReceive('notifyNewLead'));

        $this->sendWebhook(['email' => 'invalid'])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['name', 'phone', 'email']);

        $this->assertDatabaseCount('leads', 0);
    }

    #[Test]
    public function the_webhook_rejects_a_missing_signature(): void
    {
        $this->sendWebhook($this->payload, sign: false)
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Missing webhook signature.');

        $this->assertDatabaseCount('leads', 0);
    }

    #[Test]
    public function the_webhook_rejects_an_invalid_signature(): void
    {
        $this->sendWebhook($this->payload, secret: 'wrong-secret')
            ->assertForbidden()
            ->assertJsonPath('message', 'Invalid webhook signature.');

        $this->assertDatabaseCount('leads', 0);
    }

    #[Test]
    public function the_webhook_is_disabled_when_no_secret_is_configured(): void
    {
        config(['services.lead_webhook.secret' => null]);

        $this->sendWebhook($this->payload)->assertStatus(503);

        $this->assertDatabaseCount('leads', 0);
    }

    #[Test]
    public function a_notification_failure_does_not_lose_the_lead(): void
    {
        $this->mock(LeadNotifier::class, function (MockInterface $mock) {
            $mock->shouldReceive('notifyNewLead')->once()->andThrow(new RuntimeException('Provider down'));
        });

        $this->sendWebhook($this->payload)->assertCreated();

        $this->assertDatabaseHas('leads', ['phone' => '+923001234567']);
    }
}
