<?php

namespace Database\Seeders;

use App\Http\Requests\Admin\GovernmentIdChecklistRequest;
use App\Models\Agency;
use App\Models\ContentChangeLog;
use App\Models\GovernmentId;
use App\Services\GovernmentIdApplicationGuide;
use App\Services\GovernmentIdChecklists;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Explicit research seed: fill missing sections without replacing CMS edits. */
class NationalIdSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(file_get_contents(resource_path('data/national-id-import.json')), true, 512, JSON_THROW_ON_ERROR);
        $guide = app(GovernmentIdApplicationGuide::class);
        $checklists = app(GovernmentIdChecklists::class);
        $guide->read(new Request(['application_guide_payload' => json_encode(array_map(
            fn ($row) => Arr::except($row, ['groups']), $data['scenarios'],
        ), JSON_THROW_ON_ERROR)]));
        $scenarios = array_map(fn ($row) => [
            ...GovernmentIdChecklistRequest::validateForCreation(Arr::except($row, ['steps'])),
            'steps' => $row['steps'],
        ], $data['scenarios']);

        DB::transaction(function () use ($data, $scenarios, $guide, $checklists) {
            $ids = GovernmentId::query()->lockForUpdate()->get()->filter(
                fn ($id) => in_array($this->normalize($id->name), array_map($this->normalize(...), $data['aliases']), true),
            );
            if ($ids->count() > 1) throw new RuntimeException('Multiple National ID records match. Resolve the ambiguity in the CMS before seeding.');
            $id = $ids->first() ?? new GovernmentId(['name' => $data['basic']['name']]);
            $wasNew = ! $id->exists;
            $changed = false;

            // Shared agencies and office records must not be overwritten by research imports.
            if (! $id->agency_id) {
                $agencies = Agency::query()->lockForUpdate()->get()->filter(fn ($agency) =>
                    $this->normalize($agency->name) === $this->normalize($data['agency']['name'])
                    || $this->normalize($agency->acronym ?? '') === 'psa');
                if ($agencies->count() > 1) throw new RuntimeException('Multiple PSA agencies match. Resolve the ambiguity before seeding.');
                $agency = $agencies->first() ?? Agency::create($data['agency']);
                $id->agency_id = $agency->id;
            }
            foreach ($data['basic'] as $field => $value) {
                if (blank($id->getAttribute($field))) $id->setAttribute($field, $value);
            }
            if (! str_contains($id->official_sources ?? '', '[NATIONAL_ID_RESEARCH_V1]')) {
                $id->official_sources = trim(($id->official_sources ?? '')."\n\n".$data['review_notice']."\n".implode("\n", $data['sources']));
            }
            if (! $id->exists || $id->isDirty()) { $id->save(); $changed = true; }

            if (! $id->fees()->exists() && blank($id->fee) && blank($id->fee_type)) {
                foreach ($data['fees'] as $order => $fee) $id->fees()->create([...$fee, 'sort_order' => $order]);
                $changed = true;
            } else $this->command?->line('KEEP existing National ID fees.');

            foreach ($scenarios as $row) {
                $identity = Arr::only($row, ['application_type', 'application_type_custom', 'applicant_type', 'applicant_type_custom', 'min_age', 'max_age']);
                $sets = $id->requirementSets()->lockForUpdate()->get()->filter(function ($set) use ($identity) {
                    foreach ($identity as $field => $value) {
                        if ($set->getAttribute($field) !== $value) return false;
                    }
                    return true;
                });
                if ($sets->count() > 1) throw new RuntimeException('Duplicate National ID scenario: '.$row['application_type'].' / '.$row['applicant_type']);
                $set = $sets->first();
                if (! $set || (! $set->groups()->exists() && $row['groups'] !== [])) {
                    $set = $checklists->save($id, Arr::except($row, ['steps']), $set ? (string) $set->id : null);
                    $changed = true;
                }
                if (! $set->applicationSteps()->exists()) {
                    $guide->sync($id, [[...Arr::except($row, ['groups']), 'id' => $set->id]]);
                    $changed = true;
                } else $this->command?->line('KEEP existing guide: '.$set->display_label);
            }

            if ($changed) ContentChangeLog::create([
                'user_id' => null, 'action' => $wasNew ? 'created' : 'updated',
                'entity_type' => 'government_id', 'entity_id' => $id->id, 'entity_name' => $id->name,
                'changed_fields' => ['national_id_research_import'], 'created_at' => now(),
            ]);
            $this->command?->info(($changed ? 'Seeded' : 'Preserved').' National ID #'.$id->id.' — /admin/government-ids/'.$id->id.'/edit');
        });
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)));
    }
}
