{{-- Unnamed controls and JSON requests only, so this never submits with the main form. --}}
<div>
    <h4 class="mb-2 block text-sm font-medium">Catalog image</h4>

    @unless($governmentId)
        <p class="text-sm text-slate-500">You can add an image after saving this entry.</p>
    @else
        <div
            x-data="catalogImage({ item: @js(\App\Services\CatalogImages::item($recordKind === 'document' ? 'documents' : 'government-ids', $governmentId)) })"
            @keydown.enter="if ($event.target.tagName !== 'BUTTON') $event.preventDefault()"
        >
            <input x-ref="file" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="fileChosen">

            <p x-show="error && step === 'manage'" x-cloak role="alert" x-text="error" class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"></p>

            {{-- CURRENT IMAGE --}}
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                <div class="h-32 w-32 shrink-0 overflow-hidden rounded-lg bg-slate-100">
                    <template x-if="item.image_url && !item.broken">
                        <img :src="item.image_url" :alt="item.name" x-on:error="item.broken = true" class="h-full w-full object-contain">
                    </template>
                    <template x-if="!item.image_url || item.broken">
                        <div class="flex h-full flex-col items-center justify-center gap-1 p-2 text-center text-slate-400">
                            <svg aria-hidden="true" class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="16" rx="3"></rect><circle cx="9" cy="10" r="2"></circle><path d="m4 18 5-4 4 3 3-2 4 3"></path></svg>
                            <span class="text-xs" x-text="item.broken ? 'Image file is missing' : 'No image yet'"></span>
                        </div>
                    </template>
                </div>
                <div class="min-w-0">
                    <p class="text-sm text-slate-600">Use a square catalog illustration or icon. Transparent PNG artwork works best.</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" @click="pickFile()" class="admin-primary" x-text="item.image_url ? 'Replace' : 'Upload'"></button>
                        <button x-show="item.can_recrop && item.image_url" type="button" @click="recrop()" :disabled="loadingCrop" class="admin-secondary" x-text="loadingCrop ? 'Opening…' : 'Re-crop'"></button>
                        <button x-show="item.image_url" type="button" @click="askRemove()" class="admin-secondary text-red-700">Remove</button>
                    </div>
                </div>
            </div>

            <dialog x-ref="dialog" @cancel.prevent="dismiss()" class="m-auto w-[calc(100vw-2rem)] max-w-3xl rounded-xl bg-white p-0 shadow-xl backdrop:bg-slate-900/50">
            <div class="p-5 sm:p-6">
            <p x-show="error && step !== 'manage'" x-cloak role="alert" x-text="error" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"></p>

            {{-- REMOVE CONFIRMATION --}}
            <div x-show="step === 'remove'" x-cloak class="rounded-lg border border-red-200 bg-red-50 p-4">
                <p class="font-semibold text-red-800">Remove this image?</p>
                <p class="mt-1 text-sm text-red-700">Residents will see the default icon, and the uploaded picture cannot be recovered.</p>
                <div class="mt-4 flex flex-wrap justify-end gap-3">
                    <button type="button" @click="dismiss()" :disabled="busy" class="admin-secondary">Keep image</button>
                    <button type="button" @click="removeImage()" :disabled="busy" class="admin-primary !bg-red-700 hover:!bg-red-800" x-text="busy ? 'Removing…' : 'Yes, remove image'"></button>
                </div>
            </div>

            {{-- CROP --}}
            <div x-show="step === 'crop'" x-cloak>
                <h2 class="mb-4 break-words text-lg font-bold text-slate-900">Crop image for <span x-text="item.name"></span></h2>
                <div class="grid gap-5 md:grid-cols-[1fr_12rem]">
                    <div class="min-w-0">
                        <div class="h-72 overflow-hidden rounded-lg bg-slate-100 sm:h-96">
                            <img x-ref="cropImage" alt="Picture being cropped" class="block max-w-full">
                        </div>
                        <p class="mt-2 text-sm text-slate-500">Drag the picture to position it inside the square.</p>
                        <div class="mt-3 flex items-center gap-3">
                            <button type="button" @click="nudgeZoom(-0.2)" aria-label="Zoom out" class="admin-secondary !px-3 !py-1.5">&minus;</button>
                            <label class="flex-1">
                                <span class="sr-only">Zoom</span>
                                <input type="range" min="1" :max="maxZoom" step="0.01" :value="zoom" @input="setZoom($event.target.value)" class="w-full accent-[#012877]">
                            </label>
                            <button type="button" @click="nudgeZoom(0.2)" aria-label="Zoom in" class="admin-secondary !px-3 !py-1.5">+</button>
                        </div>
                    </div>
                    <div class="flex flex-row gap-4 md:flex-col">
                        <div>
                            <p class="mb-2 text-sm font-semibold text-slate-700">Square preview</p>
                            <div data-crop-preview class="h-32 w-32 overflow-hidden rounded-lg bg-slate-100 md:h-48 md:w-48"></div>
                        </div>
                        <div>
                            <p class="mb-2 text-sm font-semibold text-slate-700">In the app</p>
                            <div data-crop-preview class="h-14 w-14 overflow-hidden rounded-xl bg-slate-100"></div>
                        </div>
                    </div>
                </div>

                <div x-show="busy" x-cloak class="mt-4">
                    <div class="h-1.5 overflow-hidden rounded-full bg-slate-200">
                        <div class="h-full bg-[#012877] transition-all" :style="`width: ${progress}%`"></div>
                    </div>
                    <p class="mt-1 text-sm text-slate-500">Saving…</p>
                </div>

                <div class="mt-5 flex flex-wrap justify-end gap-3">
                    <button type="button" @click="resetCrop()" :disabled="busy" class="admin-secondary mr-auto">Reset</button>
                    <button type="button" @click="cancelCrop()" :disabled="busy" class="admin-secondary">Cancel</button>
                    <button type="button" @click="saveCrop()" :disabled="busy" class="admin-primary">Confirm crop</button>
                </div>
            </div>
            </div>
            </dialog>
        </div>
    @endunless
</div>
