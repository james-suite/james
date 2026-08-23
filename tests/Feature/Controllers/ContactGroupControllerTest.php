<?php

use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('can list contact groups', function () {
    ContactGroup::factory()->count(3)->create();

    $this->actingAs($this->user)
        ->get(route('contacts.groups.index'))
        ->assertSuccessful()
        ->assertViewIs('contacts.groups.index')
        ->assertViewHas('groups');
});

it('can display the creation screen', function () {
    $this->actingAs($this->user)
        ->get(route('contacts.groups.create'))
        ->assertSuccessful()
        ->assertViewIs('contacts.groups.create')
        ->assertViewHas('allContacts');
});

it('can create a contact group with contacts', function () {
    $contact1 = Contact::factory()->create();
    $contact2 = Contact::factory()->create();

    $this->actingAs($this->user)
        ->post(route('contacts.groups.store'), [
            'name' => 'Amigos',
            'contact_ids' => [$contact1->id, $contact2->id],
        ])
        ->assertRedirect(route('contacts.groups.index'));

    $this->assertDatabaseHas('contact_groups', [
        'name' => 'Amigos',
    ]);

    $group = ContactGroup::where('name', 'Amigos')->first();
    expect($group->contacts)->toHaveCount(2);
    expect($group->contacts->pluck('id')->toArray())->toContain($contact1->id, $contact2->id);
});

it('validates required fields on creation', function (string $field, mixed $value, string $errorKey) {
    $this->actingAs($this->user)
        ->post(route('contacts.groups.store'), [$field => $value])
        ->assertSessionHasErrors($errorKey);
})->with([
    'name is missing' => ['name', null, 'name'],
    'name is too long' => ['name', str_repeat('a', 256), 'name'],
]);

it('can display the edit screen', function () {
    $group = ContactGroup::factory()->create();

    $this->actingAs($this->user)
        ->get(route('contacts.groups.edit', $group))
        ->assertSuccessful()
        ->assertViewIs('contacts.groups.edit')
        ->assertViewHas('group')
        ->assertViewHas('allContacts');
});

it('can update a contact group and sync contacts', function () {
    $group = ContactGroup::factory()->create(['name' => 'Old Name']);

    $contact1 = Contact::factory()->create();
    $contact2 = Contact::factory()->create();

    // Attach contact1 initially
    $group->contacts()->attach($contact1);

    $this->actingAs($this->user)
        ->put(route('contacts.groups.update', $group), [
            'name' => 'New Name',
            'contact_ids' => [$contact2->id], // Replace contact1 with contact2
        ])
        ->assertRedirect(route('contacts.groups.index'));

    $this->assertDatabaseHas('contact_groups', [
        'id' => $group->id,
        'name' => 'New Name',
    ]);

    expect($group->fresh()->contacts)->toHaveCount(1);
    expect($group->fresh()->contacts->first()->id)->toBe($contact2->id);
});

it('can delete a contact group', function () {
    $group = ContactGroup::factory()->create();

    $this->actingAs($this->user)
        ->from(route('contacts.groups.index'))
        ->delete(route('contacts.groups.destroy', $group))
        ->assertRedirect(route('contacts.groups.index'));

    $this->assertDatabaseMissing('contact_groups', [
        'id' => $group->id,
    ]);
});
