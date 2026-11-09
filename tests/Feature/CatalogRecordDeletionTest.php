<?php

use App\Models\ContentChangeLog;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\Office;
use App\Models\ResidentJourney;
use App\Models\User;
use App\Services\CatalogPrerequisites;
use App\Services\CatalogRecordDeletion;
use App\Services\RoadmapPlanner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'super_admin']);
    $this->actingAs($this->admin);
});

/** An owner with a choose-2 way: the record being deleted, plus another that stays linked. */
function linkedRequirement($owner, Document $linked, array $overrides = [])
{
    $set = $owner->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    $group = $set->groups()->create(['rule' => 'choose_one', 'sort_order' => 4]);
    $way = $group->ways()->create(['required_count' => 2, 'sort_order' => 1]);
    $stays = Document::create(['name' => 'Stays linked '.uniqid()]);

    $item = $group->items()->create([
        'type' => 'document', 'document_id' => $linked->id,
        'submission_format' => 'original_photocopy', 'copies' => 2, 'quantity' => 3,
        'instructions' => 'Bring the latest copy.', 'sort_order' => 7, ...$overrides,
    ]);
    $other = $group->items()->create(['type' => 'document', 'document_id' => $stays->id, 'sort_order' => 8]);
    $item->forceFill(['requirement_way_id' => $way->id])->save();
    $other->forceFill(['requirement_way_id' => $way->id])->save();

    return compact('set', 'group', 'way', 'item', 'other', 'stays');
}

function deletePath($record): string
{
    return route($record instanceof Document ? 'admin.documents.destroy' : 'admin.government-ids.destroy', $record);
}

function impactPath($record): string
{
    return route($record instanceof Document ? 'admin.documents.delete-impact' : 'admin.government-ids.delete-impact', $record);
}

it('deletes an unused record and returns to its list', function ($model, $list) {
    $record = $model::create(['name' => 'Unused entry']);

    $this->delete(deletePath($record))->assertRedirect($list)->assertSessionHas('success', 'Unused entry deleted.');

    $this->assertModelMissing($record);
    $this->get($list)->assertOk()->assertSee('Unused entry deleted.');
    $this->get($list)->assertOk()->assertDontSee('Unused entry');
})->with([[GovernmentId::class, '/admin/government-ids'], [Document::class, '/admin/documents']]);

it('keeps a requirement as custom text with its rules, on IDs and documents', function ($ownerModel, $api) {
    $target = Document::create(['name' => 'PSA Birth Certificate']);
    $owner = $ownerModel::create(['name' => 'Philippine National ID']);
    ['item' => $item, 'other' => $other, 'way' => $way, 'group' => $group, 'stays' => $stays] = linkedRequirement($owner, $target);

    $this->delete(deletePath($target))->assertSessionHas('success', 'PSA Birth Certificate deleted. 1 requirement was kept as custom text.');

    $this->assertModelMissing($target);
    $item->refresh();
    expect($item->type)->toBe('custom')->and($item->custom_name)->toBe('PSA Birth Certificate')
        ->and($item->document_id)->toBeNull()->and($item->display_name)->toBe('PSA Birth Certificate')
        ->and($item->submission_format)->toBe('original_photocopy')->and($item->copies)->toBe(2)
        ->and($item->quantity)->toBe(3)->and($item->instructions)->toBe('Bring the latest copy.')
        ->and($item->sort_order)->toBe(7)->and($item->requirement_way_id)->toBe($way->id);
    expect($way->fresh()->required_count)->toBe(2)->and($group->fresh()->rule)->toBe('choose_one')->and($group->fresh()->sort_order)->toBe(4);
    expect($other->fresh()->document_id)->toBe($stays->id);
    $this->getJson($api.'/'.$owner->id)->assertOk();
    $this->getJson($api)->assertOk()->assertDontSee('"PSA Birth Certificate"', false);
})->with([[GovernmentId::class, '/api/government-ids'], [Document::class, '/api/documents']]);

it('keeps a government ID requirement as custom text too', function () {
    $target = GovernmentId::create(['name' => 'Voter ID']);
    $owner = Document::create(['name' => 'Clearance']);
    $set = $owner->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    $item = $set->groups()->create(['rule' => 'all'])->items()->create(['type' => 'government_id', 'government_id_id' => $target->id]);

    $this->delete(deletePath($target))->assertSessionHas('success');

    expect($item->fresh()->type)->toBe('custom')->and($item->fresh()->custom_name)->toBe('Voter ID')->and($item->fresh()->government_id_id)->toBeNull();
});

