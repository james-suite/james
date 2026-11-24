@props(['contact', 'selectedModel' => 'selectedIds', 'showBalance' => false])

<x-card size="sm" {{ $attributes->merge(['class' => 'flex items-center gap-4 cursor-pointer hover:border-neutral-300 hover:bg-neutral-50/50']) }}
     @click="if(!$event.target.closest('a') && !$event.target.closest('label')) document.getElementById('chk_contact_{{ $contact->id }}').click()"
     x-bind:class="{'ring-2 ring-accent border-transparent': {{ $selectedModel }}.includes(String({{ $contact->id }})) || {{ $selectedModel }}.includes({{ $contact->id }}) }">
    
    <div class="shrink-0 flex items-center">
        <x-form-checkbox name="contact_ids[]" value="{{ $contact->id }}" id="chk_contact_{{ $contact->id }}" x-model="{{ $selectedModel }}" class="m-0!" />
    </div>

    <div class="flex items-center gap-4 flex-1 min-w-0">
        <x-avatar :model="$contact" size="lg" />
        
        <div class="flex-1 min-w-0 flex flex-col items-start text-left">
            <span class="text-base font-semibold text-neutral-900 truncate w-full">{{ $contact->name }}</span>
            
            @if($showBalance)
                @if($contact->net_balance > 0)
                    <span class="text-sm font-medium text-emerald-600 truncate w-full">Me deve {{ formatCurrency($contact->net_balance) }}</span>
                @elseif($contact->net_balance < 0)
                    <span class="text-sm font-medium text-red-600 truncate w-full">Devo {{ formatCurrency(abs($contact->net_balance)) }}</span>
                @else
                    <span class="text-sm text-neutral-500 truncate w-full">{{ formatCurrency(0) }} &mdash; Quite</span>
                @endif
            @endif
        </div>
    </div>
    
    @if($slot->isNotEmpty())
        {{ $slot }}
    @endif
</x-card>
