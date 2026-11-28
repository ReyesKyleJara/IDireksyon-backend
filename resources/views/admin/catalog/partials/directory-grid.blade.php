<div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3" data-catalog-grid>
    @forelse($records as $record)
        <a href="{{ route($directoryRoute.'.show', [$record, ...$directoryQuery]) }}"
            class="admin-panel flex flex-col overflow-hidden transition hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#012877]">
            <div class="flex h-40 items-center justify-center bg-slate-100 px-5 text-slate-500">
                @if($record->image_url)
                    <img src="{{ $record->image_url }}" alt="" loading="lazy" class="h-32 w-full object-contain">
                @else
                    <span class="text-xs">No image yet</span>
                @endif
            </div>
            <div class="flex flex-1 flex-col gap-3 p-5">
                <div>
                    <h2 class="break-words text-lg font-bold text-slate-900">
                        {{ \App\Services\CatalogEncoding::displayName($record) }}
                        @if($record->hidden_at)<span class="ml-1 rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700" data-hidden-badge>Hidden</span>@endif
                    </h2>
                    @if($record->agency)
                        <p class="mt-1 break-words text-sm text-slate-600">{{ $record->agency->name }}@if($record->agency->acronym) ({{ $record->agency->acronym }})@endif</p>
                    @elseif(filled($record->issued_by))
                        <p class="mt-1 break-words text-sm text-slate-600">{{ $record->issued_by }}</p>
                    @endif
                </div>
                <p class="text-xs font-medium text-[#012877]">{{ \App\Support\GuidanceMode::label($record->guidance_mode) }}</p>
                <div class="mt-auto border-t border-slate-100 pt-3">
                    @include('admin.catalog.partials.directory-dates')
                </div>
            </div>
        </a>
    @empty
        <div class="admin-panel p-8 text-center sm:col-span-2 xl:col-span-3">
            <p class="font-semibold text-slate-900">{{ $directoryFiltered ? $directoryEmptyFiltered : $directoryEmpty }}</p>
            @if($directoryFiltered)
                <a href="{{ route($directoryRoute.'.index', ['view' => $directoryQuery['view']]) }}" class="admin-secondary mt-4">Reset search and filters</a>
            @else
                <a href="{{ route($directoryRoute.'.create') }}" class="admin-secondary mt-4">{{ $directoryCreateLabel }}</a>
            @endif
        </div>
    @endforelse
</div>
