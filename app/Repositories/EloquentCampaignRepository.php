<?php

namespace App\Repositories;

use App\Contracts\CampaignRepositoryInterface;
use App\Models\Campaign;
use App\Models\CampaignSend;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class EloquentCampaignRepository implements CampaignRepositoryInterface
{
    public function paginateWithStats(int $perPage = 15): LengthAwarePaginator
    {
        return Campaign::with('contactList')->withSendStats()->paginate($perPage);
    }

    public function create(array $data): Campaign
    {
        return Campaign::create($data);
    }

    public function findWithStats(int $id): Campaign
    {
        $campaign = Campaign::findOrFail($id);

        $campaign->load('contactList');
        $campaign->loadCount([
            'sends as pending_count' => fn ($q) => $q->where('status', 'pending'),
            'sends as sent_count' => fn ($q) => $q->where('status', 'sent'),
            'sends as failed_count' => fn ($q) => $q->where('status', 'failed'),
            'sends as total_count',
        ]);

        return $campaign;
    }

    public function findDraftForUpdate(int $id): Campaign
    {
        return DB::transaction(function () use ($id) {
            $campaign = Campaign::where('id', $id)
                ->where('status', 'draft')
                ->lockForUpdate()
                ->firstOrFail();

            $campaign->update(['status' => 'sending']);

            return $campaign;
        });
    }

    public function updateStatus(int $id, string $status): void
    {
        Campaign::where('id', $id)->update(['status' => $status]);
    }

    public function getDueForDispatch(): iterable
    {
        return Campaign::where('status', 'draft')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->cursor();
    }

    public function getActiveContactsForCampaign(int $campaignId, int $chunkSize, callable $callback): void
    {
        $campaign = Campaign::with('contactList')->findOrFail($campaignId);

        $campaign->contactList->contacts()
            ->active()
            ->chunkById($chunkSize, $callback);
    }

    public function upsertSends(array $records, array $uniqueBy): void
    {
        CampaignSend::upsert($records, $uniqueBy);
    }

    public function getPendingSends(int $campaignId, array $contactIds): iterable
    {
        return CampaignSend::where('campaign_id', $campaignId)
            ->whereIn('contact_id', $contactIds)
            ->where('status', 'pending')
            ->get();
    }
}
