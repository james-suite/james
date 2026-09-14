@props([
    'name' => 'avatar',
    'model' => null,
    'previewUrl' => null,
    'labelAdd' => 'Adicionar foto',
    'labelChange' => 'Alterar foto',
    'removeModalName' => null,
])

<div class="flex flex-col items-center gap-4 shrink-0" x-data="{
    previewUrl: {{ $previewUrl ? Js::from($previewUrl) : 'null' }},
    isModalOpen: false,
    cropper: null,
    fileSelected(event) {
        const file = event.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = (e) => {
            this.$refs.cropperImage.src = e.target.result;
            this.isModalOpen = true;
            this.$nextTick(async () => {
                const Cropper = await window.loadCropper();
                if (!this.isModalOpen) {
                    return;
                }

                if (this.cropper) {
                    this.cropper.destroy();
                }
                this.cropper = new Cropper(this.$refs.cropperImage, {
                    aspectRatio: 1,
                    viewMode: 1,
                    autoCropArea: 1,
                });
            });
        };
        reader.readAsDataURL(file);
    },
    closeModal(isCancel = true) {
        this.isModalOpen = false;
        if (this.cropper) {
            this.cropper.destroy();
            this.cropper = null;
        }
        if (isCancel) {
            this.$refs.fileInput.value = '';
        }
    },
    applyCrop() {
        if (!this.cropper) return;

        this.cropper.getCroppedCanvas({
            width: 500,
            height: 500
        }).toBlob((blob) => {
            const file = new File([blob], 'avatar.webp', { type: 'image/webp' });

            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            this.$refs.fileInput.files = dataTransfer.files;

            this.previewUrl = URL.createObjectURL(blob);
            this.closeModal(false);
        }, 'image/webp', 0.9);
    }
}">
    <template x-if="previewUrl">
        <img :src="previewUrl" alt="{{ $model?->name ?? 'Avatar' }}" class="shrink-0 border rounded-md object-cover bg-neutral-200 border-[var(--color-accent)] w-24 h-24 text-4xl">
    </template>
    <template x-if="!previewUrl">
        <x-avatar :model="$model" size="2xl" />
    </template>

    <div class="flex flex-col items-center gap-2">
        <label class="cursor-pointer">
            <span class="text-sm font-medium text-accent hover:text-accent/80 transition-colors">
                {{ $previewUrl ? $labelChange : $labelAdd }}
            </span>
            <input type="file" x-ref="fileInput" name="{{ $name }}" class="hidden" accept="image/jpeg,image/png,image/webp,image/heic,image/heif" @change="fileSelected">
        </label>

        @if($removeModalName)
            <x-modal.trigger :name="$removeModalName">
                <x-button type="button" color="danger-ghost" class="text-sm font-medium">Remover foto</x-button>
            </x-modal.trigger>
        @endif
    </div>

    <x-error name="{{ $name }}" />

    <div x-show="isModalOpen" x-transition.opacity x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @keydown.escape.window="closeModal">
        <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full overflow-hidden flex flex-col" @click.away="closeModal">
            <div class="p-4 border-b flex justify-between items-center">
                <h3 class="font-bold text-neutral-800">Recortar Foto</h3>
                <button type="button" class="text-neutral-400 hover:text-neutral-600 cursor-pointer" @click="closeModal">
                    <x-heroicon-o-x-mark class="size-5" />
                </button>
            </div>
            <div class="bg-neutral-100 flex justify-center items-center overflow-hidden h-[60vh]">
                <img x-ref="cropperImage" class="max-w-full max-h-full block">
            </div>
            <div class="p-4 border-t bg-neutral-50 flex justify-end gap-2">
                <x-button type="button" color="outline" class="bg-white cursor-pointer" @click="closeModal">Cancelar</x-button>
                <x-button type="button" class="cursor-pointer" @click="applyCrop">
                    <x-heroicon-o-check class="size-4" /> Cortar e Aplicar
                </x-button>
            </div>
        </div>
    </div>
</div>
