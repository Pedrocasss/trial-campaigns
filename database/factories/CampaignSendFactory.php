<?php

namespace Database\Factories;

use App\Enums\CampaignSendStatus;
use App\Models\Campaign;
use App\Models\CampaignSend;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

class CampaignSendFactory extends Factory
{
    protected $model = CampaignSend::class;

    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'contact_id'  => Contact::factory(),
            'status'      => fake()->randomElement(CampaignSendStatus::cases()),
        ];
    }
}
