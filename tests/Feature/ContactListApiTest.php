<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactListApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_contact_lists_with_count(): void
    {
        $list = ContactList::factory()->create();
        $contacts = Contact::factory(5)->create();
        $list->contacts()->attach($contacts->pluck('id'));

        $response = $this->getJson('/api/contact-lists');

        $response->assertOk()
            ->assertJsonPath('data.0.contacts_count', 5);
    }

    public function test_can_create_contact_list(): void
    {
        $response = $this->postJson('/api/contact-lists', [
            'name' => 'VIP Clients',
        ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'VIP Clients');

        $this->assertDatabaseHas('contact_lists', ['name' => 'VIP Clients']);
    }

    public function test_can_add_contact_to_list(): void
    {
        $list = ContactList::factory()->create();
        $contact = Contact::factory()->create();

        $response = $this->postJson("/api/contact-lists/{$list->id}/contacts", [
            'contact_id' => $contact->id,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('contact_contact_list', [
            'contact_id' => $contact->id,
            'contact_list_id' => $list->id,
        ]);
    }

    public function test_adding_same_contact_twice_does_not_duplicate(): void
    {
        $list = ContactList::factory()->create();
        $contact = Contact::factory()->create();

        $this->postJson("/api/contact-lists/{$list->id}/contacts", [
            'contact_id' => $contact->id,
        ]);

        $this->postJson("/api/contact-lists/{$list->id}/contacts", [
            'contact_id' => $contact->id,
        ]);

        $this->assertDatabaseCount('contact_contact_list', 1);
    }
}
