<?php

namespace App\Services;

use App\Contracts\CampaignRepositoryInterface;
use App\Jobs\SendCampaignEmail;
use App\Models\Campaign;

class CampaignService
{
    public function __construct(
        private readonly CampaignRepositoryInterface $campaigns
    ) {}

    public function dispatch(Campaign $campaign): void
    {
        $locked = $this->campaigns->findDraftForUpdate($campaign->id);

        $this->campaigns->getActiveContactsForCampaign(
            $locked->id,
            500,
            function ($contacts) use ($locked) {
                $now = now();
                $records = $contacts->map(fn ($contact) => [
                    'campaign_id' => $locked->id,
                    'contact_id' => $contact->id,
                    'status' => 'pending',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->toArray();

                $this->campaigns->upsertSends($records, ['campaign_id', 'contact_id']);

                $sends = $this->campaigns->getPendingSends(
                    $locked->id,
                    $contacts->pluck('id')->toArray()
                );

                foreach ($sends as $send) {
                    SendCampaignEmail::dispatch($send);
                }
            }
        );
    }
}
