<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Models\ContentChangeLog;
use App\Models\Document;
use App\Models\Office;
use App\Models\GovernmentId;
use App\Models\GovernmentIdApplicationStep;
use App\Models\GovernmentIdApplicationStepBlock;
use App\Models\GovernmentIdFee;
use App\Models\GovernmentIdRequirementGroup;
use App\Models\GovernmentIdRequirementItem;
use App\Models\GovernmentIdRequirementSet;
use App\Models\GovernmentIdRequirementWay;
use App\Services\GovernmentIdApplicationGuide;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ImportPassport extends Command
{
    protected $signature = 'idireksyon:import-passport {--apply : Save the previewed import} {--example : Create a standalone Passport CMS example without ID/document directory dependencies} {--id= : Existing Passport record ID when names are ambiguous} {--replace : Replace the selected Passport fields and covered sections; requires --id}';
    protected $description = 'Preview Passport research; use --replace --id to correct existing content';
    private bool $example = false;
    private bool $replace = false;
    private bool $apply = false;
    private bool $changed = false;
    private array $directory = [];

    public function handle(): int
    {
        $this->apply = (bool) $this->option('apply');
        $this->replace = (bool) $this->option('replace');
        $this->example = (bool) $this->option('example');
        $this->changed = false;
        $this->directory = [];
        try {
            if ($this->example && ($this->replace || $this->option('id') !== null)) throw new RuntimeException('Use --example on its own, without --id or --replace.');
            if ($this->replace && ! $this->option('id')) throw new RuntimeException('--replace requires --id so only the selected Passport is changed.');
            $data = json_decode(file_get_contents(resource_path('data/passport-import.json')), true, 512, JSON_THROW_ON_ERROR);
            if ($this->example) {
                $data['research_notice'] = '[PASSPORT_CMS_EXAMPLE_V1] Standalone CMS encoding example. Requirement names are not linked to inventory or sequencing. '.$data['research_notice'];
                $data['research_notes'][] = 'Original supplied text is preserved in resources/data/passport-supplied.txt. Reviewed corrections are documented in resources/data/passport-review.md.';
                foreach ($data['scenarios'] as &$scenario) foreach ($scenario['steps'] as &$step) foreach ($step['blocks'] as &$block) {
                    if ($block['type'] === 'offices') {
                        $text = implode("\n", array_map(fn ($office) => $office['name'].': '.$office['address'].', '.$office['municipality'].'.', $data['offices']));
                        $block = ['type' => 'custom', 'section_title' => 'Where to apply', 'content' => ['body' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text.' Confirm current appointment availability and office hours with DFA.']]]]]]];
                    }
                }
                unset($scenario, $step, $block);
            }
            // Reuse the guide validator before planning or making any changes.
            $guide = array_map(fn ($row) => array_diff_key($row, ['groups' => true]), $data['scenarios']);
            app(GovernmentIdApplicationGuide::class)->read(new Request(['application_guide_payload' => json_encode($guide, JSON_THROW_ON_ERROR)]));
            $this->warn($data['research_notice']);
            $this->info($this->apply ? ($this->example ? 'STANDALONE EXAMPLE — existing Passport and directory records are untouched.' : ($this->replace ? 'REPLACEMENT IMPORT — selected Passport fields and covered sections will be corrected.' : 'IMPORT — existing populated sections are preserved.')) : 'PREVIEW ONLY — no database writes.');
            $work = fn () => $this->import($data);
            $id = $this->apply ? DB::transaction($work) : $work();
            $this->newLine();
            $this->info($this->apply ? 'Import complete. Open /admin/government-ids/'.$id->id.'/edit to review.' : 'Preview complete. To save, run this command with --apply (and the same --example, --id or --replace options if supplied).');
            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            if ($this->apply) $this->warn('The import was rolled back; no partial import was saved.');
            return self::FAILURE;
        }
    }

    private function import(array $data): GovernmentId
    {
        if ($this->example) {
            $name = 'Philippine Passport (ePassport) — CMS Example';
            $existing = GovernmentId::where('name', $name)->get();
            if ($existing->count() > 1) throw new RuntimeException('Multiple CMS examples already exist. No new record was created.');
            if ($existing->isNotEmpty()) {
                $id = $existing->first();
                if (! str_contains($id->official_sources ?? '', '[PASSPORT_CMS_EXAMPLE_V1]')) throw new RuntimeException('That example name is already in use by a different record. No changes made.');
                $this->info('Example already exists at /admin/government-ids/'.$id->id.'/edit. Your edits were preserved.');
                return $id;
            }
            $id = new GovernmentId(['name' => $name]);
        } elseif ($this->option('id') !== null) {
            if (! ctype_digit((string) $this->option('id'))) throw new RuntimeException('--id must be a positive record ID.');
            $id = GovernmentId::query()->lockForUpdate()->findOrFail($this->option('id'));
            if (! str_contains($this->normalize($id->name), 'passport')) throw new RuntimeException('The selected record is not named as a Passport. Nothing was changed.');
        } else {
            $id = $this->match(GovernmentId::all(), $data['passport_aliases'], 'Passport') ?? new GovernmentId(['name' => $data['basic']['name']]);
            if ($id->exists && $this->apply) $id = GovernmentId::query()->lockForUpdate()->findOrFail($id->id);
        }
        $wasNew = ! $id->exists;
        if ($this->replace) {
            $this->line('Replacement covers basic fields, eligibility, validity, processing, legacy summaries, fees, office links, and the four listed scenarios.');
            $this->line('Other applicant/application scenarios and shared ID/document records are not replaced.');
            if ($this->apply) $this->backup($id);
        }
        $this->line(($wasNew ? 'CREATE' : 'REUSE #'.$id->id).' Passport: '.$id->name);
        $agency = ($this->example
            ? Agency::where('name', $data['agency']['name'])->orderBy('id')->first()
            : $this->match(Agency::all(), [$data['agency']['name'], $data['agency']['acronym']], 'DFA agency', ['name', 'acronym'])) ?? new Agency($data['agency']);
        if ($this->replace && $agency->exists && $this->normalize($agency->name) !== $this->normalize($data['agency']['name'])) {
            throw new RuntimeException('The matching DFA agency has a conflicting name. Review the shared agency record before replacement.');
        }
        $this->persist($agency);
        foreach ($data['basic'] as $field => $value) {
            if ($field === 'name' && ! $this->replace) continue;
            $this->fillEmpty($id, $field, $value);
        }
        if ($this->replace || ! $id->agency_id) { $this->line('SET agency: Department of Foreign Affairs'); if ($this->apply) $id->agency_id = $agency->id; }
        else $this->line('KEEP existing agency link.');
        foreach (['eligibility' => 'eligibility', 'validity' => 'validity', 'processing' => 'processing_time'] as $section => $summary) {
            $fields = array_keys($data[$section]);
            $populated = collect($fields)->contains(fn ($field) => filled($id->$field));
            if ($populated && ! $this->replace) { $this->line('KEEP existing '.$section.' section.'); continue; }
            foreach ($data[$section] as $field => $value) $this->fillEmpty($id, $field, $value);
        }
        $notes = $data['research_notice']."\n".implode("\n", $data['research_notes']);
        if ($this->replace) {
            $this->fillEmpty($id, 'official_sources', $notes);
            $this->replaceLegacyFields($id, $data);
        } elseif (! str_contains($id->official_sources ?? '', $data['research_notice'])) {
            $id->official_sources = trim(($id->official_sources ?? '')."\n\n".$notes);
            $this->line('APPEND source review notes.');
        }
        if ($this->example) {
            $this->replaceLegacyFields($id, $data);
            $id->prerequisite_notes = 'CMS example only: items retain their names and choice rules but have no document-inventory or Government-ID sequencing links yet.';
            $id->office_location = implode('; ', array_map(fn ($office) => $office['name'].' ('.$office['municipality'].')', $data['offices']));
        }
        foreach ($data['research_notes'] as $note) $this->line('RESEARCH: '.$note);
        $this->persist($id);

        if ($this->replace) {
            $this->line('REPLACE fees; old: '.json_encode($id->fees()->get()->toArray(), JSON_UNESCAPED_UNICODE));
            if ($this->apply) { $id->fees()->delete(); $this->changed = true; }
        }
        if (! $this->replace && $id->exists && $id->fees()->exists()) $this->line('KEEP existing fee rows.');
        elseif (! $this->replace && filled($id->fee)) $this->line('KEEP existing fee summary; review it before adding structured fees.');
        else {
            foreach ($data['fees'] as $order => $fee) {
                $this->line('ADD fee: '.$fee['label'].' — '.($fee['amount_min'] ?? 'varies').' PHP; '.($fee['notes'] ?? ''));
                if ($this->apply) $this->persist(new GovernmentIdFee(['government_id_id' => $id->id, 'currency' => 'PHP', 'sort_order' => $order, ...$fee]));
            }
            $id->fee = collect($data['fees'])->map(fn ($fee) => $fee['label'].': '.(isset($fee['amount_min']) ? '₱'.number_format($fee['amount_min'], 2) : 'Varies'))->implode('; ');
            $this->persist($id);
        }

        foreach ($data['scenarios'] as $input) {
            $sets = $id->exists ? $id->requirementSets()->where('application_type', $input['application_type'])->where('applicant_type', $input['applicant_type'])->get() : collect();
            if ($sets->count() > 1) throw new RuntimeException('Multiple '.$input['applicant_type'].' / '.$input['application_type'].' scenarios exist. Resolve them in the CMS before importing.');
            $set = $sets->first() ?? new GovernmentIdRequirementSet(['government_id_id' => $id->id, 'application_type' => $input['application_type'], 'applicant_type' => $input['applicant_type']]);
            $this->line('SCENARIO: '.$input['applicant_type'].' / '.$input['application_type']);
            $this->persist($set);
            if ($this->replace && $set->exists) {
                $this->line('  REPLACE old requirements: '.$set->groups()->pluck('title')->implode('; '));
                $this->line('  REPLACE old steps: '.$set->applicationSteps()->pluck('title')->implode('; '));
                if ($this->apply) { $set->groups()->delete(); $set->applicationSteps()->delete(); $this->changed = true; }
            }
            if (! $this->replace && $set->exists && $set->groups()->exists()) $this->line('  KEEP existing requirements (entire checklist).');
            else foreach ($input['groups'] as $order => $groupInput) $this->addRequirement($set, $groupInput, $order);
            if (! $this->replace && $set->exists && $set->applicationSteps()->exists()) $this->line('  KEEP existing guide steps (entire scenario).');
            else foreach ($input['steps'] as $order => $stepInput) {
                $this->line('  ADD step '.($order + 1).': '.$stepInput['title']);
                $step = new GovernmentIdApplicationStep(collect($stepInput)->except('blocks')->all());
                $step->requirement_set_id = $set->id; $step->sort_order = $order;
                $this->persist($step);
                foreach ($stepInput['blocks'] as $blockOrder => $blockInput) {
                    $block = new GovernmentIdApplicationStepBlock($blockInput);
                    $block->application_step_id = $step->id; $block->sort_order = $blockOrder;
                    $this->persist($block);
                }
            }
        }
        if ($this->replace) $this->replaceOfficeLinks($id, $agency, $data['offices'] ?? []);
        if ($this->apply && $this->changed) {
            // Terminal imports have no logged-in CMS user. Do not impersonate staff.
            ContentChangeLog::create([
                'user_id' => null, 'action' => $wasNew ? 'created' : 'updated', 'entity_type' => 'government_id',
                'entity_id' => $id->id, 'entity_name' => $id->name,
                'changed_fields' => [$this->replace ? 'passport_research_replacement' : 'passport_research_import'], 'created_at' => now(),
            ]);
        }
        return $id;
    }

    private function addRequirement(GovernmentIdRequirementSet $set, array $input, int $order): void
    {
        $this->line('  ADD requirement: '.$input['title']);
        if ($input['condition_type'] !== 'always') $this->line('    Applies when: '.($input['condition_custom'] ?? $input['condition_type']));
        $group = new GovernmentIdRequirementGroup(collect($input)->only(['title', 'condition_type', 'condition_custom'])->all());
        $group->requirement_set_id = $set->id; $group->rule = 'all'; $group->sort_order = $order;
        $this->persist($group);
        $ways = $input['ways'] ?? [['required_count' => $input['required_count'], 'items' => $input['items']]];
        foreach ($ways as $wayOrder => $wayInput) {
            $this->line('    '.($wayOrder ? 'OR ' : '').'Choose '.$wayInput['required_count'].' accepted item(s).');
            if (! empty($wayInput['qualification_custom'])) $this->line('    '.$wayInput['qualification_custom']);
            $way = new GovernmentIdRequirementWay(collect($wayInput)->only(GovernmentIdRequirementWay::EDITABLE_FIELDS)->all());
            $way->requirement_group_id = $group->id; $way->sort_order = $wayOrder;
            $this->persist($way);
            foreach ($wayInput['items'] as $index => $entry) {
                $item = new GovernmentIdRequirementItem(collect($entry)->only(['type', 'submission_format', 'copies', 'instructions'])->all());
                $item->requirement_group_id = $group->id; $item->requirement_way_id = $way->id; $item->sort_order = $index;
                if ($this->example || $entry['type'] === 'custom') {
                    $item->type = 'custom';
                    $item->custom_name = $entry['name'];
                }
                else {
                    $class = $entry['type'] === 'document' ? Document::class : GovernmentId::class;
                    $key = $class.':'.$entry['name'];
                    if (! isset($this->directory[$key])) {
                        $record = $this->match($class::all(), [$entry['name'], ...$entry['aliases']], $entry['name']) ?? new $class(['name' => $entry['name']]);
                        $this->line('    '.($record->exists ? 'REUSE #'.$record->id : 'CREATE name-only '.$entry['type']).': '.$record->name);
                        $this->persist($record); $this->directory[$key] = $record;
                    }
                    $field = $entry['type'] === 'document' ? 'document_id' : 'government_id_id';
                    $item->$field = $this->directory[$key]->id;
                }
                $this->line('    Item: '.$entry['name'].'; '.$entry['submission_format'].($entry['copies'] ? '; '.$entry['copies'].' photocopy' : '').($entry['instructions'] ? '; '.$entry['instructions'] : ''));
                $this->persist($item);
            }
        }
    }

    private function backup(GovernmentId $id): void
    {
        $snapshot = $id->fresh()->load(['agency', 'fees', 'requirementSets.groups.items', 'requirementSets.groups.ways', 'requirementSets.applicationSteps.blocks', 'offices.schedules.intervals'])->toArray();
        $path = 'passport-import-backups/passport-'.$id->id.'-'.now()->format('Ymd-His').'-'.Str::uuid().'.json';
        if (! Storage::disk('local')->put($path, json_encode(['saved_at' => now()->toIso8601String(), 'government_id' => $snapshot], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))) {
            throw new RuntimeException('Could not archive existing Passport content. Replacement stopped.');
        }
        $this->line('BACKUP: '.Storage::disk('local')->path($path));
    }

    private function replaceLegacyFields(GovernmentId $id, array $data): void
    {
        foreach (['fee_type', 'fee_min', 'fee_max', 'fee_currency', 'fee_notes', 'office_location', 'office_hours', 'last_verified_at', 'last_verified_by'] as $field) $this->fillEmpty($id, $field, null);
        $summaries = [];
        foreach ($data['scenarios'] as $scenario) {
            $lines = [ucfirst($scenario['applicant_type']).' / '.$scenario['application_type']];
            foreach ($scenario['groups'] as $group) {
                $line = $group['title'];
                if ($group['condition_type'] !== 'always') $line .= ' — Only if: '.($group['condition_custom'] ?? $group['condition_type']);
                $ways = $group['ways'] ?? [['required_count' => $group['required_count'], 'items' => $group['items']]];
                $line .= ': '.implode(' OR ', array_map(fn ($way) => 'choose '.$way['required_count'].' from ['.implode('; ', array_column($way['items'], 'name')).']'.(! empty($way['qualification_custom']) ? ' ('.$way['qualification_custom'].')' : ''), $ways));
                $lines[] = $line;
            }
            $summaries[] = implode("\n", $lines);
        }
        $this->fillEmpty($id, 'requirements', implode("\n\n", $summaries));
        $this->fillEmpty($id, 'prerequisite_notes', 'Use the applicable scenario. Accepted IDs are alternatives; requirements referencing other Government IDs provide potential sequencing paths. Parent IDs and the passport being renewed are not dependencies on obtaining those IDs for the applicant.');
        foreach (['new' => 'application_process', 'renewal' => 'renewal_process', 'replacement' => 'replacement_process'] as $type => $field) {
            $scenario = collect($data['scenarios'])->firstWhere('application_type', $type);
            $lines = [];
            foreach ($scenario['steps'] ?? [] as $index => $step) {
                $texts = [];
                foreach ($step['blocks'] as $block) {
                    if (isset($block['content']['body'])) $texts[] = $this->plainText($block['content']['body']);
                    foreach ($block['content']['items'] ?? [] as $item) $texts[] = $this->plainText($item['body']);
                    if (isset($block['content']['url'])) $texts[] = $block['content']['url'];
                }
                $lines[] = ($index + 1).'. '.$step['title'].': '.implode(' ', $texts);
            }
            $this->fillEmpty($id, $field, implode("\n", $lines));
        }
    }

    private function plainText(array $node): string
    {
        return $node['text'] ?? implode(' ', array_map(fn ($child) => $this->plainText($child), $node['content'] ?? []));
    }

    private function replaceOfficeLinks(GovernmentId $id, Agency $agency, array $offices): void
    {
        $this->line('REPLACE Passport office links. Existing shared office records/hours are not overwritten or deleted.');
        $this->line('Old linked offices: '.$id->offices()->pluck('offices.name')->implode('; '));
        $links = [];
        foreach ($offices as $input) {
            $office = $this->match(Office::all(), [$input['name'], ...$input['aliases']], $input['name']) ?? new Office();
            foreach (['name', 'address', 'municipality', 'province', 'source_url'] as $field) {
                // These records may serve other IDs. Never silently retain a conflicting location.
                if ($office->exists && $field !== 'name' && filled($office->$field) && $field !== 'source_url' && $this->normalize($office->$field) !== $this->normalize($input[$field])) {
                    throw new RuntimeException('Office #'.$office->id.' has conflicting '.$field.'. Review its shared directory record before replacing Passport links.');
                }
                if (blank($office->$field)) $office->$field = $input[$field];
            }
            if ($office->agency_id && $office->agency_id !== $agency->id) throw new RuntimeException('Office #'.$office->id.' is linked to another agency. Review it first.');
            if (! $office->exists) { $office->agency_id = $agency->id; $office->notes = 'Address from the cited DFA directory. Current hours and appointment availability require confirmation.'; }
            $this->line('LINK '.($office->exists ? '#'.$office->id.' ' : 'new draft ').$input['name'].': '.$input['address'].', '.$input['municipality']);
            $this->persist($office);
            $links[$office->id ?? $input['name']] = ['new_application_status' => 'unknown', 'renewal_status' => 'unknown', 'replacement_status' => 'unknown', 'service_notes' => 'Check this site in the DFA appointment portal for current slots, services and hours.', 'source_url' => $input['source_url'], 'last_verified_at' => null, 'last_verified_by' => null];
        }
        if ($this->apply) { $id->offices()->sync($links); $this->changed = true; }
    }

    private function persist(Model $model): void
    {
        if ($this->apply && (! $model->exists || $model->isDirty())) { $model->save(); $this->changed = true; }
    }

    private function fillEmpty(Model $model, string $field, mixed $value): void
    {
        if ($this->replace) {
            $this->line('REPLACE '.$field.': '.json_encode($model->$field, JSON_UNESCAPED_UNICODE).' → '.json_encode($value, JSON_UNESCAPED_UNICODE));
            $model->$field = $value; return;
        }
        if (filled($model->$field)) { $this->line('KEEP '.$field.': '.$model->$field); return; }
        $this->line('SET '.$field.': '.$value); $model->$field = $value;
    }

    private function match($records, array $aliases, string $label, array $fields = ['name']): ?Model
    {
        $names = array_map(fn ($name) => $this->normalize($name), $aliases);
        $matches = $records->filter(fn ($record) => collect($fields)->contains(fn ($field) => in_array($this->normalize($record->$field ?? ''), $names, true)));
        if ($matches->count() > 1) throw new RuntimeException('Ambiguous match for '.$label.': IDs '.$matches->pluck('id')->implode(', ').'. Use --id for the Passport or resolve duplicate directory entries.');
        return $matches->first();
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim(str_replace(['’', '‘'], "'", $value))));
    }
}
