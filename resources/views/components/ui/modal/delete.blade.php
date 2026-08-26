@props([
    'action' => null, 
    'alpineAction' => null,
    'title' => null,
    'message' => null,
    'itemName' => 'este item', // e.g. "este contato", "esta transação"
    'itemDesc' => null, // e.g. "McDonalds" (static)
    'dynamicItemName' => null, // e.g. "selectedName" (alpine)
    'permanent' => false,
    'buttonText' => 'Excluir',
    'confirmText' => null,
    'triggerVariant' => 'danger-outline',
    'icon' => 'heroicon-o-trash',
    'buttonClass' => '',
    'modalName' => null,
    'description' => null,
    'warning' => null,
])

@php
    $resolvedModalName = $modalName ?? ('delete-modal-' . md5($action . uniqid()));
    
    $defaultTitle = $permanent ? 'Excluir Permanentemente' : 'Excluir Registro';
    
    if ($dynamicItemName) {
        $nameHtml = "\"<span class=\"font-medium text-neutral-900\" x-text=\"{$dynamicItemName}\"></span>\"";
        $defaultMessage = $permanent 
            ? "Tem certeza que deseja excluir {$itemName} {$nameHtml} permanentemente? Esta ação não poderá ser desfeita."
            : "Tem certeza que deseja excluir {$itemName} {$nameHtml}?";
    } elseif ($itemDesc) {
        $nameHtml = "\"<span class=\"font-medium text-neutral-900\">{$itemDesc}</span>\"";
        $defaultMessage = $permanent 
            ? "Tem certeza que deseja excluir {$itemName} {$nameHtml} permanentemente? Esta ação não poderá ser desfeita."
            : "Tem certeza que deseja excluir {$itemName} {$nameHtml}?";
    } else {
        $defaultMessage = $permanent 
            ? "Tem certeza que deseja excluir {$itemName} permanentemente? Esta ação não poderá ser desfeita."
            : "Tem certeza que deseja excluir {$itemName}?";
    }
        
    $displayTitle = $title ?? $defaultTitle;
    $displayMessage = $message ?? $defaultMessage;
    $displayConfirmText = $confirmText ?? ($permanent ? 'Sim, excluir permanentemente' : 'Sim, excluir');
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
    :title="$displayTitle"
    confirmVariant="danger">
    
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
        @method('DELETE')
        <x-button type="submit" color="red" class="w-full sm:w-auto">
            {{ $displayConfirmText }}
        </x-button>
    </form>
</x-modal>
