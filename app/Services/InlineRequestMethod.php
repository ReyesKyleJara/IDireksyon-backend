<?php

namespace App\Services;

use App\Http\Controllers\Admin\Concerns\PreparesCatalogEntry;
use App\Http\Requests\Admin\GovernmentIdChecklistRequest;
use App\Models\ContentChangeLog;
use App\Models\Document;
use App\Models\GovernmentId;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** A single transaction over the existing CMS records; no parallel content store. */
class InlineRequestMethod
{
    use PreparesCatalogEntry;

    public static function present(Document|GovernmentId $owner, $set = null): array
    {
        $metadata = $set?->only(['application_type', 'application_type_custom', 'applicant_type', 'applicant_type_custom', 'min_age', 'max_age'])
            ?? ['application_type' => $owner instanceof Document ? 'copy' : 'new', 'applicant_type' => 'all'];
        $checklist = $set ? GovernmentIdChecklists::present($set) : ['groups' => []];
        $names = $set ? $set->groups->flatMap->items->mapWithKeys(fn ($item) => [$item->id => $item->display_name])->all() : [];
        foreach ($checklist['groups'] as &$group) {
            unset($group['condition_label']);
            if (isset($group['ways'])) {
                foreach ($group['ways'] as &$way) foreach ($way['items'] as &$item) unset($item['name']);
                unset($way, $item);
            } else {
                foreach ($group['items'] as &$item) unset($item['name']);
                unset($item);
            }
        }
        unset($group);
        $guide = $set ? collect(app(GovernmentIdApplicationGuide::class)->present($owner))->firstWhere('id', $set->id) : null;
        return [
            'id' => $set?->id, 'metadata' => $metadata,
            'channel' => array_replace(['channel' => '', 'provider' => '', 'portal_url' => '', 'preparation' => '', 'application_guide_text' => '', 'processing' => '', 'fulfillment' => [], 'appointment' => 'unknown', 'fee_ids' => $set ? null : [], 'office_ids' => $set ? null : []], $set?->request_channel ?? []),
            'groups' => $checklist['groups'], 'steps' => $guide['steps'] ?? [], 'names' => $names,
            'fees' => $owner->fees()->with('options')->get()->filter(fn ($fee) => $set && (!isset($set->request_channel['fee_ids']) || in_array($fee->id, $set->request_channel['fee_ids'], true)))->map(function ($fee) use ($owner, $set) {
                $shared = $owner->requirementSets->contains(fn ($other) => $other->id !== $set?->id && (!isset($other->request_channel['fee_ids']) || in_array($fee->id, $other->request_channel['fee_ids'], true)));
                $shared = $shared || collect(CatalogSpecialCases::all($owner))->contains(fn ($case) => in_array($fee->id, $case['fee_ids'] ?? [], true));
                return [...CatalogEncoding::fee($fee), 'shared' => $shared];
            })->values()->all(),
        ];
    }

    public function save(Request $request, Document|GovernmentId $owner): array
    {
        $input = $request->validate(['inline_payload' => ['required', 'json', 'max:2000000']]);
        $draft = json_decode($input['inline_payload'], true, 64);
        Validator::make(['draft' => $draft], ['draft' => ['required', 'array:id,channel,groups,steps,fees,metadata,names'], 'draft.id' => ['nullable', 'integer'], 'draft.channel' => ['required', 'array'], 'draft.groups' => ['present', 'array'], 'draft.steps' => ['present', 'array'], 'draft.fees' => ['present', 'array', 'list', 'max:100'], 'draft.fees.*' => ['array:id,label,type,amount_min,amount_max,currency,description,notes,is_optional,options,shared'], 'draft.fees.*.id' => ['nullable', 'integer', 'distinct']])->validate();
        return DB::transaction(function () use ($owner, $draft) {
            $set = !empty($draft['id']) ? $owner->requirementSets()->lockForUpdate()->findOrFail($draft['id']) : null;
            $saved = self::present($owner, $set);
            // Metadata is authoritative on the server, including legacy transactions and ages.
            $metadata = $saved['metadata'];
            $channel = array_replace($saved['channel'], Arr::only($draft['channel'], ['channel', 'provider', 'portal_url', 'preparation', 'application_guide_text', 'processing', 'fulfillment', 'fee_ids', 'office_ids']));
            Validator::make($channel, ['provider' => ['required', 'string', 'max:255']])->validate();
            $channel['label'] = $saved['channel']['label'] ?? $channel['provider'];
            $channel = CatalogEncoding::channel($channel, $owner);

            $requirementsChanged = !$set || $draft['groups'] != $saved['groups'];
            $guideChanged = !$set || $draft['steps'] != $saved['steps'];
            // Unchanged legacy structures must remain editable without re-encoding them.
            $checklist = null;
            if ($requirementsChanged) {
                // Validate with the existing checklist rules, including structured alternatives.
                $checklistRequest = new GovernmentIdChecklistRequest();
                $checklistRequest->replace([...$metadata, 'groups' => $draft['groups']]);
                $validator = Validator::make($checklistRequest->all(), $checklistRequest->rules());
                $checklistRequest->withValidator($validator);
                $checklist = $validator->validate();
            }
            $this->retain($saved['groups'], $draft['groups'], 'requirements');
            foreach ($saved['groups'] as $group) {
                $updated = collect($draft['groups'])->firstWhere('id', $group['id']);
                $this->retain($group['ways'] ?? [], $updated['ways'] ?? [], 'requirements');
                $this->retain($group['items'] ?? [], $updated['items'] ?? [], 'requirements');
                foreach ($group['ways'] ?? [] as $way) {
                    $updatedWay = collect($updated['ways'] ?? [])->firstWhere('id', $way['id']);
                    $this->retain($way['items'], $updatedWay['items'] ?? [], 'requirements');
                }
            }
            $this->retain($saved['steps'], $draft['steps'], 'application steps');
            foreach ($saved['steps'] as $step) {
                $updated = collect($draft['steps'])->firstWhere('id', $step['id']);
                $this->retain($step['blocks'], $updated['blocks'] ?? [], 'guide content');
            }
            $guide = [];
            if ($guideChanged) {
                $guideRequest = new Request(['application_guide_payload' => json_encode([[...$metadata, 'steps' => $draft['steps']]])]);
                $guide = app(GovernmentIdApplicationGuide::class)->read($guideRequest);
            }

            if ($requirementsChanged) $set = app(GovernmentIdChecklists::class)->save($owner, $checklist, $set ? (string) $set->id : null);
            // Never remove unknown legacy channel metadata.
            $set->update(['request_channel' => array_replace($saved['channel'], $channel)]);
            if ($guideChanged) {
                $guide[0]['id'] = $set->id;
                app(GovernmentIdApplicationGuide::class)->sync($owner, $guide);
            }
            $this->saveFees($owner, $set, $draft['fees'], $saved['fees']);
            ContentChangeLog::record($owner, 'updated', ['request_channels']);
            $owner->refresh();
            return ['method' => self::present($owner, $set->fresh()), 'revision' => CatalogRevision::token($owner)];
        });
    }

