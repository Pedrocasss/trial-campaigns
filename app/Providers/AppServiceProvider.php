<?php

namespace App\Providers;

use App\Contracts\CampaignRepositoryInterface;
use App\Contracts\ContactListRepositoryInterface;
use App\Contracts\ContactRepositoryInterface;
use App\Contracts\EmailSenderInterface;
use App\Repositories\EloquentCampaignRepository;
use App\Repositories\EloquentContactListRepository;
use App\Repositories\EloquentContactRepository;
use App\Services\LogEmailSender;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ContactRepositoryInterface::class, EloquentContactRepository::class);
        $this->app->bind(ContactListRepositoryInterface::class, EloquentContactListRepository::class);
        $this->app->bind(CampaignRepositoryInterface::class, EloquentCampaignRepository::class);
        $this->app->bind(EmailSenderInterface::class, LogEmailSender::class);
    }

    public function boot(): void
    {
        //
    }
}
