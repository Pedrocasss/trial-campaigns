<?php

namespace Database\Factories;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\ContactList;
use Illuminate\Database\Eloquent\Factories\Factory;

class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        return [
            'subject'         => fake()->sentence(),
            'body'            => fake()->paragraphs(3, true),
            'contact_list_id' => ContactList::factory(),
            'status'          => CampaignStatus::Draft,
            'scheduled_at'    => fake()->optional(0.6)->dateTimeBetween('now', '+30 days'),
        ];
    }
}
