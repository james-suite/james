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

<div class="fixed top-0 inset-x-0 flex items-start justify-center lg:justify-end p-4 z-60 pointer-events-none">

        <div x-data="{
                show: {{ $hasInitialToast ? 'true' : 'false' }},
                progress: 100,
                message: {{ Js::from($initialMessage) }},
                type: {{ Js::from($initialType) }},
                durationMs: 4000,
                remainingMs: 4000,
                lastTickAt: null,
                paused: false,
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
                    clearInterval(this.interval);
                    this.durationMs = this.type === 'error' ? 5000 : 4000;
                    this.remainingMs = this.durationMs;
                    this.progress = 100;
                    this.paused = false;
                    this.lastTickAt = Date.now();
                    this.runInterval();
                },
                runInterval() {
                    clearInterval(this.interval);
                    this.interval = setInterval(() => {
                        if (this.paused) return;
                        const now = Date.now();
                        const elapsed = now - (this.lastTickAt ?? now);
                        this.lastTickAt = now;
                        this.remainingMs = Math.max(0, this.remainingMs - elapsed);
                        this.progress = (this.remainingMs / this.durationMs) * 100;
                        if (this.remainingMs <= 0) {
                            this.close();
                        }
                    }, 50);
                },
                pauseTimer() {
                    this.paused = true;
                },
                resumeTimer() {
                    if (!this.show) return;
                    this.paused = false;
                    this.lastTickAt = Date.now();
                },
                close() {
                    clearInterval(this.interval);
                    this.show = false;
                }
            }" x-show="show" x-cloak
            @mouseenter="pauseTimer()"
            @mouseleave="resumeTimer()"
            x-transition:enter="transition motion-ease-smooth-out motion-duration-medium transform"
            x-transition:enter-start="translate-x-full opacity-0" x-transition:enter-end="translate-x-0 opacity-100"
            x-transition:leave="transition motion-ease-smooth-out motion-duration-fast transform is-leaving"
            x-transition:leave-start="translate-x-0 opacity-100" x-transition:leave-end="translate-x-full opacity-0"
            class="t-toast w-full max-w-sm rounded-xl border border-neutral-200 bg-white overflow-hidden pointer-events-auto"
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
