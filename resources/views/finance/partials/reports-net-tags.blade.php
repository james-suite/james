<div>
    <div class="scroll-fade-y space-y-4 max-h-[400px] overflow-y-auto pr-2">
        @forelse($netTags as $index => $item)
            <x-finance.tag-list-item :index="$index + 1" :item="$item" type="net" :showBar="false" />
        @empty
            <div class="text-sm text-neutral-500 py-2">Nenhuma tag no período.</div>
        @endforelse
    </div>
</div>
