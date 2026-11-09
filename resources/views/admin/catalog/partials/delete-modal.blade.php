@can('delete-catalog-records')
    <div x-data="catalogDelete({ url: @js(route($recordRoutes.'.delete-impact', $governmentId)) })">
        <button type="button" @click="open()" class="admin-secondary !border-red-200 !text-red-700 hover:!bg-red-50">Delete</button>

        <dialog x-ref="dialog" class="m-auto w-[calc(100vw-2rem)] max-w-lg rounded-xl bg-white p-0 text-left shadow-xl backdrop:bg-slate-900/50">
            <div class="p-5 sm:p-6">
                <h2 class="break-words text-lg font-bold text-slate-900">Delete {{ $governmentId->name }}?</h2>

                <p x-show="loading" class="mt-4 text-sm text-slate-500">Checking where this is used…</p>
                <p x-show="failed" x-cloak role="alert" class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">This entry's usage could not be checked, so it can't be deleted right now. Close this and try again.</p>

                <template x-if="impact">
                    <div class="mt-4 space-y-3 text-sm text-slate-700">
                        <template x-for="section in impact.sections" :key="section.title">
                            <p><strong x-text="section.title" class="font-semibold text-slate-900"></strong> <span x-text="section.body"></span></p>
                        </template>
                    </div>
                </template>

                <p x-show="impact" x-cloak class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm font-semibold text-red-800">This cannot be undone.</p>

                <form method="POST" action="{{ route($recordRoutes.'.destroy', $governmentId) }}" class="mt-5 flex flex-wrap justify-end gap-3">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="_catalog_revision" value="{{ \App\Services\CatalogRevision::token($governmentId) }}">
                    <button type="button" @click="close()" class="admin-secondary">Cancel</button>
                    <button type="submit" :disabled="!impact" class="admin-primary !bg-red-700 hover:!bg-red-800 disabled:opacity-50">Delete permanently</button>
                </form>
            </div>
        </dialog>
    </div>
@endcan