it('deletes a record that lists itself as a requirement', function () {
    $record = GovernmentId::create(['name' => 'Renewable ID']);
    $set = $record->requirementSets()->create(['application_type' => 'renewal', 'applicant_type' => 'all']);
    $set->groups()->create(['rule' => 'all'])->items()->create(['type' => 'government_id', 'government_id_id' => $record->id]);

    $this->delete(deletePath($record))->assertSessionHas('success', 'Renewable ID deleted.');

    $this->assertModelMissing($record);
    expect(DB::table('government_id_requirement_items')->count())->toBe(0);
});

it('keeps prerequisites as custom text with their condition', function () {
    $target = Document::create(['name' => 'Barangay Certificate']);
    $owner = GovernmentId::create(['name' => 'Postal ID']);
    app(CatalogPrerequisites::class)->save($owner, [['type' => 'document', 'document_id' => $target->id, 'condition' => 'If first time']]);

    $this->delete(deletePath($target))->assertSessionHas('success', 'Barangay Certificate deleted. 1 prerequisite was kept as custom text.');

    $row = $owner->prerequisites()->sole();
    expect($row->type)->toBe('custom')->and($row->custom_name)->toBe('Barangay Certificate')
        ->and($row->document_id)->toBeNull()->and($row->condition)->toBe('If first time');
});

it('merges a prerequisite into an identical custom one so later saves do not fail', function () {
    $target = Document::create(['name' => 'Barangay Certificate']);
    $owner = GovernmentId::create(['name' => 'Postal ID']);
    app(CatalogPrerequisites::class)->save($owner, [
        ['type' => 'document', 'document_id' => $target->id],
        ['type' => 'custom', 'custom_name' => 'Barangay Certificate'],
    ]);

    $this->delete(deletePath($target))->assertSessionHas('success');

    expect($owner->prerequisites()->count())->toBe(1)->and($owner->prerequisites()->sole()->type)->toBe('custom');
});

