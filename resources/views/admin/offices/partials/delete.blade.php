{{--
    Delete button and confirmation for one office. Super admins only.
    $office: the office. $blocked: why it cannot be deleted, or null.
--}}
@can('delete-catalog-records')
    <div x-data class="inline-block">
        <button type="button" @click="$refs.dialog.showModal()" class="admin-secondary !border-red-200 !text-red-700 hover:!bg-red-50" aria-label="Delete {{ $office->name }}">Delete</button>

        <dialog x-ref="dialog" class="m-auto w-[calc(100vw-2rem)] max-w-lg rounded-xl bg-white p-0 text-left shadow-xl backdrop:bg-slate-900/50">
            <div class="p-5 sm:p-6">
                <h2 class="break-words text-lg font-bold text-slate-900">Delete {{ $office->name }}?</h2>

                @if($blocked)
                    <p role="alert" class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">{{ $blocked }}</p>
                    @if(! empty($records))
                        <ul class="mt-3 space-y-1 text-sm">
                            @foreach($records as $record)
                                <li><a href="{{ $record['url'] }}" class="font-semibold text-blue-800 underline">{{ $record['name'] }}</a> <span class="text-slate-500">({{ $record['type'] }})</span></li>
                            @endforeach
                        </ul>
                    @endif
                    <div class="mt-5 flex justify-end">
                        <button type="button" @click="$refs.dialog.close()" class="admin-secondary">Close</button>
                    </div>
                @else
                    <p class="mt-4 text-sm text-slate-700">The office and its opening hours will be removed from the CMS. No ID or document uses it, so residents will not notice a change.</p>
                    <p class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm font-semibold text-red-800">This cannot be undone.</p>
                    <form method="POST" action="{{ route('admin.offices.destroy', $office) }}" class="mt-5 flex flex-wrap justify-end gap-3">
                        @csrf
                        @method('DELETE')
                        <button type="button" @click="$refs.dialog.close()" class="admin-secondary">Cancel</button>
                        <button type="submit" class="admin-primary !bg-red-700 hover:!bg-red-800">Delete permanently</button>
                    </form>
                @endif
            </div>
        </dialog>
    </div>
@endcan
