<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDefaultFinancialTagsRequest;
use App\Http\Requests\StoreFinancialTagRequest;
use App\Http\Requests\UpdateFinancialTagRequest;
use App\Models\FinancialTag;
use BladeUI\Icons\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FinancialTagController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $tags = FinancialTag::query()
            ->when(request('search'), fn ($query, $search) => $query->search($search, ['name']))
            ->withCount(['transactions', 'transactionItems'])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $allDefaultTags = config('finance.default_tags', []);
        $existingTagNames = FinancialTag::whereIn('name', array_column($allDefaultTags, 'name'))->pluck('name')->toArray();
        $availableDefaultTags = array_filter($allDefaultTags, fn ($tag) => ! in_array($tag['name'], $existingTagNames));

        return view('finance.tags.index', compact('tags', 'availableDefaultTags'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('finance.tags.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFinancialTagRequest $request): RedirectResponse
    {
        FinancialTag::create($request->validated());

        return redirect()
            ->route('financial.tags.index')
            ->with('success', 'Tag criada com sucesso.');
    }

    /**
     * Store multiple default tags in storage.
     */
    public function storeDefaults(StoreDefaultFinancialTagsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $allDefaultTags = collect(config('finance.default_tags', []))->keyBy('name');

        foreach ($validated['tags'] as $tagName) {
            if ($allDefaultTags->has($tagName)) {
                $tagData = $allDefaultTags->get($tagName);

                FinancialTag::firstOrCreate(
                    ['name' => $tagData['name']],
                    [
                        'icon' => $tagData['icon'],
                        'color_hex' => $tagData['color_hex'],
                        'is_protected' => false,
                    ]
                );
            }
        }

        return redirect()
            ->route('financial.tags.index')
            ->with('success', 'Tags padrão adicionadas com sucesso.');
    }

    public function fetchIcon(string $name)
    {
        try {
            $svg = app(Factory::class)->svg($name);
            if ($svg) {
                return response($svg->toHtml())->header('Content-Type', 'image/svg+xml');
            }
        } catch (\Exception $e) {
            return response($e->getMessage(), 500);
        }

        return response('Icon not found', 404);
    }

    /**
     * Display the specified resource.
     */
    public function show(FinancialTag $financialTag): View
    {
        $financialTag->loadCount(['transactions', 'transactionItems']);

        return view('finance.tags.show', compact('financialTag'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(FinancialTag $financialTag): View|RedirectResponse
    {
        if ($financialTag->is_protected) {
            return redirect()
                ->route('financial.tags.index')
                ->with('error', 'Tags protegidas não podem ser editadas.');
        }

        return view('finance.tags.edit', compact('financialTag'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFinancialTagRequest $request, FinancialTag $financialTag): RedirectResponse
    {
        $financialTag->update($request->validated());

        return redirect()
            ->route('financial.tags.show', $financialTag)
            ->with('success', 'Tag atualizada com sucesso.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FinancialTag $financialTag): RedirectResponse
    {
        if ($financialTag->is_protected) {
            return back()->with('error', 'Esta tag é padrão do sistema e não pode ser excluída.');
        }

        if ($financialTag->transactions()->exists() || $financialTag->transactionItems()->exists()) {
            return back()->with('error', 'Esta tag não pode ser excluída pois possui transações vinculadas.');
        }

        $financialTag->delete();

        return redirect()
            ->route('financial.tags.index')
            ->with('success', 'Tag excluída com sucesso.');
    }
}