    private function retain(array $before, array $after, string $section): void
    {
        if (array_diff(array_column($before, 'id'), array_column($after, 'id'))) {
            throw ValidationException::withMessages(['inline_payload' => 'Saved '.$section.' cannot be removed in this editor. Reload and try again.']);
        }
    }

    private function saveFees($owner, $set, array $rows, array $saved): void
    {
        $updates = [];
        $new = [];
        $linkedIds = [];
        foreach ($rows as $row) {
            $row = Arr::except($row, ['shared']);
            if (empty($row['id'])) { $new[] = $row; continue; }
            $original = collect($saved)->firstWhere('id', $row['id']);
            if (!$original) {
                throw ValidationException::withMessages(['fees' => 'This fee is not linked to this provider. Reload before editing it.']);
            }
            $id = (int) $row['id'];
            $shared = $original['shared'];
            $original = Arr::except($original, ['shared']);
            if ($row != $original && $shared) {
                // Editing a shared fee creates an independent provider fee. Keep the original intact.
                $copy = Arr::except($row, ['id']);
                $copy['options'] = array_map(fn ($option) => Arr::except($option, ['id', 'government_id_fee_id', 'document_fee_id', 'created_at', 'updated_at', 'sort_order']), $copy['options'] ?? []);
                $new[] = $copy;
            } else {
                $linkedIds[] = $id;
                if ($row != $original) $updates[$id] = $row;
            }
        }
        if ($updates || $new) {
            // Always retain all reusable records; removed rows are unlinked, never deleted.
            $changes = [...array_values($updates), ...$new];
            $this->validateCatalogEntry(new Request(['name' => $owner->name, 'fees' => $changes]));
            $preparedChanges = $this->prepareFeeRows($changes);
            $normalizedUpdates = collect($preparedChanges)->filter(fn ($row) => !empty($row['id']))->keyBy('id');
            $newRows = collect($preparedChanges)->filter(fn ($row) => empty($row['id']))->values()->all();
            $allSaved = $owner->fees()->with('options')->get()->map(fn ($fee) => CatalogEncoding::fee($fee));
            $prepared = [...$allSaved->map(fn ($row) => $normalizedUpdates->get($row['id']) ?? $row)->all(), ...$newRows];
            $before = $owner->fees()->pluck('id')->all();
            if ($new) {
                // Materialize legacy all-fee scopes before adding provider-only entries.
                foreach ($owner->requirementSets()->where('id', '!=', $set->id)->get() as $other) {
                    if (!isset($other->request_channel['fee_ids'])) $other->update(['request_channel' => [...($other->request_channel ?? []), 'fee_ids' => $before]]);
                }
            }
            $this->syncFees($owner, $prepared, true, array_map('intval', array_keys($updates)));
            $linkedIds = [...$linkedIds, ...array_values(array_diff($owner->fees()->pluck('id')->all(), $before))];
        }
        // An explicit empty list means no applicable fees and survives reopening/resaving.
        $set->refresh()->update(['request_channel' => [...$set->request_channel, 'fee_ids' => array_values($linkedIds)]]);
    }
}
