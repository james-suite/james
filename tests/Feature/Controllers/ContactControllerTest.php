<?php

use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('can list contacts', function () {
    Contact::factory()->count(3)->create();

    $this->actingAs($this->user)
        ->get(route('contacts.index'))
        ->assertSuccessful()
        ->assertViewIs('contacts.index')
        ->assertViewHas('contacts');
});

it('can display the creation screen', function () {
    $this->actingAs($this->user)
        ->get(route('contacts.create'))
        ->assertSuccessful()
        ->assertViewIs('contacts.create');
});

it('does not register the obsolete contact categories route', function () {
    expect(Route::has('contacts.categories'))->toBeFalse();
});

it('can create a contact', function () {
    $contactData = [
        'name' => 'John Doe',
        'relationship_category' => 'Friend',
        'birthdate' => '1990-01-01',
        'phones' => [
            ['label' => 'Mobile', 'value' => '123456789'],
        ],
        'emails' => [
            ['label' => 'Personal', 'value' => 'john@example.com'],
        ],
        'notes' => 'Some notes',
    ];

    $this->actingAs($this->user)
        ->post(route('contacts.store'), $contactData)
        ->assertRedirect();

    $this->assertDatabaseHas('contacts', [
        'name' => 'John Doe',
        'relationship_category' => 'Friend',
        'notes' => 'Some notes',
    ]);
});

it('validates required fields on creation', function (string $field, mixed $value, string $errorKey) {
    $this->actingAs($this->user)
        ->post(route('contacts.store'), [$field => $value])
        ->assertSessionHasErrors($errorKey);
})->with([
    'name is missing' => ['name', null, 'name'],
    'name is too long' => ['name', str_repeat('a', 256), 'name'],
    'email value is missing' => ['emails', [['label' => 'Work', 'value' => '']], 'emails.0.value'],
    'phone value is missing' => ['phones', [['label' => 'Mobile', 'value' => '']], 'phones.0.value'],
]);

it('can display the edit screen', function () {
    $contact = Contact::factory()->create();

    $this->actingAs($this->user)
        ->get(route('contacts.edit', $contact))
        ->assertSuccessful()
        ->assertViewIs('contacts.edit')
        ->assertViewHas('contact');
});

it('can update a contact', function () {
    $contact = Contact::factory()->create(['name' => 'Old Name']);

    $this->actingAs($this->user)
        ->put(route('contacts.update', $contact), [
            'name' => 'New Name',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('contacts', [
        'id' => $contact->id,
        'name' => 'New Name',
    ]);
});

it('can sync and clear contact groups', function () {
    $contact = Contact::factory()->create();
    $groups = ContactGroup::factory()->count(2)->create();

    $this->actingAs($this->user)
        ->post(route('contacts.groups.sync', $contact), [
            'group_ids' => $groups->pluck('id')->all(),
        ])
        ->assertRedirect();

    expect($contact->groups()->pluck('contact_groups.id')->all())
        ->toEqualCanonicalizing($groups->pluck('id')->all());

    $this->actingAs($this->user)
        ->post(route('contacts.groups.sync', $contact))
        ->assertRedirect();

    expect($contact->groups()->exists())->toBeFalse();
});

it('validates contact group ids before syncing', function () {
    $contact = Contact::factory()->create();
    $group = ContactGroup::factory()->create();

    $this->actingAs($this->user)
        ->post(route('contacts.groups.sync', $contact), [
            'group_ids' => [$group->id, $group->id, 999999],
        ])
        ->assertSessionHasErrors(['group_ids.1', 'group_ids.2']);

    expect($contact->groups()->exists())->toBeFalse();
});

it('can soft delete a contact', function () {
    $contact = Contact::factory()->create();

    $this->actingAs($this->user)
        ->delete(route('contacts.destroy', $contact))
        ->assertRedirect(route('contacts.index'));

    $this->assertSoftDeleted('contacts', [
        'id' => $contact->id,
    ]);
});

it('can list trashed contacts', function () {
    $contact = Contact::factory()->create();
    $contact->delete();

    $this->actingAs($this->user)
        ->get(route('contacts.trashed'))
        ->assertSuccessful()
        ->assertViewIs('contacts.trashed');
});

it('can restore a trashed contact', function () {
    $contact = Contact::factory()->create();
    $contact->delete();

    $this->actingAs($this->user)
        ->patch(route('contacts.restore', $contact))
        ->assertRedirect();

    $this->assertNotSoftDeleted('contacts', [
        'id' => $contact->id,
    ]);
});

it('can force delete a contact', function () {
    $contact = Contact::factory()->create();
    $contact->delete();

    $this->actingAs($this->user)
        ->delete(route('contacts.force', $contact))
        ->assertRedirect(route('contacts.trashed'));

    $this->assertDatabaseMissing('contacts', [
        'id' => $contact->id,
    ]);
});

it('can upload and save an avatar', function () {
    Storage::fake('media');

    $file = UploadedFile::fake()->image('avatar.jpg');

    $this->actingAs($this->user)
        ->post(route('contacts.store'), [
            'name' => 'Jane Doe',
            'avatar' => $file,
        ])
        ->assertRedirect();

    $contact = Contact::where('name', 'Jane Doe')->first();

    expect($contact->fresh()->getFirstMedia('avatar'))->not->toBeNull();
})->skip(! extension_loaded('gd'), 'GD extension is not installed.');

it('can remove an avatar', function () {
    Storage::fake('media');

    $contact = Contact::factory()->create();
    $file = UploadedFile::fake()->image('avatar.jpg');
    $contact->saveAvatar($file);

    expect($contact->fresh()->getFirstMedia('avatar'))->not->toBeNull();

    $this->actingAs($this->user)
        ->delete(route('contacts.destroy-avatar', $contact))
        ->assertRedirect();

    expect($contact->fresh()->getFirstMedia('avatar'))->toBeNull();
})->skip(! extension_loaded('gd'), 'GD extension is not installed.');
