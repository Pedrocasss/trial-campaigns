<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignSend;
use App\Models\Contact;
use App\Models\ContactList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_campaigns_with_stats(): void
    {
        $campaign = Campaign::factory()->create();
        CampaignSend::factory()->count(3)->create([
            'campaign_id' => $campaign->id,
            'status' => 'sent',
        ]);
        CampaignSend::factory()->count(2)->create([
            'campaign_id' => $campaign->id,
            'status' => 'pending',
        ]);

        $response = $this->getJson('/api/campaigns');

        $response->assertOk()
            ->assertJsonPath('data.0.sent_count', 3)
            ->assertJsonPath('data.0.pending_count', 2)
            ->assertJsonPath('data.0.total_count', 5);
    }

    public function test_can_create_campaign(): void
    {
        $list = ContactList::factory()->create();

        $response = $this->postJson('/api/campaigns', [
            'subject' => 'Welcome Email',
            'body' => 'Hello and welcome!',
            'contact_list_id' => $list->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.subject', 'Welcome Email');

        $this->assertDatabaseHas('campaigns', [
            'subject' => 'Welcome Email',
            'status' => 'draft',
        ]);
    }

    public function test_can_show_campaign_with_stats(): void
    {
        $campaign = Campaign::factory()->create();
        CampaignSend::factory()->count(2)->create([
            'campaign_id' => $campaign->id,
            'status' => 'failed',
        ]);

        $response = $this->getJson("/api/campaigns/{$campaign->id}");

        $response->assertOk()
            ->assertJsonPath('data.failed_count', 2)
            ->assertJsonPath('data.total_count', 2);
    }

    public function test_can_dispatch_draft_campaign(): void
    {
        $list = ContactList::factory()->create();
        $contacts = Contact::factory(3)->create(['status' => 'active']);
        $list->contacts()->attach($contacts->pluck('id'));

        $campaign = Campaign::factory()->create([
            'contact_list_id' => $list->id,
            'status' => 'draft',
        ]);

        $response = $this->postJson("/api/campaigns/{$campaign->id}/dispatch");

        $response->assertOk()
            ->assertJsonPath('message', 'Campaign dispatch started.');

        $this->assertDatabaseHas('campaigns', [
            'id' => $campaign->id,
            'status' => 'sending',
        ]);

        $this->assertDatabaseCount('campaign_sends', 3);
    }

    public function test_cannot_dispatch_non_draft_campaign(): void
    {
        $campaign = Campaign::factory()->create(['status' => 'sending']);

        $response = $this->postJson("/api/campaigns/{$campaign->id}/dispatch");

        $response->assertUnprocessable()
            ->assertJsonPath('error', 'Campaign must be in draft status.');
    }

    public function test_dispatch_only_sends_to_active_contacts(): void
    {
        $list = ContactList::factory()->create();
        $active = Contact::factory(2)->create(['status' => 'active']);
        $unsubscribed = Contact::factory(1)->create(['status' => 'unsubscribed']);
        $list->contacts()->attach($active->pluck('id'));
        $list->contacts()->attach($unsubscribed->pluck('id'));

        $campaign = Campaign::factory()->create([
            'contact_list_id' => $list->id,
            'status' => 'draft',
        ]);

        $this->postJson("/api/campaigns/{$campaign->id}/dispatch");

        $this->assertDatabaseCount('campaign_sends', 2);
    }

    public function test_dispatch_is_idempotent(): void
    {
        $list = ContactList::factory()->create();
        $contacts = Contact::factory(3)->create(['status' => 'active']);
        $list->contacts()->attach($contacts->pluck('id'));

        $campaign = Campaign::factory()->create([
            'contact_list_id' => $list->id,
            'status' => 'draft',
        ]);

        $this->postJson("/api/campaigns/{$campaign->id}/dispatch");

        $campaign->update(['status' => 'draft']);
        $this->postJson("/api/campaigns/{$campaign->id}/dispatch");

        $this->assertDatabaseCount('campaign_sends', 3);
    }
}
