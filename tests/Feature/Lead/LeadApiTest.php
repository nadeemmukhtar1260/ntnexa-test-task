<?php

namespace Tests\Feature\Lead;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LeadApiTest extends TestCase
{
    use RefreshDatabase;

    private array $validPayload = [
        'name' => 'Ali Khan',
        'phone' => '+923001234567',
        'email' => 'ali@example.com',
        'source' => 'website',
        'status' => 'new',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    #[Test]
    public function it_creates_a_lead(): void
    {
        $this->postJson('/api/leads', $this->validPayload)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Lead created successfully.')
            ->assertJsonPath('data.name', 'Ali Khan')
            ->assertJsonPath('data.status', 'new')
            ->assertJsonStructure(['data' => ['id', 'name', 'phone', 'email', 'source', 'status', 'created_at', 'updated_at']]);

        $this->assertDatabaseHas('leads', ['email' => 'ali@example.com', 'status' => 'new']);
    }

    #[Test]
    public function creating_a_lead_requires_the_mandatory_fields(): void
    {
        $this->postJson('/api/leads', [])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonValidationErrors(['name', 'phone', 'source', 'status']);

        $this->assertDatabaseCount('leads', 0);
    }

    #[Test]
    public function creating_a_lead_rejects_an_invalid_email_and_phone(): void
    {
        $this->postJson('/api/leads', [...$this->validPayload, 'email' => 'not-an-email', 'phone' => 'abc'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'phone']);
    }

    #[Test]
    #[DataProvider('validStatuses')]
    public function it_accepts_every_allowed_status(string $status): void
    {
        $this->postJson('/api/leads', [...$this->validPayload, 'status' => $status])
            ->assertCreated()
            ->assertJsonPath('data.status', $status);
    }

    public static function validStatuses(): array
    {
        return array_map(fn (LeadStatus $s) => [$s->value], LeadStatus::cases());
    }

    #[Test]
    #[DataProvider('invalidStatuses')]
    public function it_rejects_an_invalid_status(mixed $status): void
    {
        $this->postJson('/api/leads', [...$this->validPayload, 'status' => $status])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status' => 'The status must be one of: new, contacted, qualified, closed.',
            ]);
    }

    public static function invalidStatuses(): array
    {
        return [['archived'], ['NEW'], ['lost']];
    }

    #[Test]
    public function it_lists_leads_with_pagination(): void
    {
        Lead::factory()->count(20)->create();

        $this->getJson('/api/leads?per_page=5')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.last_page', 4)
            ->assertJsonStructure(['meta' => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to', 'next_page_url', 'prev_page_url']]);
    }

    #[Test]
    public function it_filters_leads_by_status_and_source(): void
    {
        Lead::factory()->count(3)->create(['status' => LeadStatus::New, 'source' => 'website']);
        Lead::factory()->count(2)->create(['status' => LeadStatus::Qualified, 'source' => 'website']);
        Lead::factory()->count(4)->create(['status' => LeadStatus::New, 'source' => 'facebook']);

        $this->getJson('/api/leads?status=new')->assertOk()->assertJsonPath('meta.total', 7);
        $this->getJson('/api/leads?source=website')->assertOk()->assertJsonPath('meta.total', 5);
        $this->getJson('/api/leads?status=new&source=website')->assertOk()->assertJsonPath('meta.total', 3);
    }

    #[Test]
    public function it_rejects_an_invalid_status_filter(): void
    {
        $this->getJson('/api/leads?status=archived')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    #[Test]
    public function it_shows_a_lead(): void
    {
        $lead = Lead::factory()->create();

        $this->getJson("/api/leads/{$lead->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $lead->id)
            ->assertJsonPath('data.name', $lead->name);
    }

    #[Test]
    public function it_returns_404_for_a_missing_lead(): void
    {
        $expected = ['success' => false, 'message' => 'Lead not found.'];

        $this->getJson('/api/leads/999')->assertNotFound()->assertExactJson($expected);
        $this->putJson('/api/leads/999', ['name' => 'X'])->assertNotFound()->assertExactJson($expected);
        $this->deleteJson('/api/leads/999')->assertNotFound()->assertExactJson($expected);
        $this->getJson('/api/leads/not-a-number')->assertNotFound();
    }

    #[Test]
    public function it_updates_a_lead(): void
    {
        $lead = Lead::factory()->create(['status' => LeadStatus::New]);

        $this->putJson("/api/leads/{$lead->id}", [...$this->validPayload, 'status' => 'contacted'])
            ->assertOk()
            ->assertJsonPath('message', 'Lead updated successfully.')
            ->assertJsonPath('data.status', 'contacted')
            ->assertJsonPath('data.name', 'Ali Khan');

        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'status' => 'contacted']);
    }

    #[Test]
    public function it_partially_updates_a_lead_with_patch(): void
    {
        $lead = Lead::factory()->create(['name' => 'Original', 'status' => LeadStatus::New]);

        $this->patchJson("/api/leads/{$lead->id}", ['status' => 'qualified'])
            ->assertOk()
            ->assertJsonPath('data.status', 'qualified')
            ->assertJsonPath('data.name', 'Original');
    }

    #[Test]
    public function updating_a_lead_validates_the_status(): void
    {
        $lead = Lead::factory()->create(['status' => LeadStatus::New]);

        $this->patchJson("/api/leads/{$lead->id}", ['status' => 'archived', 'name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'name']);

        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'status' => 'new']);
    }

    #[Test]
    public function it_deletes_a_lead(): void
    {
        $lead = Lead::factory()->create();

        $this->deleteJson("/api/leads/{$lead->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Lead deleted successfully.');

        $this->assertDatabaseMissing('leads', ['id' => $lead->id]);
    }
}
