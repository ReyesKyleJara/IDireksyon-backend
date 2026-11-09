<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\ResidentJourney;
use App\Models\User;
use App\Services\CatalogRecordDeletion;
use App\Services\CatalogResidentReferences;
use App\Services\CatalogRevision;
use App\Services\RoadmapPlanner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

function safetyRoute($record, string $action): string
{
    return route('admin.'.($record instanceof GovernmentId ? 'government-ids' : 'documents').'.'.$action, $record);
}

function safetySnapshot(): array
{
    $tables = ['users', 'resident_government_ids', 'resident_documents', 'resident_id_preparations',
        'resident_document_preparations', 'resident_journeys', 'resident_journey_completions',
        'resident_journey_resolutions', 'government_ids', 'documents', 'government_id_requirement_items',
        'document_requirement_items', 'catalog_government_id_prerequisites', 'catalog_document_prerequisites',
        'content_change_logs'];

    return collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->get()->toJson()])->all();
}

function safetyItem($owner, $target = null)
{
    $set = $owner->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    $group = $set->groups()->create(['rule' => 'all']);

    return $group->items()->create($target === null ? ['type' => 'custom', 'custom_name' => 'Form'] : [
        'type' => $target instanceof GovernmentId ? 'government_id' : 'document',
        $target instanceof GovernmentId ? 'government_id_id' : 'document_id' => $target->id,
    ]);
}

beforeEach(function () {
    $this->staff = User::factory()->create(['role' => 'super_admin']);
    $this->resident = User::factory()->create(['name' => 'Private resident', 'email' => 'private@example.test']);
    $this->actingAs($this->staff);
});

it('blocks inventory references through both HTTP and direct service calls without any writes', function ($model, $relationship) {
    Storage::fake('public');
    $record = $model::create(['name' => 'Resident-owned entry']);
    $record->forceFill(['image_path' => 'catalog-images/keep.jpg'])->save();
    Storage::disk('public')->put('catalog-images/keep.jpg', 'keep');
    $this->resident->$relationship()->attach($record);
    $before = safetySnapshot();

    $impact = $this->getJson(safetyRoute($record, 'delete-impact'))->assertOk()->assertJsonPath('data.can_delete', false);
    $impact->assertJsonMissingPath('data.counts.inventory')->assertJsonMissingPath('data.counts.journeys')
        ->assertDontSee('Private resident')->assertDontSee('private@example.test')
        ->assertDontSee('user_id')->assertDontSee('has it in their inventory');
    $this->deleteJson(safetyRoute($record, 'destroy'))->assertUnprocessable()
        ->assertJsonValidationErrors('catalog_deletion')
        ->assertJsonPath('errors.catalog_deletion.0', CatalogRecordDeletion::BLOCKED_MESSAGE);
    expect(fn () => app(CatalogRecordDeletion::class)->delete($record))->toThrow(ValidationException::class);

    expect(safetySnapshot())->toBe($before);
    Storage::disk('public')->assertExists('catalog-images/keep.jpg');
})->with([[GovernmentId::class, 'ownedGovernmentIds'], [Document::class, 'ownedDocuments']]);

it('protects saved true and false preparation on an entries own checklist', function ($model, $table, $prepared) {
    $record = $model::create(['name' => 'Checklist owner']);
    $item = safetyItem($record);
    DB::table($table)->insert(['user_id' => $this->resident->id, 'requirement_item_id' => $item->id, 'prepared' => $prepared]);
    $before = safetySnapshot();

    $this->deleteJson(safetyRoute($record, 'destroy'))->assertUnprocessable()->assertJsonValidationErrors('catalog_deletion');
    expect(safetySnapshot())->toBe($before);
})->with([
    [GovernmentId::class, 'resident_id_preparations', true], [GovernmentId::class, 'resident_id_preparations', false],
    [Document::class, 'resident_document_preparations', true], [Document::class, 'resident_document_preparations', false],
]);

