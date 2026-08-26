@props([
    'action' => null, 
    'alpineAction' => null,
    'title' => 'Restaurar Registro',
    'message' => null,
    'itemName' => 'este item',
    'itemDesc' => null,
    'dynamicItemName' => null,
    'buttonText' => 'Restaurar',
    'confirmText' => 'Sim, restaurar',
    'triggerVariant' => 'outline',
    'icon' => 'heroicon-o-arrow-uturn-left',
    'buttonClass' => 'bg-white hover:bg-neutral-50 text-neutral-600 border-neutral-300',
    'modalName' => null,
    'description' => null,
    'warning' => null,
])

@php
    $resolvedModalName = $modalName ?? ('restore-modal-' . md5($action . uniqid()));
    
    if ($dynamicItemName) {
        $nameHtml = "\"<span class=\"font-medium text-neutral-900\" x-text=\"{$dynamicItemName}\"></span>\"";
        $defaultMessage = "Tem certeza que deseja restaurar {$itemName} {$nameHtml}?";
    } elseif ($itemDesc) {
        $nameHtml = "\"<span class=\"font-medium text-neutral-900\">{$itemDesc}</span>\"";
        $defaultMessage = "Tem certeza que deseja restaurar {$itemName} {$nameHtml}?";
    } else {
        $defaultMessage = "Tem certeza que deseja restaurar {$itemName}?";
    }

    $displayMessage = $message ?? ($defaultMessage . " Ele voltará para a sua lista de registros ativos.");
@endphp

@if(!$modalName)
    <x-modal.trigger name="{{ $resolvedModalName }}">
        @if(isset($trigger))
            {{ $trigger }}
        @else
            <x-button type="button" color="{{ $triggerVariant }}" class="{{ $buttonClass }}">
                @if($icon)
                    <x-dynamic-component :component="$icon" class="size-4" />
                @endif
                {{ $buttonText }}
            </x-button>
        @endif
    </x-modal.trigger>
@endif

<x-modal
    name="{{ $resolvedModalName }}"
    :title="$title"
    confirmVariant="success">

    <x-slot name="content">
        @if(isset($content))
            {{ $content }}
        @else
            <p class="mb-3 text-neutral-900">{!! $displayMessage !!}</p>
            
            @if($description)
                <p class="mb-3 text-neutral-900">{{ $description }}</p>
            @endif

            @if($warning)
                <div class="rounded-md bg-amber-50 p-3 border border-amber-200 text-left">
                    <div class="flex">
                        <div class="shrink-0">
                            <x-heroicon-m-exclamation-triangle class="size-5 text-amber-400" />
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-amber-800">Atenção</h3>
                            <div class="mt-1 text-sm text-amber-700">
                                <p>{{ $warning }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endif
    </x-slot>

    @if($alpineAction)
        <form :action="{!! $alpineAction !!}" method="POST" class="m-0">
    @else
        <form action="{{ $action }}" method="POST" class="m-0">
    @endif
        @csrf
        @method('PATCH')
        <x-button type="submit" color="green" class="w-full sm:w-auto">
            {{ $confirmText }}
        </x-button>
    </form>
</x-modal>
