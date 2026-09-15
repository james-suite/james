@php
    $hasInitialToast = session()->has('toast') || session()->has('success') || session()->has('error') || session()->has('warning');

    if (session()->has('toast')) {
        $toast = session('toast');
        $initialType = $toast['type'] ?? 'info';
        $initialMessage = $toast['message'] ?? '';
    } elseif (session()->has('error')) {
        $initialType = 'error';
        $initialMessage = session('error');
    } elseif (session()->has('warning')) {
        $initialType = 'warning';
        $initialMessage = session('warning');
    } else {
        $initialType = 'success';
        $initialMessage = session('success', '');
    }
@endphp

<div class="fixed top-0 inset-x-0 flex items-start justify-center lg:justify-end p-4 z-[60] pointer-events-none">

        <div x-data="{
                show: {{ $hasInitialToast ? 'true' : 'false' }},
                progress: 100,
                message: {{ Js::from($initialMessage) }},
                type: {{ Js::from($initialType) }},
                timer: null,
                interval: null,
                init() {
                    window.addEventListener('toast', (event) => this.showToast(event.detail));

                    if (this.show) {
                        this.startTimer();
                    }
                },
                showToast(detail) {
                    this.message = detail?.message ?? '';
                    this.type = detail?.type ?? 'info';
                    this.show = true;
                    this.startTimer();
                },
                startTimer() {
                    clearTimeout(this.timer);
                    clearInterval(this.interval);
                    this.progress = 100;
                    this.interval = setInterval(() => {
                        this.progress -= 2;
                        if (this.progress <= 0) {
                            clearInterval(this.interval);
                        }
                    }, 50);
                    this.timer = setTimeout(() => this.show = false, 2500);
                },
                close() {
                    clearTimeout(this.timer);
                    clearInterval(this.interval);
                    this.show = false;
                }
            }" x-show="show" x-cloak
            x-transition:enter="transition motion-ease-smooth-out motion-duration-medium transform"
            x-transition:enter-start="translate-x-full opacity-0" x-transition:enter-end="translate-x-0 opacity-100"
            x-transition:leave="transition motion-ease-smooth-out motion-duration-fast transform is-leaving"
            x-transition:leave-start="translate-x-0 opacity-100" x-transition:leave-end="translate-x-full opacity-0"
            class="t-toast w-full max-w-sm rounded-lg shadow-lg border border-neutral-300 bg-white overflow-hidden pointer-events-auto"
            role="status"
            aria-atomic="true"
            :aria-live="type === 'error' ? 'assertive' : 'polite'">
            <div class="flex items-center gap-4 p-4">
                {{-- Ícone --}}
                <div class="flex items-center justify-center w-8 h-8 bg-green-100 rounded-full shrink-0" x-show="type === 'success'">
                        <x-heroicon-o-check class="w-5 h-5 text-green-600" />
                </div>
                <div class="flex items-center justify-center w-8 h-8 bg-red-100 rounded-full shrink-0" x-show="type === 'error'">
                        <x-heroicon-o-x-mark class="w-5 h-5 text-red-600" />
                </div>
                <div class="flex items-center justify-center w-8 h-8 bg-yellow-100 rounded-full shrink-0" x-show="type === 'warning'">
                        <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-yellow-600" />
                </div>
                <div class="flex items-center justify-center w-8 h-8 bg-blue-100 rounded-full shrink-0" x-show="type !== 'success' && type !== 'error' && type !== 'warning'">
                        <x-heroicon-o-information-circle class="w-5 h-5 text-blue-600" />
                </div>
                {{-- Mensagem e Botão de Fechar --}}
                <div class="flex-1 text-sm font-medium text-neutral-800" x-text="message"></div>
                <button type="button" class="cursor-pointer shrink-0" aria-label="Fechar mensagem" @click="close()">
                    <x-heroicon-o-x-mark class="size-6" />
                </button>
            </div>

            {{-- Barra de Progresso --}}
            <div class="w-full bg-neutral-200 h-1">
                <div class="h-1" :class="{
                    'bg-green-600': type === 'success',
                    'bg-red-600': type === 'error',
                    'bg-yellow-600': type === 'warning',
                    'bg-blue-600': type !== 'success' && type !== 'error' && type !== 'warning'
                }" :style="`width: ${progress}%`"></div>
            </div>
        </div>
</div>
