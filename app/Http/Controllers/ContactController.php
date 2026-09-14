<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\SyncContactGroupsRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Models\Contact;
use App\Models\ContactGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ContactController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $contacts = Contact::query()
            ->select(['id', 'name', 'relationship_category', 'created_at'])
            ->with('media')
            ->when(request('category'), fn ($query, $category) => $query->where('relationship_category', $category))
            ->when(request('group_id'), fn ($query, $groupId) => $query->whereHas('groups', fn ($q) => $q->where('contact_groups.id', $groupId)))
            ->when(request('search'), fn ($query, $search) => $query->search($search, ['name', 'notes']))
            ->latest()
            ->paginate(18)
            ->withQueryString();

        $categories = Contact::relationshipCategories();

        $groups = ContactGroup::orderBy('name')->get();

        $hasTrashed = Contact::onlyTrashed()->exists();

        return view('contacts.index', compact('contacts', 'categories', 'groups', 'hasTrashed'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $categories = Contact::relationshipCategories();

        [$phones, $emails] = $this->formatPhonesAndEmails(
            old('phones', []),
            old('emails', [])
        );

        return view('contacts.create', compact('categories', 'phones', 'emails'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreContactRequest $request): RedirectResponse
    {
        $contact = Contact::create($request->validated());

        if ($request->hasFile('avatar')) {
            $contact->saveAvatar($request->file('avatar'));
        }

        return redirect()
            ->route('contacts.show', $contact)
            ->with('success', 'Contato criado com sucesso.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Contact $contact): View
    {
        $allGroups = ContactGroup::orderBy('name')->get();

        return view('contacts.show', compact('contact', 'allGroups'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Contact $contact): View
    {
        $categories = Contact::relationshipCategories();

        [$phones, $emails] = $this->formatPhonesAndEmails(
            old('phones', $contact->phones ?? []),
            old('emails', $contact->emails ?? [])
        );

        return view('contacts.edit', compact('contact', 'categories', 'phones', 'emails'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateContactRequest $request, Contact $contact): RedirectResponse
    {
        $contact->update($request->validated());

        if ($request->hasFile('avatar')) {
            $contact->saveAvatar($request->file('avatar'));
        }

        return redirect()
            ->route('contacts.show', $contact)
            ->with('success', 'Contato atualizado com sucesso.');
    }

    /**
     * Sync the groups for a contact.
     */
    public function syncGroups(SyncContactGroupsRequest $request, Contact $contact): RedirectResponse
    {
        $contact->groups()->sync($request->validated('group_ids', []));

        return back()->with('success', 'Grupos atualizados com sucesso.');
    }

    /**
     * Remove the specified resource from storage (soft delete).
     */
    public function destroy(Contact $contact): RedirectResponse
    {
        $contact->delete();

        return redirect()
            ->route('contacts.index')
            ->with('success', 'Contato movido para a lixeira.');
    }

    /**
     * Display a listing of trashed resources.
     */
    public function trashed(): View
    {
        $contacts = Contact::onlyTrashed()
            ->select(['id', 'name', 'relationship_category', 'deleted_at'])
            ->with('media')
            ->when(request('category'), fn ($query, $category) => $query->where('relationship_category', $category))
            ->when(request('search'), fn ($query, $search) => $query->search($search, ['name', 'notes']))
            ->latest('deleted_at')
            ->paginate(50)
            ->withQueryString();

        $categories = Contact::onlyTrashed()->relationshipCategories();

        return view('contacts.trashed', compact('contacts', 'categories'));
    }

    /**
     * Restore a trashed resource.
     */
    public function restore(Contact $contact): RedirectResponse
    {
        $contact->restore();

        return redirect()
            ->route('contacts.show', $contact)
            ->with('success', 'Contato restaurado com sucesso.');
    }

    /**
     * Permanently delete a trashed resource.
     */
    public function forceDestroy(Contact $contact): RedirectResponse
    {
        $contact->clearMediaCollection('avatar');
        $contact->forceDelete();

        return redirect()
            ->route('contacts.trashed')
            ->with('success', 'Contato excluído permanentemente.');
    }

    /**
     * Remove the avatar from a contact.
     */
    public function destroyAvatar(Contact $contact): RedirectResponse
    {
        $contact->clearMediaCollection('avatar');

        return redirect()
            ->route('contacts.edit', $contact)
            ->with('success', 'Avatar removido com sucesso.');
    }

    /**
     * Serve the contact's avatar image.
     */
    public function avatar(Contact $contact)
    {
        $media = $contact->getFirstMedia('avatar');

        if (! $media || ! is_file($media->getPath())) {
            abort(404);
        }

        return response()->file($media->getPath());
    }

    /**
     * Format phones and emails array for the view.
     */
    private function formatPhonesAndEmails(mixed $oldPhones, mixed $oldEmails): array
    {
        $phones = collect($oldPhones ?? [])->map(function ($phone) {
            return [
                'label' => is_array($phone) && ! empty($phone['label']) ? $phone['label'] : 'Principal',
                'value' => is_array($phone) ? ($phone['value'] ?? '') : $phone,
            ];
        })->values()->all();

        $emails = collect($oldEmails ?? [])->map(function ($email) {
            return [
                'label' => is_array($email) && ! empty($email['label']) ? $email['label'] : 'Principal',
                'value' => is_array($email) ? ($email['value'] ?? '') : $email,
            ];
        })->values()->all();

        return [$phones, $emails];
    }
}
