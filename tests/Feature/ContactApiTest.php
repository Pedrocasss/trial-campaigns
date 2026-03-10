<?php

namespace Tests\Feature;

use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_contacts_paginated(): void
    {
        Contact::factory(20)->create();

        $response = $this->getJson('/api/contacts');

        $response->assertOk()
            ->assertJsonPath('meta.total', 20)
            ->assertJsonCount(15, 'data');
    }

    public function test_can_create_contact(): void
    {
        $response = $this->postJson('/api/contacts', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'John Doe')
            ->assertJsonPath('data.email', 'john@example.com');

        $this->assertDatabaseHas('contacts', [
            'email' => 'john@example.com',
            'status' => 'active',
        ]);
    }

    public function test_cannot_create_contact_with_duplicate_email(): void
    {
        Contact::factory()->create(['email' => 'john@example.com']);

        $response = $this->postJson('/api/contacts', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_cannot_create_contact_without_required_fields(): void
    {
        $response = $this->postJson('/api/contacts', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email']);
    }

    public function test_can_unsubscribe_contact(): void
    {
        $contact = Contact::factory()->create(['status' => 'active']);

        $response = $this->postJson("/api/contacts/{$contact->id}/unsubscribe");

        $response->assertOk()
            ->assertJsonPath('data.status', 'unsubscribed');

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'status' => 'unsubscribed',
        ]);
    }
}
