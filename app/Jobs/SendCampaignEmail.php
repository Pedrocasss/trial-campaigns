<?php

namespace App\Jobs;

use App\Models\CampaignSend;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendCampaignEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 60, 300];

    public function __construct(
        private readonly CampaignSend $campaignSend
    ) {}

    public function handle(): void
    {
        $send = $this->campaignSend->load(['contact', 'campaign']);

        if ($send->status === 'sent') {
            return;
        }

        $this->sendEmail(
            $send->contact->email,
            $send->campaign->subject,
            $send->campaign->body
        );

        $this->campaignSend->update(['status' => 'sent']);
    }

    public function failed(\Throwable $exception): void
    {
        $this->campaignSend->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);

        Log::error('Campaign send failed', [
            'send_id' => $this->campaignSend->id,
            'error' => $exception->getMessage(),
        ]);
    }

    private function sendEmail(string $to, string $subject, string $body): void
    {
        Log::info("Sending email to {$to}: {$subject}");
    }
}
