@php
    $colors = [
        '#64748b', '#ef4444', '#f97316', '#f59e0b', '#eab308',
        '#84cc16', '#22c55e', '#10b981', '#14b8a6', '#06b6d4',
        '#0ea5e9', '#3b82f6', '#6366f1', '#8b5cf6', '#a855f7',
        '#d946ef', '#ec4899', '#f43f5e'
    ];

    $suggestedIcons = [
        'heroicon-o-credit-card', 'heroicon-o-chart-bar', 'heroicon-o-academic-cap', 'heroicon-o-book-open',
        'heroicon-o-computer-desktop', 'heroicon-o-device-phone-mobile', 'phosphor-airplane-tilt', 'phosphor-bus',
        'heroicon-o-truck', 'heroicon-o-tv', 'heroicon-o-shield-check', 'heroicon-o-key',
        'heroicon-o-cake', 'heroicon-o-ticket', 'phosphor-poker-chip', 'phosphor-paw-print',
        'phosphor-baby', 'heroicon-o-scissors', 'phosphor-sneaker', 'phosphor-armchair',
        'phosphor-plant', 'phosphor-coffee', 'phosphor-pizza', 'heroicon-o-briefcase',
        'heroicon-o-building-office', 'heroicon-o-building-storefront', 'heroicon-o-cpu-chip', 'heroicon-o-beaker',
        'heroicon-o-camera', 'heroicon-o-musical-note'
    ];
@endphp

