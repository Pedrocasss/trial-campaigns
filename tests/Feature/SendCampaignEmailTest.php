<?php

namespace Tests\Feature;

use App\Contracts\EmailSenderInterface;
use App\Enums\CampaignSendStatus;
use App\Jobs\SendCampaignEmail;
use App\Models\Campaign;
use App\Models\CampaignSend;
use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendCampaignEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_email_and_marks_as_sent(): void
    {
        $contact = Contact::factory()->create(['email' => 'test@example.com']);
        $campaign = Campaign::factory()->create(['subject' => 'Test Subject', 'body' => 'Test Body']);
        $send = CampaignSend::factory()->create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'status' => CampaignSendStatus::Pending,
        ]);

        $sender = $this->mock(EmailSenderInterface::class);
        $sender->shouldReceive('send')
            ->once()
            ->with('test@example.com', 'Test Subject', 'Test Body');

        $job = new SendCampaignEmail($send);
        $job->handle($sender);

        $this->assertDatabaseHas('campaign_sends', [
            'id' => $send->id,
            'status' => CampaignSendStatus::Sent->value,
        ]);
    }

    public function test_skips_already_sent(): void
    {
        $send = CampaignSend::factory()->create(['status' => CampaignSendStatus::Sent]);

        $sender = $this->mock(EmailSenderInterface::class);
        $sender->shouldNotReceive('send');

        $job = new SendCampaignEmail($send);
        $job->handle($sender);
    }

    public function test_failed_marks_send_as_failed(): void
    {
        $send = CampaignSend::factory()->create(['status' => CampaignSendStatus::Pending]);

        $job = new SendCampaignEmail($send);
        $job->failed(new \RuntimeException('SMTP connection refused'));

        $this->assertDatabaseHas('campaign_sends', [
            'id' => $send->id,
            'status' => CampaignSendStatus::Failed->value,
        ]);
    }

    public function test_has_retry_configuration(): void
    {
        $send = CampaignSend::factory()->create();
        $job = new SendCampaignEmail($send);

        $this->assertEquals(3, $job->tries);
        $this->assertEquals([10, 60, 300], $job->backoff);
        $this->assertEquals(30, $job->timeout);
    }
}
