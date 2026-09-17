@php
    $groupForm = $kind === 'groups';
    $formKey = $kind.'-'.($item?->id ?? 'new');
    $bag = $groupForm ? 'structure' : 'fees';
    $restore = $errors->getBag($bag)->any() && old('_form') === $formKey;
    $fieldValue = function ($key, $default = '') use ($restore, $item) {
        $v = $restore ? old($key, $default) : ($item?->$key ?? $default);
        return is_scalar($v) ? (string) $v : '';
    };
@endphp
<form method="POST" action="{{ route('admin.catalog.'.$kind.'.'.($item ? 'update' : 'store'), array_filter([$resource, $record->id, $item?->id])) }}" class="mt-4 space-y-4"
    @submit="if (contentDirty && !confirm('You have unsaved general changes. Continue and discard those changes?')) $event.preventDefault()">
    @csrf @if($item) @method('PUT') @endif
    <input type="hidden" name="_form" value="{{ $formKey }}">
    <x-admin.field :name="'name'" :id="$formKey.'-name'" :label="$groupForm ? 'Group name' : 'Fee name'" :value="$fieldValue('name')" required maxlength="255" />
    @if($groupForm)
        <div><label for="{{ $formKey }}-match" class="mb-2 block text-sm font-medium">How many are needed?</label><select id="{{ $formKey }}-match" name="match_rule" class="admin-input">@foreach(\App\Models\RequirementGroup::MATCH_RULES as $key=>$text)<option value="{{ $key }}" @selected($fieldValue('match_rule','all')===$key)>{{ $text }}</option>@endforeach</select></div>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-admin.field name="minimum_count" :id="$formKey.'-minimum'" label="Minimum (for at least N)" type="number" min="1" max="100" :value="$fieldValue('minimum_count',1)" />
            <x-admin.field name="position" :id="$formKey.'-position'" label="Display order" type="number" min="0" max="1000" :value="$fieldValue('position',0)" />
        </div>
        <x-admin.field name="condition_notes" :id="$formKey.'-conditions'" label="Applies only in certain cases? (optional)" type="textarea" :value="$fieldValue('condition_notes')" maxlength="5000" hint="Describe the condition from your source. Conditional groups remain for manual review until applicant rules are supported." />
    @else
        <x-admin.field name="amount" :id="$formKey.'-amount'" label="Amount (PHP)" type="number" min="0" max="99999999.99" step="0.01" :value="$fieldValue('amount')" hint="Leave blank if unknown. Enter 0 only when confirmed free." />
        <div><label for="{{ $formKey }}-kind" class="mb-2 block text-sm font-medium">Charge type</label><select id="{{ $formKey }}-kind" name="kind" class="admin-input">@foreach(\App\Models\CatalogFee::KINDS as $key=>$text)<option value="{{ $key }}" @selected($fieldValue('kind','required')===$key)>{{ $text }}</option>@endforeach</select></div>
        <x-admin.field name="choice_group" :id="$formKey.'-choices'" label="Alternative set name" :value="$fieldValue('choice_group')" maxlength="255" hint="For alternatives, use the same set name for mutually exclusive choices, such as Processing option. They will not be added together." />
        <x-admin.field name="notes" :id="$formKey.'-notes'" label="Conditions / source notes" type="textarea" :value="$fieldValue('notes')" maxlength="5000" />
    @endif
    <button class="admin-primary" type="submit">{{ $item ? 'Save' : 'Add' }} {{ $groupForm ? 'group' : 'fee' }}</button>
</form>
@if($item)
<form method="POST" action="{{ route('admin.catalog.'.$kind.'.destroy', [$resource,$record->id,$item->id]) }}" class="mt-3"
    @submit="if (!confirm(contentDirty ? 'Remove this item and discard unsaved general changes?' : 'Remove this item?')) $event.preventDefault()">
    @csrf @method('DELETE')<button class="text-xs text-red-700 underline" type="submit">Remove {{ $groupForm ? 'empty group' : 'fee' }}</button>
</form>
@endif
