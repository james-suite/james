@props(['name', 'bag' => 'default'])

@error($name, $bag)
    <div id="{{ $name }}-error" role="alert" {{ $attributes->merge(['class' => 'mt-1.5 flex items-center gap-x-2 text-sm text-red-700 animate-shake']) }}>
        {{-- Ícone de Erro --}}
        <x-heroicon-m-exclamation-triangle class="size-5 shrink-0" />

        {{-- Mensagem de Erro --}}
        <span class="font-semibold wrap-break-words">{{ $message }}</span>
    </div>
@enderror
