<?php

use App\Models\Campaign;
use App\Services\CampaignService;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    Campaign::where('status', 'draft')
        ->whereNotNull('scheduled_at')
        ->where('scheduled_at', '<=', now())
        ->each(function (Campaign $campaign) {
            app(CampaignService::class)->dispatch($campaign);
        });
})->everyMinute();
