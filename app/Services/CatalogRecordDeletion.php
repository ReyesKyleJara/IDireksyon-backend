<?php

namespace App\Services;

use App\Models\ContentChangeLog;
use App\Models\Document;
use App\Models\GovernmentId;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Permanently deletes an ID or Document without losing what other records say.
 * Requirements and prerequisites that pointed here become custom text, keeping
 * their group, OR/choose-N rules, order, format, copies, and instructions.
 */
class CatalogRecordDeletion
{
    public const BLOCKED_MESSAGE = 'Permanent deletion is blocked because resident inventory, preparation progress, or roadmap data depends on this entry. Keep this catalog entry to preserve resident data.';

    private const TABLES = [
        GovernmentId::class => [
            'label' => 'Government ID',
            'column' => 'government_id_id',
            'sets' => 'government_id_requirement_sets',
            'groups' => 'government_id_requirement_groups',
            'items' => 'government_id_requirement_items',
            'prerequisites' => 'catalog_government_id_prerequisites',
            'steps' => 'government_id_application_steps',
        ],
        Document::class => [
            'label' => 'Document',
            'column' => 'document_id',
            'sets' => 'document_requirement_sets',
            'groups' => 'document_requirement_groups',
            'items' => 'document_requirement_items',
            'prerequisites' => 'catalog_document_prerequisites',
            'steps' => 'document_application_steps',
        ],
    ];

    /** Catalog usage and deletion eligibility only; never expose resident counts. */
    public function impact(GovernmentId|Document $record): array
    {
        $tables = self::TABLES[$record::class];
        $requirements = $this->foreign($record, $this->requirementUsages($record));
        $prerequisites = $this->foreign($record, $this->prerequisiteUsages($record));
        $counts = [
            'requirements' => count($requirements),
            'requirement_entries' => count($this->owners($requirements)),
            'prerequisites' => count($prerequisites),
            'prerequisite_entries' => count($this->owners($prerequisites)),
            'offices' => $record->offices()->count(),
        ];
        $blocked = app(CatalogResidentReferences::class)->exists($record);

        return [
            'name' => $record->name,
            'type_label' => $tables['label'],
            'counts' => $counts,
            'can_delete' => ! $blocked,
            'sections' => array_values(array_filter([
                ...($blocked ? [['title' => 'Deletion blocked.', 'body' => self::BLOCKED_MESSAGE]] : [
                    ...$this->referenceSections($requirements, $prerequisites),
                    $this->removedSection($record, $tables, $counts['offices']),
                ]),
            ])),
        ];
    }

    /** Runs in one transaction and returns the success message. */
    public function delete(GovernmentId|Document $record): string
    {
        return CatalogResidentReferences::transaction(function () use ($record) {
            $record = $record->newQuery()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            if (app(CatalogResidentReferences::class)->exists($record, lock: true)) {
                throw ValidationException::withMessages(['catalog_deletion' => self::BLOCKED_MESSAGE]);
            }
            $tables = self::TABLES[$record::class];
            $requirements = $this->requirementUsages($record, lock: true);
            $prerequisites = $this->prerequisiteUsages($record, lock: true);

            $this->convertRequirements($record);
            $this->convertPrerequisites($record, $prerequisites);
            $this->logAffectedOwners($record, $requirements, $prerequisites);

            // Application Guide steps restrict set deletion, so they must go first.
            DB::table($tables['steps'])->whereIn(
                'requirement_set_id',
                DB::table($tables['sets'])->where($tables['column'], $record->id)->select('id'),
            )->delete();

            $name = $record->name;
            $record->delete();

            return $this->successMessage($name, count($this->foreign($record, $requirements)), count($this->foreign($record, $prerequisites)));
        }, exclusive: true);
    }

    /** Every requirement item pointing at this record, in both catalogs. */
    private function requirementUsages(GovernmentId|Document $record, bool $lock = false): array
    {
        $column = self::TABLES[$record::class]['column'];
        $usages = [];
        foreach (self::TABLES as $ownerClass => $owner) {
            $query = DB::table($owner['items'].' as item')
                ->join($owner['groups'].' as grp', 'grp.id', '=', 'item.requirement_group_id')
                ->join($owner['sets'].' as requirement_set', 'requirement_set.id', '=', 'grp.requirement_set_id')
                ->where('item.'.$column, $record->id)
                ->select('item.id', 'requirement_set.'.$owner['column'].' as owner_id');
            foreach (($lock ? $query->lockForUpdate() : $query)->get() as $row) {
                $usages[] = ['id' => (int) $row->id, 'owner_class' => $ownerClass, 'owner_id' => (int) $row->owner_id];
            }
        }

        return $usages;
    }

    /** Every prerequisite pointing at this record, in both catalogs. */
    private function prerequisiteUsages(GovernmentId|Document $record, bool $lock = false): array
    {
        $column = self::TABLES[$record::class]['column'];
        $usages = [];
        foreach (self::TABLES as $ownerClass => $owner) {
            $query = DB::table($owner['prerequisites'])->where($column, $record->id)->select('id', 'owner_id', 'condition');
            foreach (($lock ? $query->lockForUpdate() : $query)->get() as $row) {
                $usages[] = ['id' => (int) $row->id, 'owner_class' => $ownerClass, 'owner_id' => (int) $row->owner_id, 'condition' => $row->condition];
            }
        }

        return $usages;
    }

