<?php

use App\Contracts\CampaignRepositoryInterface;
use App\Services\CampaignService;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    $repository = app(CampaignRepositoryInterface::class);
    $service = app(CampaignService::class);

    foreach ($repository->getDueForDispatch() as $campaign) {
        $service->dispatch($campaign);
    }
})->everyMinute();
