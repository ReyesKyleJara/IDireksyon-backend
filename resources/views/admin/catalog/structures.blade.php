@php($groupSection = $kind === 'groups')
<div class="space-y-3">
    @if($errors->getBag($groupSection ? 'structure' : 'fees')->any())
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->getBag($groupSection ? 'structure' : 'fees')->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @foreach($groupSection ? $record->requirementGroups : $record->fees as $item)
        <details class="rounded-lg border border-slate-200 p-4" @if(old('_form')===$kind.'-'.$item->id) open @endif>
            <summary class="cursor-pointer text-sm font-medium">{{ $item->name }} <span class="ml-2 font-normal text-slate-500">{{ $groupSection ? $item->rule_label : ($item->amount === null ? 'Amount unknown' : '₱'.number_format((float)$item->amount,2)).' · '.(\App\Models\CatalogFee::KINDS[$item->kind] ?? $item->kind) }}</span></summary>
            @include('admin.catalog.structure-form', ['item'=>$item])
        </details>
    @endforeach
    <details class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4" @if(old('_form')===$kind.'-new') open @endif>
        <summary class="cursor-pointer text-sm font-semibold text-[#012877]">+ Add {{ $groupSection ? 'requirement group' : 'fee' }}</summary>
        @include('admin.catalog.structure-form', ['item'=>null])
    </details>
</div>
