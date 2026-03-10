<?php

namespace App\Services;

use App\Jobs\SendCampaignEmail;
use App\Models\Campaign;
use App\Models\CampaignSend;

class CampaignService
{
    public function dispatch(Campaign $campaign): void
    {
        $campaign->update(['status' => 'sending']);

        $campaign->contactList->contacts()
            ->where('status', 'active')
            ->chunkById(500, function ($contacts) use ($campaign) {
                foreach ($contacts as $contact) {
                    $send = CampaignSend::firstOrCreate(
                        [
                            'campaign_id' => $campaign->id,
                            'contact_id' => $contact->id,
                        ],
                        [
                            'status' => 'pending',
                        ]
                    );

                    if ($send->wasRecentlyCreated) {
                        SendCampaignEmail::dispatch($send);
                    }
                }
            });
    }
}
