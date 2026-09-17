<?php

namespace App\Services;

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\RequirementGroup;

/** Shared directory/rule contract; no ID-specific rules or resident roadmap logic. */
class CatalogDefinition
{
    public function directoryIssues(GovernmentId|Document $record): array
    {
        $issues = [];
        if ($record->research_status !== 'verified') {
            $issues[] = 'Verify the researched content before making it available to the directory.';
        }
        if (! $record->source_url || ! $record->source_checked_at) {
            $issues[] = 'Record an official source and the date it was checked.';
        }
        if ($record->availability_status === 'inactive') {
            $issues[] = 'Inactive records cannot appear in the resident directory.';
        }

        return $issues;
    }

    public function structureIssues(GovernmentId|Document $record): array
    {
        $record->loadMissing(['requirementGroups', 'requirements.referencedDocument', 'requirements.referencedGovernmentId']);
        $issues = [];
        $groups = $record->requirementGroups;
        foreach ($record->requirements as $requirement) {
            if (! $requirement->requirement_group_id || ! $groups->contains('id', $requirement->requirement_group_id)) {
                $issues[] = 'Assign every requirement to a group before reviewing the rules.';
            }
            if ($requirement->type !== 'custom') {
                $target = $requirement->type === 'document' ? $requirement->referencedDocument : $requirement->referencedGovernmentId;
                if (! $target) {
                    $issues[] = 'A linked requirement is missing. Choose an existing ID or document.';
                } elseif (get_class($target) === get_class($record) && $target->id === $record->id) {
                    $issues[] = 'An entry cannot require itself.';
                }
            } elseif ($requirement->is_dependency) {
                $issues[] = 'Only a linked ID or document can be marked as an obtain-first dependency.';
            }
        }
        foreach ($groups as $group) {
            $options = $record->requirements->where('requirement_group_id', $group->id);
            $count = $options->count();
            if (! $count) {
                $issues[] = $group->name.': add at least one requirement or remove this empty group.';
            }
            if (! array_key_exists($group->match_rule, RequirementGroup::MATCH_RULES) || ($group->match_rule === 'at_least' && ($group->minimum_count < 1 || $group->minimum_count > $count))) {
                $issues[] = $group->name.': the minimum must fit the number of options in the group.';
            }
            if ($group->condition_notes) {
                $issues[] = $group->name.': applicability needs manual review; conditional rules are not automated yet.';
            }
            $keys = $options->map(fn ($r) => $r->type.':'.($r->referenced_document_id ?? $r->referenced_government_id_id ?? mb_strtolower(trim($r->name))));
            if ($keys->unique()->count() !== $count) {
                $issues[] = $group->name.': duplicate options must not count toward a minimum.';
            }
        }

        return array_values(array_unique($issues));
    }

    public function sequencingIssues(GovernmentId|Document $record): array
    {
        $issues = array_merge($this->directoryIssues($record), $this->structureIssues($record));
        if (! $record->is_published) {
            $issues[] = 'Make the verified record available to the directory first.';
        }
        if ($record->availability_status !== 'available') {
            $issues[] = 'Confirm availability for new applications before using this as an acquisition step.';
        }
        if (! $record->requirements_reviewed) {
            $issues[] = 'Review the complete requirement set and obtain-first links against the source.';
        }

        return array_values(array_unique($issues));
    }

    /** All groups must be satisfied. Alternatives within a group remain alternatives. */
    public function rules(GovernmentId|Document $record): array
    {
        if ($issues = $this->sequencingIssues($record)) {
            throw new \DomainException(implode(' ', $issues));
        }

        return $record->requirementGroups->map(fn ($group) => [
            'id' => $group->id, 'name' => $group->name, 'match' => $group->match_rule,
            'minimum' => $group->match_rule === 'all' ? $record->requirements->where('requirement_group_id', $group->id)->count() : ($group->match_rule === 'any' ? 1 : $group->minimum_count),
            'options' => $record->requirements->where('requirement_group_id', $group->id)->map(fn ($r) => [
                'id' => $r->id, 'name' => $r->display_name, 'type' => $r->type,
                'reference_id' => $r->referenced_document_id ?? $r->referenced_government_id_id,
                'obtain_first' => $r->is_dependency, 'notes' => $r->description,
            ])->values()->all(),
        ])->values()->all();
    }

    /** Do not sum fee alternatives or optional fees into a fabricated total. */
    public function feeChoices(GovernmentId|Document $record): array
    {
        $record->loadMissing('fees');
        $map = fn ($fee) => ['id' => $fee->id, 'name' => $fee->name, 'amount' => $fee->amount, 'currency' => 'PHP', 'notes' => $fee->notes];

        return [
            'required' => $record->fees->where('kind', 'required')->map($map)->values()->all(),
            'optional' => $record->fees->where('kind', 'optional')->map($map)->values()->all(),
            'choose_one' => $record->fees->where('kind', 'alternative')->groupBy('choice_group')
                ->map(fn ($fees, $name) => ['name' => $name, 'options' => $fees->map($map)->values()->all()])->values()->all(),
        ];
    }
}