<div class="grid grid-cols-1 lg:grid-cols-11 gap-4 sm:gap-6"
    x-data="{
        open: false,
        selectedColor: '{{ old('color_hex', $tag->color_hex ?? '#64748b') }}',
        selectedIcon: '{{ old('icon', $tag->icon ?? 'heroicon-o-tag') }}',
        colors: {{ json_encode($colors) }},
        suggestedIcons: {{ json_encode($suggestedIcons) }},
        customIconHtml: '',
        
        fetchCustomIcon() {
            if (!this.suggestedIcons.includes(this.selectedIcon) && this.selectedIcon.length > 3) {
                fetch('{{ route('ui.icons.show', ['name' => '__ICON_NAME__']) }}'.replace('__ICON_NAME__', this.selectedIcon))
                    .then(res => res.ok ? res.text() : Promise.reject('Failed to fetch'))
                    .then(html => this.customIconHtml = html)
                    .catch(() => this.customIconHtml = '');
            } else {
                this.customIconHtml = '';
            }
        }
    }"
    x-init="
        $watch('selectedIcon', () => fetchCustomIcon());
        fetchCustomIcon();
    ">
    
    <!-- Hidden Inputs -->
    <input type="hidden" name="color_hex" x-model="selectedColor">
    <input type="hidden" name="icon" x-model="selectedIcon">

    <!-- Coluna da Esquerda: Informações Principais -->
    <div class="lg:col-span-5 flex flex-col gap-4 sm:gap-6">
        <div class="bg-white rounded-xl border border-neutral-200 shadow-sm p-4 sm:p-6 flex flex-col gap-4 sm:gap-6">
            <!-- Preview da Tag -->
            <div class="flex items-center justify-center p-8 bg-neutral-50 rounded-xl border border-neutral-100">
                <div class="relative focus:outline-none rounded-md">
                    <template x-if="suggestedIcons.includes(selectedIcon)">
                        <div class="w-full h-full flex items-center justify-center">
                            @foreach($suggestedIcons as $icon)
                                <div x-show="selectedIcon === '{{ $icon }}'">
                                    <x-avatar :icon="$icon" size="xl" class="border-transparent! text-white! shadow-sm transition-all" x-bind:style="`background-color: ${selectedColor};`" />
                                </div>
                            @endforeach
                        </div>
                    </template>

                    <div x-show="!suggestedIcons.includes(selectedIcon)">
                        <template x-if="customIconHtml">
                            <div class="shrink-0 flex items-center justify-center font-medium w-16 h-16 text-2xl border-transparent text-white shadow-sm transition-all rounded-md" x-bind:style="`background-color: ${selectedColor};`">
                                <div x-html="customIconHtml" class="w-[65%] h-[65%] flex items-center justify-center [&>svg]:w-full [&>svg]:h-full"></div>
                            </div>
                        </template>
                        <template x-if="!customIconHtml">
                            <x-avatar icon="heroicon-o-question-mark-circle" size="xl" class="border-transparent! text-white! shadow-sm transition-all" x-bind:style="`background-color: ${selectedColor};`" />
                        </template>
                    </div>
                </div>
            </div>

            <!-- Nome da Tag -->
            <div>
                <x-form-input name="name" label="Nome da Tag" :value="old('name', $tag->name ?? '')" required placeholder="Ex: Casa, Carro, Alimentação..." oninput="document.getElementById('preview-text').innerText = this.value || 'Nova Tag';" />
            </div>

            <!-- Cores -->
            <div>
                <label class="block text-sm font-medium text-neutral-700 mb-2">Cor da Tag</label>
                <div class="flex flex-wrap gap-2">
                    <template x-for="color in colors" :key="color">
                        <button type="button" 
                            @click="selectedColor = color"
                            class="w-10 h-10 rounded-full shrink-0 border-2 transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2"
                            :class="selectedColor === color ? 'border-accent shadow-md scale-110' : 'border-transparent shadow-sm hover:scale-105'"
                            x-bind:style="`background-color: ${color};`">
                        </button>
                    </template>
                    
                    <div class="h-10 w-px bg-neutral-200 mx-1"></div>

                    <x-color-picker 
                        x-model="selectedColor" 
                        x-bind:style="!colors.includes(selectedColor) ? 'background-color: ' + selectedColor + ';' : 'background-color: transparent;'" 
                    />
                </div>
            </div>
        </div>
    </div>

    <!-- Coluna da Direita: Ícones -->
    <div class="lg:col-span-6 flex flex-col gap-4 sm:gap-6">
        <div class="bg-white rounded-xl border border-neutral-200 shadow-sm p-4 sm:p-6 h-full flex flex-col gap-4 sm:gap-6">
            <!-- Grid de Ícones Sugeridos -->
            <div class="flex-1">
                <label class="block text-sm font-medium text-neutral-700 mb-2">Selecione um Ícone</label>
                <div class="grid w-full grid-cols-5 lg:grid-cols-10 gap-2">
                    @foreach($suggestedIcons as $icon)
                        <x-tooltip text="{{ $icon }}" class="flex! w-full min-w-0">
                            <button type="button"
                                aria-label="{{ $icon }}"
                                class="button-scale w-full aspect-square cursor-pointer rounded-xl p-2 flex items-center justify-center text-neutral-600 bg-neutral-50 hover:bg-accent/10 hover:text-accent hover:ring-2 hover:ring-accent/50 hover:shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-1 z-0 hover:z-10 relative"
                                @click="selectedIcon = '{{ $icon }}'"
                                :aria-pressed="selectedIcon === '{{ $icon }}'"
                                :class="selectedIcon === '{{ $icon }}' ? 'bg-accent/10! text-accent! ring-2 ring-accent shadow-sm' : 'ring-1 ring-neutral-200'">
                                <x-dynamic-component :component="$icon" class="size-7 shrink-0" />
                            </button>
                        </x-tooltip>
                    @endforeach
                </div>
            </div>

            <!-- Ícone Customizado -->
            <div class="pt-4 border-t border-neutral-100 flex flex-col gap-2">
                <x-form-input name="custom_icon" label="Ícone Customizado (Opcional)" x-model="selectedIcon" placeholder="Ex: heroicon-o-wifi" x-on:input.debounce.300ms="fetchCustomIcon()" />
                <p class="text-xs text-neutral-500 leading-relaxed">
                    Utilizamos a sintaxe do <strong>Blade UI Kit</strong> para renderização. Atualmente, as bibliotecas <a href="https://heroicons.com" target="_blank" class="underline decoration-accent/30 font-medium text-accent hover:text-accent/80 transition-colors">Heroicons</a>, <a href="https://tabler-icons.io/" target="_blank" class="underline decoration-accent/30 font-medium text-accent hover:text-accent/80 transition-colors">Tabler Icons</a> e <a href="https://phosphoricons.com/" target="_blank" class="underline decoration-accent/30 font-medium text-accent hover:text-accent/80 transition-colors">Phosphor Icons</a> estão instaladas no sistema.<br>
                    Consulte a <a href="https://blade-ui-kit.com/blade-icons" target="_blank" class="underline decoration-accent/30 font-medium text-accent hover:text-accent/80 transition-colors">documentação oficial</a> para buscar ícones (lembre-se de usar o prefixo correspondente, ex: <code>heroicon-o-</code>, <code>tabler-</code> ou <code>phosphor-</code>).
                </p>
            </div>
        </div>
    </div>
</div>
