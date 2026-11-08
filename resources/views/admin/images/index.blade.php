@extends('admin.layouts.app')

@section('title', 'Images | IDireksyon')

@section('page_title', 'Images')

@section('content')

<div x-data="catalogImages({ items: @js($items), open: @js($open) })" class="space-y-5">

    <div>
        <h1 class="text-2xl font-bold text-slate-900">Images</h1>
        <p class="mt-1 text-sm text-slate-500">
            Add a square picture for each ID and document. Residents see it in the app right after you save.
        </p>
    </div>

    <div class="admin-panel flex flex-col gap-3 p-4 md:flex-row md:items-center">
        <label class="flex-1">
            <span class="sr-only">Search by name</span>
            <input x-model="search" type="search" placeholder="Search by name" class="w-full rounded-lg border-slate-300 text-sm">
        </label>
        <label>
            <span class="sr-only">Type</span>
            <select x-model="type" class="w-full rounded-lg border-slate-300 text-sm md:w-48">
                <option value="all">All types</option>
                <option value="government-ids">Government IDs</option>
                <option value="documents">Documents</option>
            </select>
        </label>
        <label>
            <span class="sr-only">Image status</span>
            <select x-model="status" class="w-full rounded-lg border-slate-300 text-sm md:w-40">
                <option value="all">All images</option>
                <option value="with">Has an image</option>
                <option value="without">No image yet</option>
            </select>
        </label>
    </div>

    <p class="text-sm text-slate-500">
        <span x-text="withImageCount"></span> of <span x-text="items.length"></span> have an image
    </p>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
        <template x-for="item in visible" :key="item.key">
            <button type="button" @click="open(item)" class="admin-panel block overflow-hidden text-left transition hover:shadow-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#012877]">
                <div class="aspect-square w-full bg-slate-100">
                    <template x-if="item.image_url && !item.broken">
                        <img :src="item.image_url" :alt="item.name" loading="lazy" x-on:error="item.broken = true" class="h-full w-full object-cover">
                    </template>
                    <template x-if="!item.image_url || item.broken">
                        <div class="flex h-full flex-col items-center justify-center gap-2 p-3 text-center text-slate-400">
                            <svg aria-hidden="true" class="h-10 w-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="16" rx="3"/><circle cx="9" cy="10" r="2"/><path d="m4 18 5-4 4 3 3-2 4 3"/></svg>
                            <span class="text-xs" x-text="item.broken ? 'Image file is missing' : 'No image yet'"></span>
                        </div>
                    </template>
                </div>
                <div class="p-3">
                    <p class="truncate text-sm font-semibold text-slate-900" x-text="item.name" :title="item.name"></p>
                    <p class="mt-0.5 text-xs text-slate-500" x-text="item.type_label"></p>
                </div>
            </button>
        </template>
    </div>

    <p x-show="visible.length === 0" x-cloak class="admin-panel p-8 text-center text-sm text-slate-500">
        Nothing matches your search.
    </p>

    <dialog x-ref="dialog" @cancel.prevent="step === 'crop' ? cancelCrop() : close()" class="m-auto w-[calc(100vw-2rem)] max-w-3xl rounded-xl bg-white p-0 shadow-xl backdrop:bg-slate-900/50">
        <input x-ref="file" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="fileChosen">

        <div class="p-5 sm:p-6">
            <div class="mb-4 flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <h2 class="break-words text-lg font-bold text-slate-900" x-text="active?.name"></h2>
                    <p class="text-sm text-slate-500" x-text="active?.type_label"></p>
                </div>
                <button x-show="step !== 'crop'" type="button" @click="close()" class="admin-secondary shrink-0">Close</button>
            </div>

            <p x-show="error" x-cloak role="alert" x-text="error" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"></p>

            {{-- CURRENT IMAGE --}}
            <div x-show="step === 'manage'" class="flex flex-col gap-5 sm:flex-row">
                <div class="aspect-square w-full shrink-0 overflow-hidden rounded-lg bg-slate-100 sm:w-64">
                    <template x-if="active?.image_url && !active?.broken">
                        <img :src="active.image_url" :alt="active.name" x-on:error="active.broken = true" class="h-full w-full object-cover">
                    </template>
                    <template x-if="!active?.image_url || active?.broken">
                        <div class="flex h-full items-center justify-center p-4 text-center text-sm text-slate-400" x-text="active?.broken ? 'The image file is missing. Please upload it again.' : 'No image yet'"></div>
                    </template>
                </div>
                <div class="flex flex-col gap-3">
                    <p class="text-sm text-slate-600" x-text="active?.image_url ? 'This is the image residents see in the app.' : 'Upload a JPG, PNG, or WebP picture, up to 10 MB. You will crop it into a square before saving.'"></p>
                    <button type="button" @click="pickFile()" class="admin-primary" x-text="active?.image_url ? 'Replace image' : 'Upload image'"></button>
                    <button x-show="active?.can_recrop && active?.image_url" type="button" @click="recrop()" :disabled="loadingCrop" class="admin-secondary" x-text="loadingCrop ? 'Opening…' : 'Re-crop image'"></button>
                    <button x-show="active?.image_url" type="button" @click="step = 'delete'; error = ''" class="admin-secondary text-red-700">Delete image</button>
                    <a :href="active?.details_url" class="text-sm font-medium text-[#012877] hover:underline">View details</a>
                </div>
            </div>

            {{-- DELETE CONFIRMATION --}}
            <div x-show="step === 'delete'" x-cloak class="rounded-lg border border-red-200 bg-red-50 p-4">
                <p class="font-semibold text-red-800">Delete this image?</p>
                <p class="mt-1 text-sm text-red-700">Residents will stop seeing it, and the uploaded picture cannot be recovered.</p>
                <div class="mt-4 flex flex-wrap justify-end gap-3">
                    <button type="button" @click="step = 'manage'" :disabled="busy" class="admin-secondary">Keep image</button>
                    <button type="button" @click="deleteImage()" :disabled="busy" class="admin-primary !bg-red-700 hover:!bg-red-800" x-text="busy ? 'Deleting…' : 'Yes, delete image'"></button>
                </div>
            </div>

            {{-- CROP --}}
            <div x-show="step === 'crop'" x-cloak>
                <div class="grid gap-5 md:grid-cols-[1fr_12rem]">
                    <div class="min-w-0">
                        <div class="h-72 overflow-hidden rounded-lg bg-slate-900 sm:h-96">
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
                            <p class="mb-2 text-sm font-semibold text-slate-700">Final image</p>
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
                    <button type="button" @click="saveCrop()" :disabled="busy" class="admin-primary">Save image</button>
                </div>
            </div>
        </div>
    </dialog>
</div>

@endsection