it('protects preparation referring to an entry from either catalog', function ($targetModel, $ownerModel, $table) {
    $record = $targetModel::create(['name' => 'Linked entry']);
    $owner = $ownerModel::create(['name' => 'Other application']);
    $item = safetyItem($owner, $record);
    DB::table($table)->insert(['user_id' => $this->resident->id, 'requirement_item_id' => $item->id, 'prepared' => false]);
    $before = safetySnapshot();

    $this->deleteJson(safetyRoute($record, 'destroy'))->assertUnprocessable();
    expect(safetySnapshot())->toBe($before);
})->with([
    [GovernmentId::class, GovernmentId::class, 'resident_id_preparations'],
    [GovernmentId::class, Document::class, 'resident_document_preparations'],
    [Document::class, GovernmentId::class, 'resident_id_preparations'],
    [Document::class, Document::class, 'resident_document_preparations'],
]);

it('finds nested journey references and preserves completion history', function ($model, $kind, $shape) {
    $record = $model::create(['name' => 'Journey reference']);
    $reference = ['type' => $kind, 'id' => $record->id];
    $plan = match ($shape) {
        'target' => ['target_'.$kind.'_ids' => [(string) $record->id], 'steps' => []],
        'prerequisite' => ['steps' => [['prerequisites' => [$reference]]]],
        'suggestion' => ['steps' => [['suggestions' => [$reference]]]],
        'owner' => ['steps' => [['owner_key' => $kind.':'.$record->id.':set:99:group:88']]],
    };
    $journey = ResidentJourney::create(['user_id' => $this->resident->id, 'journey_key' => str_repeat('a', 64), 'plan' => $plan]);
    DB::table('resident_journey_completions')->insert([
        'resident_journey_id' => $journey->id, 'step_identity' => $kind.':'.$record->id, 'completed_at' => now(),
    ]);
    $before = safetySnapshot();

    $this->deleteJson(safetyRoute($record, 'destroy'))->assertUnprocessable();
    expect(safetySnapshot())->toBe($before);
})->with([[GovernmentId::class, 'government_id'], [Document::class, 'document']])->with(['target', 'prerequisite', 'suggestion', 'owner']);

it('protects resolution owners selected items and targets without a saved journey', function ($model, $kind, $shape) {
    $record = $model::create(['name' => 'Resolution reference']);
    $selections = match ($shape) {
        'owner' => ['paths' => [$kind.':'.$record->id => 123]],
        'item' => ['groups' => ['document:999:set:3:group:4' => ['items' => [$kind.':'.$record->id]]]],
        'target' => ['target_government_id_ids' => [], 'target_document_ids' => [], 'paths' => []],
    };
    if ($shape === 'target') {
        $selections['target_'.$kind.'_ids'] = [$record->id];
    }
    DB::table('resident_journey_resolutions')->insert([
        'user_id' => $this->resident->id, 'journey_key' => hash('sha256', json_encode([$kind.':'.$record->id])),
        'selections' => json_encode($selections),
    ]);
    $before = safetySnapshot();

    $this->deleteJson(safetyRoute($record, 'destroy'))->assertUnprocessable();
    expect(safetySnapshot())->toBe($before);
})->with([[GovernmentId::class, 'government_id'], [Document::class, 'document']])->with(['owner', 'item', 'target']);

it('fails closed for legacy resolution targets that cannot be recovered', function () {
    $record = Document::create(['name' => 'Possibly an opaque legacy target']);
    DB::table('resident_journey_resolutions')->insert([
        'user_id' => $this->resident->id, 'journey_key' => str_repeat('a', 64),
        'selections' => json_encode(['paths' => ['government_id:999' => 3], 'groups' => []]),
    ]);
    $before = safetySnapshot();
    $this->deleteJson(safetyRoute($record, 'destroy'))->assertUnprocessable();
    expect(safetySnapshot())->toBe($before);
});

it('protects completion history even when the acquisition step is no longer in the saved plan', function ($model, $kind) {
    $record = $model::create(['name' => 'Historical completed step']);
    $journey = ResidentJourney::create(['user_id' => $this->resident->id, 'journey_key' => str_repeat('b', 64), 'plan' => ['steps' => []]]);
    DB::table('resident_journey_completions')->insert([
        'resident_journey_id' => $journey->id, 'step_identity' => $kind.':'.$record->id, 'completed_at' => now(),
    ]);
    $before = safetySnapshot();
    $this->deleteJson(safetyRoute($record, 'destroy'))->assertUnprocessable();
    expect(safetySnapshot())->toBe($before);
})->with([[GovernmentId::class, 'government_id'], [Document::class, 'document']]);

