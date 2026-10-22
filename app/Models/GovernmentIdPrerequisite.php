<?php

namespace App\Models;

use App\Services\PrerequisiteGraph;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class GovernmentIdPrerequisite extends Model
{
    protected $fillable = ['government_id_id', 'requirement_set_id', 'prerequisite_government_id_id', 'prerequisite_document_id', 'sort_order', 'note'];

    protected function casts(): array
    {
        return array_fill_keys(['government_id_id', 'requirement_set_id', 'prerequisite_government_id_id', 'prerequisite_document_id', 'sort_order'], 'integer');
    }

    /** Serialize graph edits so concurrent A→B and B→A writes cannot both pass validation. */
    public function save(array $options = [])
    {
        return DB::transaction(function () use ($options) {
            DB::table('government_ids')->orderBy('id')->lockForUpdate()->get(['id']);
            $data = array_replace(['requirement_set_id' => null, 'prerequisite_government_id_id' => null, 'prerequisite_document_id' => null, 'sort_order' => 0, 'note' => null], $this->getAttributes());
            Validator::make($data, [
                'government_id_id' => ['required', 'integer', 'exists:government_ids,id'],
                'requirement_set_id' => ['nullable', 'integer', Rule::exists('government_id_requirement_sets', 'id')->where('government_id_id', $data['government_id_id'] ?? 0)],
                'prerequisite_government_id_id' => ['nullable', 'integer', 'exists:government_ids,id'],
                'prerequisite_document_id' => ['nullable', 'integer', 'exists:documents,id'],
                'sort_order' => ['required', 'integer', 'min:0', 'max:4294967295'],
                'note' => ['nullable', 'string', 'max:500'],
            ])->validate();
            $this->fill(array_intersect_key($data, array_flip($this->fillable)));
            $rows = self::query()->when($this->exists, fn ($query) => $query->whereKeyNot($this->id))->lockForUpdate()->get()->map->getAttributes()->all();
            $rows[] = $this->attributesToArray();
            app(PrerequisiteGraph::class)->validate($rows, GovernmentIdRequirementSet::pluck('government_id_id', 'id')->all());
            // Generated columns must never be included in an UPDATE after refreshing the model.
            unset($this->attributes['profile_scope_id']);

            return parent::save($options);
        });
    }

    public function governmentId(): BelongsTo
    {
        return $this->belongsTo(GovernmentId::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(GovernmentIdRequirementSet::class, 'requirement_set_id');
    }

    public function prerequisiteGovernmentId(): BelongsTo
    {
        return $this->belongsTo(GovernmentId::class, 'prerequisite_government_id_id');
    }

    public function prerequisiteDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'prerequisite_document_id');
    }
}
