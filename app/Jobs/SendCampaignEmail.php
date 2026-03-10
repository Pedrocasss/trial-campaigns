<?php

namespace App\Jobs;

use App\Contracts\EmailSenderInterface;
use App\Enums\CampaignSendStatus;
use App\Models\CampaignSend;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendCampaignEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 60, 300];
    public int $timeout = 30;

    public function __construct(
        private readonly CampaignSend $campaignSend
    ) {}

    public function handle(EmailSenderInterface $sender): void
    {
        $send = $this->campaignSend->load(['contact', 'campaign']);

        if ($send->status === CampaignSendStatus::Sent) {
            return;
        }

        $sender->send(
            $send->contact->email,
            $send->campaign->subject,
            $send->campaign->body
        );

        $this->campaignSend->update(['status' => CampaignSendStatus::Sent]);
    }

    public function failed(\Throwable $exception): void
    {
        $this->campaignSend->update([
            'status' => CampaignSendStatus::Failed,
            'error_message' => Str::limit($exception->getMessage(), 500),
        ]);

        Log::error('Campaign send failed', [
            'send_id' => $this->campaignSend->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