it('fails closed for unreadable journey snapshots without changing them', function () {
    $record = Document::create(['name' => 'Potential dependency']);
    DB::table('resident_journeys')->insert([
        'user_id' => $this->resident->id, 'journey_key' => str_repeat('c', 64), 'plan' => 'null',
    ]);
    $before = safetySnapshot();
    $this->deleteJson(safetyRoute($record, 'destroy'))->assertUnprocessable();
    expect(safetySnapshot())->toBe($before);
});

it('does not confuse unrelated typed IDs and permits deletion with recoverable legacy targets', function () {
    $record = Document::create(['name' => 'Unused document']);
    $other = GovernmentId::create(['name' => 'Unrelated ID']);
    // Deliberately overlap numeric IDs across the two catalogs.
    expect($record->id)->toBe($other->id);
    $key = hash('sha256', json_encode(['government_id:'.$other->id]));
    ResidentJourney::create(['user_id' => $this->resident->id, 'journey_key' => $key, 'plan' => [
        'target_government_id_ids' => [$other->id], 'target_document_ids' => [],
        'steps' => [['type' => 'government_id', 'id' => $other->id]],
    ]]);
    DB::table('resident_journey_resolutions')->insert([
        'user_id' => $this->resident->id, 'journey_key' => $key,
        'selections' => json_encode(['paths' => ['government_id:'.$other->id => 1], 'groups' => []]),
    ]);
    $this->getJson(safetyRoute($record, 'delete-impact'))->assertOk()->assertJsonPath('data.can_delete', true);
    $this->delete(safetyRoute($record, 'destroy'))->assertSessionHas('success');
    $this->assertModelMissing($record);
    $this->assertModelExists($other);
});

it('rechecks resident references added after an eligible preview even with a current catalog revision', function ($model, $relationship) {
    $record = $model::create(['name' => 'Previously unused']);
    $revision = CatalogRevision::token($record);
    $this->getJson(safetyRoute($record, 'delete-impact'))->assertOk()->assertJsonPath('data.can_delete', true);
    CatalogResidentReferences::transaction(fn () => $this->resident->$relationship()->attach($record));
    $before = safetySnapshot();

    $this->from(safetyRoute($record, 'show'))->delete(safetyRoute($record, 'destroy'), ['_catalog_revision' => $revision])
        ->assertRedirect(safetyRoute($record, 'show'))->assertSessionHasErrors('catalog_deletion');
    $this->get(safetyRoute($record, 'show'))->assertOk()->assertSee(CatalogRecordDeletion::BLOCKED_MESSAGE)
        ->assertSee(':disabled="impact?.can_delete !== true"', false);
    expect(safetySnapshot())->toBe($before);
})->with([[GovernmentId::class, 'ownedGovernmentIds'], [Document::class, 'ownedDocuments']]);

it('keeps resident-linked impact and deletion unavailable to inactive staff and researchers', function ($role, $active) {
    $record = Document::create(['name' => 'Protected entry']);
    $this->resident->ownedDocuments()->attach($record);
    $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => $active]));
    $before = safetySnapshot();
    $this->getJson(safetyRoute($record, 'delete-impact'))->assertForbidden();
    $this->deleteJson(safetyRoute($record, 'destroy'))->assertForbidden();
    expect(safetySnapshot())->toBe($before);
})->with([['researcher', true], ['super_admin', false], ['resident', true]]);

it('records resolution targets on future saves so unrelated unused entries remain deletable', function () {
    $target = GovernmentId::create(['name' => 'Choose a request path']);
    $first = $target->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    $target->requirementSets()->create(['application_type' => 'renewal', 'applicant_type' => 'all']);
    $planner = app(RoadmapPlanner::class);
    $plan = $planner->build($this->resident, [$target->id]);
    $choice = collect($plan['steps'])->firstWhere('type', 'unresolved_requirement');
    $planner->build($this->resident, [$target->id], [], ['key' => $choice['resolution_key'], 'set_id' => $first->id]);
    $selections = json_decode(DB::table('resident_journey_resolutions')->value('selections'), true);
    expect($selections['target_government_id_ids'])->toBe([$target->id])->and($selections['target_document_ids'])->toBe([]);
    $unused = Document::create(['name' => 'Unused entry']);
    $this->delete(safetyRoute($unused, 'destroy'))->assertSessionHas('success');
    $this->deleteJson(safetyRoute($target, 'destroy'))->assertUnprocessable();
});
