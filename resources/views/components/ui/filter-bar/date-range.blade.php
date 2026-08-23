@props([
    'nameStart'   => 'date_start',
    'nameEnd'     => 'date_end',
    'valueStart'  => '',
    'valueEnd'    => '',
    'titleStart'  => '',
    'titleEnd'    => '',
])

<div class="flex items-center divide-x divide-neutral-200 w-full sm:w-auto"
     x-data="{
         emptyStart: {{ $valueStart ? 'false' : 'true' }},
         emptyEnd:   {{ $valueEnd   ? 'false' : 'true' }},
     }">

    {{-- ── Start ─────────────────────────────────────────────── --}}
    <div class="relative flex items-center">

        <input
            type="date"
            name="{{ $nameStart }}"
            value="{{ $valueStart }}"
            @change="emptyStart = !$event.target.value"
            class="w-full sm:w-auto bg-transparent border-0 py-2 sm:py-1.5 {{ $titleStart ? 'pl-7' : 'px-3' }} pr-2 text-sm text-neutral-600 focus:outline-none focus:ring-0 focus:bg-neutral-100 rounded-md cursor-pointer transition-colors [&::-webkit-calendar-picker-indicator]:opacity-0 [&::-webkit-calendar-picker-indicator]:absolute [&::-webkit-calendar-picker-indicator]:inset-0 [&::-webkit-calendar-picker-indicator]:w-full [&::-webkit-calendar-picker-indicator]:h-full [&::-webkit-calendar-picker-indicator]:cursor-pointer"
        >

        @if($titleStart)
            <span x-show="emptyStart"
                  class="pointer-events-none absolute inset-0 flex items-center gap-1.5 pl-2.5 pr-2 text-sm text-neutral-400 select-none whitespace-nowrap bg-white rounded-l-md"
                  aria-hidden="true">
                <x-heroicon-o-calendar-days class="size-3.5 shrink-0" />
                {{ $titleStart }}
            </span>
        @endif

    </div>

    {{-- ── Separator ─────────────────────────────────────────── --}}
    <span class="flex items-center px-1.5 py-2 sm:py-1.5 text-neutral-300 text-xs select-none shrink-0">→</span>

    {{-- ── End ───────────────────────────────────────────────── --}}
    <div class="relative flex items-center">

        <input
            type="date"
            name="{{ $nameEnd }}"
            value="{{ $valueEnd }}"
            @change="emptyEnd = !$event.target.value"
            class="w-full sm:w-auto bg-transparent border-0 py-2 sm:py-1.5 px-3 text-sm text-neutral-600 focus:outline-none focus:ring-0 focus:bg-neutral-100 rounded-md cursor-pointer transition-colors [&::-webkit-calendar-picker-indicator]:opacity-0 [&::-webkit-calendar-picker-indicator]:absolute [&::-webkit-calendar-picker-indicator]:inset-0 [&::-webkit-calendar-picker-indicator]:w-full [&::-webkit-calendar-picker-indicator]:h-full [&::-webkit-calendar-picker-indicator]:cursor-pointer"
        >

        @if($titleEnd)
            <span x-show="emptyEnd"
                  class="pointer-events-none absolute inset-0 flex items-center pl-3 pr-2 text-sm text-neutral-400 select-none whitespace-nowrap bg-white rounded-r-md"
                  aria-hidden="true">{{ $titleEnd }}</span>
        @endif

    </div>

</div>
