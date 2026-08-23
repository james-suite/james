<?php

use App\Models\Contact;
use App\Models\SettlementGroup;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Storage::fake('attachments');
    $this->actingAs(User::factory()->create());
});

it('serves allowed attachments inline through signed URLs', function () {
    $group = SettlementGroup::factory()->create();
    $media = $group
        ->addMedia(UploadedFile::fake()->createWithContent(
            'receipt.pdf',
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF"
        ))
        ->toMediaCollection('attachments');

    $unsignedUrl = route('attachments.download', [$media, $media->file_name]);
    $signedUrl = URL::signedRoute(
        'attachments.download',
        [$media, $media->file_name]
    );
    $otherMedia = SettlementGroup::factory()->create()
        ->addMedia(UploadedFile::fake()->createWithContent(
            'other.pdf',
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF"
        ))
        ->toMediaCollection('attachments');
    $tamperedUrl = str_replace(
        "/attachments/{$media->id}/",
        "/attachments/{$otherMedia->id}/",
        $signedUrl
    );

    $this->get($unsignedUrl)->assertForbidden();
    $this->get($tamperedUrl)->assertForbidden();

    $response = $this->get($signedUrl)->assertSuccessful();

    expect($response->headers->get('content-disposition'))->toStartWith('inline;');
});

it('rejects signed attachments from unsupported parent models', function () {
    $contact = Contact::factory()->create();
    $media = $contact
        ->addMedia(UploadedFile::fake()->image('private.png'))
        ->toMediaCollection('attachments', 'attachments');

    $signedUrl = URL::signedRoute(
        'attachments.download',
        [$media, $media->file_name]
    );

    $this->get($signedUrl)->assertNotFound();
});
