<?php

namespace Database\Factories;

use App\Enums\ContactStatus;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'name'   => fake()->name(),
            'email'  => fake()->unique()->safeEmail(),
            'status' => fake()->randomElement([ContactStatus::Active, ContactStatus::Active, ContactStatus::Active, ContactStatus::Unsubscribed]),
        ];
    }
}