    /** Usages on other records. Its own requirements are deleted along with it. */
    private function foreign(GovernmentId|Document $record, array $usages): array
    {
        return array_values(array_filter($usages, fn ($usage) => $usage['owner_class'] !== $record::class || $usage['owner_id'] !== $record->id));
    }

    private function convertRequirements(GovernmentId|Document $record): void
    {
        $column = self::TABLES[$record::class]['column'];
        foreach (self::TABLES as $owner) {
            DB::table($owner['items'])->where($column, $record->id)->update([
                'type' => 'custom',
                'custom_name' => $record->name,
                $column => null,
                'updated_at' => now(),
            ]);
        }
    }

    private function convertPrerequisites(GovernmentId|Document $record, array $usages): void
    {
        $column = self::TABLES[$record::class]['column'];
        foreach ($usages as $usage) {
            $table = DB::table(self::TABLES[$usage['owner_class']]['prerequisites']);
            // An identical custom row would make the owner's next save fail as a duplicate.
            $duplicate = (clone $table)->where('owner_id', $usage['owner_id'])->where('type', 'custom')
                ->where('custom_name', $record->name)
                ->when($usage['condition'] === null, fn ($query) => $query->whereNull('condition'), fn ($query) => $query->where('condition', $usage['condition']))
                ->exists();
            if ($duplicate) {
                (clone $table)->where('id', $usage['id'])->delete();
            } else {
                (clone $table)->where('id', $usage['id'])->update([
                    'type' => 'custom',
                    'custom_name' => $record->name,
                    $column => null,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /** Recorded directly so other records' research timestamps stay as they are. */
    private function logAffectedOwners(GovernmentId|Document $record, array $requirements, array $prerequisites): void
    {
        $changes = [];
        foreach ([['requirements', $requirements], ['prerequisites', $prerequisites]] as [$field, $usages]) {
            foreach ($this->foreign($record, $usages) as $usage) {
                $changes[$usage['owner_class'].':'.$usage['owner_id']][$field] = $field;
            }
        }
        foreach ($changes as $key => $fields) {
            [$class, $id] = explode(':', $key);
            if ($owner = $class::find($id)) {
                ContentChangeLog::record($owner, 'updated', array_values($fields));
            }
        }
    }

    private function owners(array $usages): array
    {
        $names = [];
        foreach ($usages as $usage) {
            $names[$usage['owner_class'].':'.$usage['owner_id']] = $usage;
        }

        return collect($names)->map(fn ($usage) => $usage['owner_class']::whereKey($usage['owner_id'])->value('name'))->sort()->values()->all();
    }

    private function nameList(array $names): string
    {
        if (count($names) > 3) {
            $others = count($names) - 2;

            return implode(', ', array_slice($names, 0, 2)).', and '.$others.' '.Str::plural('other', $others);
        }

        return Arr::join($names, ', ', count($names) === 2 ? ' and ' : ', and ');
    }

    private function referenceSections(array $requirements, array $prerequisites): array
    {
        if ($requirements === [] && $prerequisites === []) {
            return [['title' => 'No other entries use this.', 'body' => '']];
        }
        $sentences = [];
        if ($requirements !== []) {
            $names = $this->owners($requirements);
            $count = count($requirements);
            $sentences[] = $this->nameList($names).(count($names) === 1 ? ' lists' : ' list')." this as a requirement ({$count} ".Str::plural('requirement', $count).').';
        }
        if ($prerequisites !== []) {
            $names = $this->owners($prerequisites);
            $sentences[] = $this->nameList($names).(count($names) === 1 ? ' lists' : ' list').' this as a prerequisite.';
        }

        return [
            ['title' => 'Used by other entries.', 'body' => implode(' ', $sentences)],
            ['title' => 'Those references stay.', 'body' => "They'll be kept as custom text with the same name and rules, but will no longer link to this entry or match residents' inventories automatically."],
        ];
    }

    private function removedSection(GovernmentId|Document $record, array $tables, int $offices): ?array
    {
        $sets = DB::table($tables['sets'])->where($tables['column'], $record->id)->pluck('id');
        $parts = array_values(array_filter([
            $sets->isNotEmpty() ? 'its requirements' : null,
            $record->fees()->exists() ? 'fees' : null,
            DB::table($tables['steps'])->whereIn('requirement_set_id', $sets)->exists() ? 'Application Guide' : null,
            $record->image_path ? 'image' : null,
            $offices > 0 ? $offices.' office '.Str::plural('link', $offices) : null,
        ]));
        if ($parts === []) {
            return null;
        }

        return [
            'title' => 'Also removed:',
            'body' => Arr::join($parts, ', ', count($parts) === 2 ? ' and ' : ', and ').'.'.($offices > 0 ? ' Offices themselves are not deleted.' : ''),
        ];
    }

    private function successMessage(string $name, int $requirements, int $prerequisites): string
    {
        $kept = array_values(array_filter([
            $requirements > 0 ? $requirements.' '.Str::plural('requirement', $requirements) : null,
            $prerequisites > 0 ? $prerequisites.' '.Str::plural('prerequisite', $prerequisites) : null,
        ]));
        if ($kept === []) {
            return $name.' deleted.';
        }

        return $name.' deleted. '.implode(' and ', $kept).' '.($requirements + $prerequisites === 1 ? 'was' : 'were').' kept as custom text.';
    }
}
