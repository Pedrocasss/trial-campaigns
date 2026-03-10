<?php

namespace App\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CampaignRepositoryInterface
{
    public function paginateWithStats(int $perPage = 15): LengthAwarePaginator;

    public function create(array $data): mixed;

    public function findWithStats(int $id): mixed;

    public function findDraftForUpdate(int $id): mixed;

    public function getDueForDispatch(): iterable;

    public function getActiveContactsForCampaign(int $campaignId, int $chunkSize, callable $callback): void;

    public function upsertSends(array $records, array $uniqueBy): void;

    public function getPendingSends(int $campaignId, array $contactIds): iterable;
}
