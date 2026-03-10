<?php

namespace App\Services;

use App\Jobs\SendCampaignEmail;
use App\Models\Campaign;
use App\Models\CampaignSend;
use Illuminate\Support\Facades\DB;

class CampaignService
{
    public function dispatch(Campaign $campaign): void
    {
        $campaign = DB::transaction(function () use ($campaign) {
            $locked = Campaign::where('id', $campaign->id)
                ->where('status', 'draft')
                ->lockForUpdate()
                ->firstOrFail();

            $locked->update(['status' => 'sending']);

            return $locked;
        });

        $campaign->load('contactList');

        $campaign->contactList->contacts()
            ->active()
            ->chunkById(500, function ($contacts) use ($campaign) {
                $now = now();
                $records = $contacts->map(fn ($contact) => [
                    'campaign_id' => $campaign->id,
                    'contact_id' => $contact->id,
                    'status' => 'pending',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->toArray();

                CampaignSend::upsert($records, ['campaign_id', 'contact_id']);

                $sends = CampaignSend::where('campaign_id', $campaign->id)
                    ->whereIn('contact_id', $contacts->pluck('id'))
                    ->where('status', 'pending')
                    ->get();

                foreach ($sends as $send) {
                    SendCampaignEmail::dispatch($send);
                }
            });
    }
}
