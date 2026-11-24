@props([
    'href' => '#',
    'current' => false,
])

<a href="{{ $href }}" {{ $attributes->class([
        // Classes base, sempre aplicadas
        'rounded-lg min-h-11 lg:min-h-8 gap-3 flex relative items-center w-full text-base lg:text-sm [&>svg]:size-5 lg:[&>svg]:size-4 px-3 text-start transition-colors motion-duration-quick',
        // Classes para o estado ATIVO
        'bg-white border border-neutral-200 text-neutral-900 font-medium' => $current,
        // Classes para o estado INATIVO
        'text-neutral-600 hover:bg-neutral-200/60 hover:text-neutral-900' => !$current,
    ]) }}>
    {{ $slot }}
</a>