<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/** Explicit edges only. Used by CMS/model writes, imports, and defensive planner checks. */
final class PrerequisiteGraph
{
    public function validate(array $rows, array $profileOwners): void
    {
        $edges = [];
        $seen = [];
        foreach ($rows as $row) {
            $owner = (int) $row['government_id_id'];
            $id = $row['prerequisite_government_id_id'];
            $document = $row['prerequisite_document_id'];
            $profile = $row['requirement_set_id'];
            if (($id === null) === ($document === null) || $id === $owner) {
                $this->fail('Choose exactly one prerequisite ID or Document. An ID cannot require itself.');
            }
            if ($profile !== null && ($profileOwners[$profile] ?? null) !== $owner) {
                $this->fail('The Applicability Profile must belong to the target Government ID.');
            }
            $key = implode(':', [$owner, $profile ?? 'everyone', $id === null ? 'document' : 'id', $id ?? $document]);
            if (isset($seen[$key])) {
                $this->fail('This prerequisite already exists for the selected applicability.');
            }
            $seen[$key] = true;
            if ($id !== null) {
                $edges[$owner][] = (int) $id;
            }
        }
        $visiting = [];
        $visited = [];
        $visit = function (int $id, array $path) use (&$visit, &$visiting, &$visited, $edges): void {
            if (isset($visiting[$id])) {
                $this->fail('Circular prerequisite chain: '.implode(' → ', [...$path, $id]).'. Remove a dependency before saving.');
            }
            if (isset($visited[$id])) {
                return;
            }
            $visiting[$id] = true;
            foreach ($edges[$id] ?? [] as $next) {
                $visit($next, [...$path, $id]);
            }
            unset($visiting[$id]);
            $visited[$id] = true;
        };
        foreach (array_keys($edges) as $id) {
            $visit($id, []);
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['prerequisite' => $message]);
    }
}
