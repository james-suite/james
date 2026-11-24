<x-accordion id="password-rules-panel" head-class="flex items-center justify-between gap-4" class="mb-6 rounded-xl border border-neutral-200 bg-white px-4 py-3 text-neutral-800">
    <x-slot name="trigger">
        <div class="flex items-center">
            <x-heroicon-o-information-circle class="mr-4 h-7 w-7 shrink-0 text-accent" />
            <p class="font-bold">Regras para a senha</p>
        </div>
        <x-heroicon-m-chevron-down class="t-acc-chevron size-5 shrink-0 text-neutral-400" aria-hidden="true" />
    </x-slot>

    <div class="pt-3">
        <ul class="list-inside list-disc text-sm">
            <li>Mínimo de 8 caracteres e máximo de 64 caracteres</li>
            <li>Deve conter pelo menos uma letra maiúscula e uma minúscula</li>
            <li>Deve conter pelo menos um número</li>
            <li>Deve conter pelo menos um símbolo</li>
            <li>Não deve ser uma senha comprometida</li>
        </ul>
    </div>
</x-accordion>