it('removes owned content, including an Application Guide, but not offices or linked records', function () {
    Storage::fake('public');
    $record = GovernmentId::create(['name' => 'Full entry']);
    $record->forceFill(['image_path' => 'catalog-images/a.jpg', 'image_original_path' => 'catalog-images/originals/a.jpg'])->save();
    Storage::disk('public')->put('catalog-images/a.jpg', 'x');
    Storage::disk('public')->put('catalog-images/originals/a.jpg', 'x');
    $linked = Document::create(['name' => 'Linked']);
    ['set' => $set, 'stays' => $stays] = linkedRequirement($record, $linked);
    $set->applicationSteps()->create(['title' => 'Step one']);
    $record->fees()->create(['label' => 'Fee', 'type' => 'free']);
    $office = Office::create(['name' => 'Municipal office']);
    $record->offices()->attach($office->id);

    $this->delete(deletePath($record))->assertSessionHas('success', 'Full entry deleted.');

    expect(DB::table('government_id_application_steps')->count())->toBe(0)
        ->and(DB::table('government_id_requirement_sets')->count())->toBe(0)
        ->and(DB::table('government_id_fees')->count())->toBe(0)
        ->and(DB::table('government_id_office')->count())->toBe(0);
    $this->assertModelExists($office);
    $this->assertModelExists($linked);
    $this->assertModelExists($stays);
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

it('blocks deletion without changing inventories or explicit preparation choices', function () {
    $target = Document::create(['name' => 'PSA Birth Certificate']);
    $owner = GovernmentId::create(['name' => 'National ID']);
    ['item' => $item] = linkedRequirement($owner, $target);
    $owned = User::factory()->create();
    $changedMind = User::factory()->create();
    $unrelated = User::factory()->create();
    $owned->ownedDocuments()->attach($target->id);
    $changedMind->ownedDocuments()->attach($target->id);
    DB::table('resident_id_preparations')->insert(['user_id' => $changedMind->id, 'requirement_item_id' => $item->id, 'prepared' => false]);

    $this->delete(deletePath($target))->assertSessionHasErrors('catalog_deletion');

    $this->assertModelExists($target);
    expect($item->fresh()->type)->toBe('document');
    expect(DB::table('resident_documents')->count())->toBe(2);
    $prepared = fn ($user) => DB::table('resident_id_preparations')->where('user_id', $user->id)->value('prepared');
    expect($prepared($owned))->toBeNull()->and((bool) $prepared($changedMind))->toBeFalse()->and($prepared($unrelated))->toBeNull();
    $this->actingAs($owned)->getJson('/api/auth/requirement-preparation/government_id/'.$owner->id)->assertOk()
        ->assertJsonPath('data.items', []);
});

it('logs each affected record without changing its research timestamp', function () {
    $target = Document::create(['name' => 'PSA Birth Certificate']);
    $owner = GovernmentId::create(['name' => 'National ID']);
    linkedRequirement($owner, $target);
    app(CatalogPrerequisites::class)->save($owner, [['type' => 'document', 'document_id' => $target->id]]);
    $updated = $owner->fresh()->updated_at;
    $this->travel(5)->minutes();

    $this->delete(deletePath($target));

    expect($owner->fresh()->updated_at->equalTo($updated))->toBeTrue();
    $log = ContentChangeLog::where('entity_type', 'government_id')->where('entity_id', $owner->id)->where('action', 'updated')->latest('id')->first();
    expect($log->changed_fields)->toBe(['requirements', 'prerequisites']);
    expect(ContentChangeLog::where('entity_type', 'document')->where('entity_id', $target->id)->where('action', 'deleted')->exists())->toBeTrue();
});

it('rolls everything back if the delete fails', function () {
    $target = Document::create(['name' => 'PSA Birth Certificate']);
    $owner = GovernmentId::create(['name' => 'National ID']);
    ['item' => $item] = linkedRequirement($owner, $target);
    Document::deleting(fn () => throw new RuntimeException('Forced failure'));

    expect(fn () => app(CatalogRecordDeletion::class)->delete($target))->toThrow(RuntimeException::class);

    $this->assertModelExists($target);
    expect($item->fresh()->type)->toBe('document')->and($item->fresh()->document_id)->toBe($target->id);
    expect(DB::table('resident_documents')->count())->toBe(0)->and(DB::table('resident_id_preparations')->count())->toBe(0);
});

it('rejects a delete from a stale page', function () {
    $record = Document::create(['name' => 'Entry']);

    $this->delete(deletePath($record), ['_catalog_revision' => 'stale'])->assertSessionHasErrors('_catalog_revision');

    $this->assertModelExists($record);
});

it('blocks deleting a saved journey target and keeps its details available', function () {
    $resident = User::factory()->create();
    $target = GovernmentId::create(['name' => 'Target ID']);
    $this->actingAs($resident);
    $journey = $this->postJson('/api/auth/journeys', ['target_government_id_ids' => [$target->id], 'target_document_ids' => []])->assertCreated()->json('data');

    $this->actingAs($this->admin, 'web')->delete(deletePath($target))->assertSessionHasErrors('catalog_deletion');

    $this->actingAs($resident)->getJson('/api/auth/journeys')->assertOk()
        ->assertJsonPath('data.0.id', $journey['id'])->assertSee('Target ID');
    expect(app(RoadmapPlanner::class)->build($resident, [$target->id], [])['blocked'])->toBeFalse();
    $this->getJson('/api/government-ids/'.$target->id)->assertOk();
});

it('blocks deleting a saved journey acquisition step', function () {
    $resident = User::factory()->create();
    $step = Document::create(['name' => 'Supporting document']);
    $target = GovernmentId::create(['name' => 'Target ID']);
    app(CatalogPrerequisites::class)->save($target, [['type' => 'document', 'document_id' => $step->id]]);
    $this->actingAs($resident);
    $this->postJson('/api/auth/journeys', ['target_government_id_ids' => [$target->id], 'target_document_ids' => []])->assertCreated();

    $this->actingAs($this->admin, 'web')->delete(deletePath($step))->assertSessionHasErrors('catalog_deletion');

    $this->actingAs($resident)->getJson('/api/auth/journeys')->assertOk()->assertSee('Supporting document');
    expect(ResidentJourney::count())->toBe(1);
});

it('reports usage for the warning without changing anything', function () {
    $target = Document::create(['name' => 'PSA Birth Certificate']);
    foreach (['Alpha ID', 'Bravo ID', 'Charlie ID', 'Delta ID'] as $name) {
        ['item' => $item] = linkedRequirement(GovernmentId::create(['name' => $name]), $target);
    }
    $prerequisiteOwner = Document::create(['name' => 'Echo Document']);
    app(CatalogPrerequisites::class)->save($prerequisiteOwner, [['type' => 'document', 'document_id' => $target->id]]);
    $target->offices()->attach(Office::create(['name' => 'Office'])->id);

    $impact = $this->getJson(impactPath($target))->assertOk()->json('data');

    expect($impact['counts'])->toBe([
        'requirements' => 4, 'requirement_entries' => 4, 'prerequisites' => 1,
        'prerequisite_entries' => 1, 'offices' => 1,
    ]);
    $text = json_encode($impact['sections']);
    expect($text)->toContain('Alpha ID, Bravo ID, and 2 others list this as a requirement (4 requirements).')
        ->toContain('Echo Document lists this as a prerequisite.')
        ->toContain('kept as custom text')->toContain('1 office link')->toContain('Offices themselves are not deleted.');
    expect($impact['can_delete'])->toBeTrue();
    expect($item->fresh()->type)->toBe('document');
    $this->assertModelExists($target);
});

it('blocks saved journey references without exposing a resident count', function () {
    $target = GovernmentId::create(['name' => 'Target ID']);
    expect(json_encode($this->getJson(impactPath($target))->assertOk()->json('data.sections')))->toContain('No other entries use this.');

    $this->actingAs(User::factory()->create());
    $this->postJson('/api/auth/journeys', ['target_government_id_ids' => [$target->id], 'target_document_ids' => []])->assertCreated();

    $this->actingAs($this->admin, 'web')->getJson(impactPath($target))->assertOk()
        ->assertJsonPath('data.can_delete', false)->assertJsonMissingPath('data.counts.journeys')
        ->assertJsonMissingPath('data.counts.inventory')->assertSee(CatalogRecordDeletion::BLOCKED_MESSAGE);
});

it('lets a Super Admin see the Delete action', function ($model, $resource) {
    $record = $model::create(['name' => 'Entry']);

    $this->get('/admin/'.$resource.'/'.$record->id)->assertOk()->assertSee('Delete permanently')->assertSee('catalogDelete(', false);
})->with([[GovernmentId::class, 'government-ids'], [Document::class, 'documents']]);

it('hides deletion from Researchers and refuses direct requests', function ($model, $resource) {
    $record = $model::create(['name' => 'Entry']);
    $this->actingAs(User::factory()->create(['role' => 'researcher']));

    $this->get('/admin/'.$resource.'/'.$record->id)->assertOk()->assertDontSee('Delete permanently')->assertDontSee('catalogDelete(', false);
    $this->getJson(impactPath($record))->assertForbidden();
    $this->delete(deletePath($record))->assertForbidden();
    $this->deleteJson(deletePath($record))->assertForbidden();

    $this->assertModelExists($record);
})->with([[GovernmentId::class, 'government-ids'], [Document::class, 'documents']]);

it('keeps normal editing and image work for Researchers', function () {
    Storage::fake('public');
    $record = GovernmentId::create(['name' => 'Entry']);
    $this->actingAs(User::factory()->create(['role' => 'researcher']));

    $this->put('/admin/government-ids/'.$record->id, ['name' => 'Renamed'])->assertSessionHasNoErrors();
    expect($record->fresh()->name)->toBe('Renamed');
    $this->get('/admin/government-ids/'.$record->id.'/edit')->assertOk()->assertSee('catalogImage(', false);
});

it('refuses residents and guests', function () {
    $record = Document::create(['name' => 'Entry']);

    $this->actingAs(User::factory()->create());
    $this->getJson(impactPath($record))->assertForbidden();
    $this->delete(deletePath($record))->assertForbidden();

    auth()->logout();
    $this->delete(deletePath($record))->assertRedirect(route('login'));
    $this->assertModelExists($record);
});

it('has no quick delete on the Documents list', function () {
    $record = Document::create(['name' => 'Entry']);

    $this->get('/admin/documents')->assertOk()->assertDontSee('action="'.route('admin.documents.destroy', $record).'"', false);
});

it('leaves the public legal pages alone', function ($path) {
    $this->get($path)->assertOk()->assertSee('GeoBytes');
})->with(['/privacy', '/account-deletion']);
