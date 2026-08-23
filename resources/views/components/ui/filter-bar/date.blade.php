@props(['name' => '', 'title' => '', 'value' => ''])

<div class="relative flex items-center w-full sm:w-auto"
     x-data="{ empty: {{ $value ? 'false' : 'true' }} }">

    <input
        type="date"
        name="{{ $name }}"
        value="{{ $value }}"
        @change="empty = !$event.target.value"
        {{ $attributes->merge(['class' => 'w-full sm:w-auto bg-transparent border-0 py-2 sm:py-1.5 pl-7 pr-2 text-sm text-neutral-600 focus:outline-none focus:ring-0 focus:bg-neutral-100 rounded-md cursor-pointer transition-colors [&::-webkit-calendar-picker-indicator]:opacity-0 [&::-webkit-calendar-picker-indicator]:absolute [&::-webkit-calendar-picker-indicator]:inset-0 [&::-webkit-calendar-picker-indicator]:w-full [&::-webkit-calendar-picker-indicator]:h-full [&::-webkit-calendar-picker-indicator]:cursor-pointer']) }}
    >

    @if($title)
        <span x-show="empty"
              class="pointer-events-none absolute inset-0 flex items-center gap-1.5 pl-2.5 pr-2 text-sm text-neutral-400 select-none whitespace-nowrap bg-white rounded-md"
              aria-hidden="true">
            <x-heroicon-o-calendar-days class="size-3.5 shrink-0" />
            {{ $title }}
        </span>
    @endif

</div>

