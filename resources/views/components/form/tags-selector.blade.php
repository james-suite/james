@props([
    'label' => '',
    'name' => 'tags',
    'value' => [],
    'primaryValue' => null,
    'options' => [],
    'xName' => null,
    'xValue' => null,
    'xPrimaryValue' => null,
    'xDisablePrimary' => 'false',
])

@php
    $baseName = str_replace(['[', ']'], ['.', ''], $name);
    // Find base primary name, e.g. items[0][tags] -> items[0][primary_tag_id]
    // If it's just 'tags', it becomes 'primary_tag_id'
    $primaryName = str_replace('tags', 'primary_tag_id', $name);
    if (str_ends_with($primaryName, '[]')) {
        $primaryName = substr($primaryName, 0, -2);
    }
@endphp

<div x-data="{
    open: false,
    search: '',
    options: {{ Js::from($options) }},
    selectedIds: {{ $xValue ?: Js::from(is_array($value) ? array_values($value) : []) }} || [],
    primaryId: {{ $xPrimaryValue ?: Js::from($primaryValue) }} || null,

    init() {
        if (Array.isArray(this.selectedIds)) {
            this.selectedIds = this.selectedIds.map(Number);
        }
        if (this.primaryId) {
            this.primaryId = Number(this.primaryId);
        }
    },

    get selectedOptions() {
        return this.options.filter(o => this.selectedIds.includes(o.id));
    },

    get filteredOptions() {
        if (this.search === '') return this.options;
        const s = this.search.toLowerCase();
        return this.options.filter(o => o.name.toLowerCase().includes(s));
    },

    openModal() {
        this.search = '';
        this.open = true;
        this.$nextTick(() => this.$refs.searchInput?.focus());
    },

    closeModal() {
        this.open = false;
    },

    toggleTag(id) {
        const index = this.selectedIds.indexOf(id);
        if (index > -1) {
            this.selectedIds.splice(index, 1);
            if (this.primaryId === id) {
                this.primaryId = this.selectedIds.length > 0 ? this.selectedIds[0] : null;
            }
        } else {
            this.selectedIds.push(id);
            if (!this.primaryId && !({{ $xDisablePrimary === 'false' ? 'false' : $xDisablePrimary }})) {
                this.primaryId = id;
            }
        }
    },

    setPrimary(id) {
        if (this.selectedIds.includes(id) && !({{ $xDisablePrimary === 'false' ? 'false' : $xDisablePrimary }})) {
            this.primaryId = id;
        }
    }
}" class="w-full relative" x-effect="if ({{ $xDisablePrimary === 'false' ? 'false' : $xDisablePrimary }}) { primaryId = null; }">

    <!-- Trigger Button & Label -->
    <div class="grid w-full gap-1.5">
        @if($label)
            <label class="inline-flex items-center text-sm font-semibold text-neutral-700">{{ $label }}</label>
        @endif

        @if(isset($trigger))
            <div class="cursor-pointer" @click="openModal">
                {{ $trigger }}
            </div>
        @else
            <div class="w-full min-h-11 border border-neutral-200 rounded-xl py-2 px-3 bg-white flex flex-wrap gap-2 items-center cursor-pointer hover:border-accent transition-colors" @click="openModal">

                <template x-if="selectedOptions.length === 0">
                    <span class="text-sm text-neutral-400">Selecionar tags...</span>
                </template>

                <template x-for="opt in selectedOptions" :key="opt.id">
                    <span
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border relative group"
                        :style="opt.color_hex ? `background-color: ${opt.color_hex}15; color: ${opt.color_hex}; border-color: ${opt.color_hex}40;` : 'background-color: #f3f4f6; color: #374151; border-color: #e5e7eb;'"
                    >
                        <span x-show="primaryId === opt.id && !({{ $xDisablePrimary === 'false' ? 'false' : $xDisablePrimary }})" class="text-yellow-500 absolute -top-1.5 -right-1.5 bg-white rounded-full border border-yellow-200 p-0.5 shadow-sm">
                            <x-heroicon-s-star class="size-2.5" />
                        </span>
                        <span x-show="opt.svg" x-html="opt.svg" class="shrink-0 flex items-center *:size-3.5"></span>
                        <span x-text="opt.name" style="margin-top: 1px;"></span>
                    </span>
                </template>

                <button type="button" class="ml-auto flex items-center justify-center p-1 rounded-full text-neutral-400 hover:text-accent hover:bg-accent/10 transition-colors cursor-pointer">
                    <x-heroicon-o-plus class="size-4" />
                </button>
            </div>
        @endif

        <template x-for="id in selectedIds" :key="id">
            <input type="hidden" :name="{{ $xName ? $xName : '`' . $name . (str_ends_with($name, '[]') ? '' : '[]') . '`' }}" :value="id">
        </template>
        <template x-if="primaryId && !({{ $xDisablePrimary === 'false' ? 'false' : $xDisablePrimary }})">
            <input type="hidden" :name="{{ $xName ? $xName . '.replace(\'[tags][]\', \'[primary_tag_id]\').replace(\'tags[]\', \'primary_tag_id\')' : '`' . $primaryName . '`' }}" :value="primaryId">
        </template>
        <x-form-error name="{{ $baseName }}" />
    </div>

    <!-- Modal -->
    <template x-teleport="body">
        <div x-show="open" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-neutral-900/50 backdrop-blur-sm transition-opacity" @click="closeModal" x-show="open" x-transition.opacity></div>

            <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                <div x-show="open"
                     x-transition:enter="motion-ease-smooth-out motion-duration-fast"
                     x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:motion-scale-large"
                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="motion-ease-smooth-out motion-duration-quick"
                     x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:motion-scale-large"
                     class="relative transform rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 w-full sm:max-w-3xl flex flex-col max-h-[85vh]">

                    <!-- Header -->
                    <div class="px-6 py-4 border-b border-neutral-100 flex items-center justify-between shrink-0 bg-white rounded-t-2xl">
                        <div>
                            <h3 class="text-lg font-bold text-neutral-900">Selecione as Tags</h3>
                            <p class="text-sm text-neutral-500">Marque as tags relacionadas. Clique na estrela para definir a principal.</p>
                        </div>
                        <x-tooltip text="Fechar" position="top">
                            <button type="button" class="text-neutral-400 hover:text-neutral-600 transition-colors p-1 bg-neutral-50 hover:bg-neutral-100 rounded-full cursor-pointer" @click="closeModal">
                                <x-heroicon-o-x-mark class="size-5" />
                            </button>
                        </x-tooltip>
                    </div>

                    <!-- Search Bar -->
                    <div class="px-6 py-4 bg-neutral-50/50 border-b border-neutral-100 shrink-0">
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 z-10">
                                <x-heroicon-o-magnifying-glass class="size-5 text-neutral-400" />
                            </div>
                            <x-input placeholder="Buscar tags pelo nome..." x-model="search" x-ref="searchInput" class="pl-10" />
                        </div>
                    </div>

                    <!-- Tags Grid -->
                    <div class="p-6 overflow-y-auto flex-1 bg-white">
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                            <template x-for="tag in filteredOptions" :key="tag.id">
                                <button type="button"
                                    @click="toggleTag(tag.id)"
                                    class="relative p-4 rounded-xl border-2 flex flex-col items-center gap-3 transition-all text-center group cursor-pointer"
                                    :class="selectedIds.includes(tag.id) ? 'border-accent bg-accent/5 shadow-sm' : 'border-neutral-200 hover:border-neutral-300 hover:bg-neutral-50'">

                                    <div class="shrink-0 flex items-center justify-center font-medium w-12 h-12 text-2xl border-transparent text-white shadow-sm rounded-full transition-all"
                                         :style="`background-color: ${tag.color_hex || '#64748b'}; opacity: ${selectedIds.includes(tag.id) ? '1' : '0.85'};`">
                                        <div x-html="tag.svg" class="w-[55%] h-[55%] flex items-center justify-center [&>svg]:w-full [&>svg]:h-full"></div>
                                    </div>
                                    <span class="text-sm font-semibold transition-colors" :class="selectedIds.includes(tag.id) ? 'text-accent' : 'text-neutral-700 group-hover:text-neutral-900'" x-text="tag.name"></span>

                                    <!-- Primary Tag indicator -->
                                    <x-tooltip text="Definir como principal" class="absolute! top-2 right-2 z-10" x-show="selectedIds.includes(tag.id) && !({{ $xDisablePrimary === 'false' ? 'false' : $xDisablePrimary }})">
                                        <div @click.stop="setPrimary(tag.id)"
                                             class="p-1 rounded-full transition-colors cursor-pointer"
                                             :class="primaryId === tag.id ? 'text-yellow-500 bg-yellow-100/80 shadow-xs' : 'text-neutral-400 hover:text-yellow-500 hover:bg-yellow-50/70'">
                                            <x-heroicon-s-star class="size-5" x-show="primaryId === tag.id" />
                                            <x-heroicon-o-star class="size-5" x-show="primaryId !== tag.id" />
                                        </div>
                                    </x-tooltip>
                                </button>
                            </template>
                        </div>

                        <div class="py-12 text-center" x-show="filteredOptions.length === 0">
                            <div class="mx-auto size-12 bg-neutral-100 text-neutral-400 rounded-full flex items-center justify-center mb-3">
                                <x-heroicon-o-tag class="size-6" />
                            </div>
                            <p class="text-neutral-500 text-sm">Nenhuma tag encontrada para "<span x-text="search" class="font-semibold text-neutral-700"></span>"</p>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-6 py-4 border-t border-neutral-100 bg-neutral-50 rounded-b-2xl flex flex-col sm:flex-row items-center justify-between gap-4 shrink-0">
                        <a href="{{ route('financial.tags.create') }}" target="_blank" class="text-sm text-accent hover:text-accent/80 font-medium inline-flex items-center gap-1.5 transition-colors">
                            <x-heroicon-o-plus-circle class="size-5" />
                            Criar nova tag
                        </a>
                        <x-button type="button" @click="closeModal" color="primary" class="w-full sm:w-auto">
                            Concluir Seleção
                        </x-button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
