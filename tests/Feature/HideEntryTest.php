<?php

require_once __DIR__.'/../Support/agency_verification.php';

use App\Models\ContentChangeLog;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\User;
use App\Support\ResidentVisibility;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* Hide Entry / Show Entry for IDs and documents. Fake data, in-memory SQLite. */

function heToggle(GovernmentId|Document $record, bool $hide, ?User $user = null)
{
    $route = $record instanceof Document ? 'admin.documents.visibility' : 'admin.government-ids.visibility';

    // Tests run every request in one process: go back to the CMS (web) login after a resident API call.
    auth()->shouldUse('web');
    test()->flushSession();
    $response = test()->actingAs($user ?? avSuperAdmin())->from('/admin')->post(route($route, $record), ['hidden' => $hide ? 1 : 0]);
    if ($user === null) {
        $response->assertRedirect('/admin')->assertSessionHasNoErrors();
        expect($record->fresh()->isHidden())->toBe($hide);
    }

    return $response;
}

/** A visible ID whose checklist requires a document, and that document as its own record. */
function heLinked(): array
{
    $paper = Document::create(['name' => 'Fixture Birth Certificate']);
    $target = GovernmentId::create(['name' => 'Fixture Passport']);
    $set = $target->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    $item = $set->groups()->create(['title' => 'Proof of birth', 'rule' => 'all'])
        ->items()->create(['type' => 'document', 'document_id' => $paper->id, 'submission_format' => 'original']);

    return [$target, $paper, $item];
}

function heResident(): User
{
    return User::factory()->create(['role' => 'resident', 'is_active' => true]);
}

it('hides and shows an entry without changing any of its information', function (Closure $make) {
    $record = $make();
    $before = array_diff_key($record->fresh()->getAttributes(), array_flip(['hidden_at', 'hidden_by']));
    $admin = avSuperAdmin();

    heToggle($record, true, $admin)->assertRedirect('/admin')->assertSessionHasNoErrors();
    $hidden = $record->fresh();
    expect($hidden->isHidden())->toBeTrue()->and($hidden->hidden_by)->toBe($admin->id)
        ->and(array_diff_key($hidden->getAttributes(), array_flip(['hidden_at', 'hidden_by'])))->toBe($before)
        ->and(ContentChangeLog::where('action', 'hidden')->where('entity_id', $record->id)->exists())->toBeTrue();

    heToggle($record, false, $admin)->assertSessionHasNoErrors();
    $shown = $record->fresh();
    expect($shown->isHidden())->toBeFalse()->and($shown->hidden_by)->toBeNull()
        ->and(array_diff_key($shown->getAttributes(), array_flip(['hidden_at', 'hidden_by'])))->toBe($before)
        ->and(ContentChangeLog::where('action', 'shown')->where('entity_id', $record->id)->exists())->toBeTrue();
})->with([
    'government id' => [fn () => avRecord()],
    'document' => [fn () => Document::create(['name' => 'Fixture document', 'description' => 'Kept as is.'])],
]);

it('keeps a hidden entry fully usable in the CMS, with a clear banner, badge and Show entry button', function () {
    $record = avRecord();
    heToggle($record, true);
    $admin = avSuperAdmin();

    $show = $this->actingAs($admin)->get(route('admin.government-ids.show', $record))->assertOk()->getContent();
    expect($show)->toContain('data-hidden-banner')->toContain('Hidden from residents')->toContain('Show entry')->not->toContain('>Hide entry<');
    $this->get(route('admin.government-ids.edit-wizard.show', [$record, 'basic']))->assertOk();
    expect($this->get(route('admin.government-ids.index'))->assertOk()->getContent())->toContain('data-hidden-badge');

    $this->post(route('admin.government-ids.edit-wizard.save', [$record, 'basic']), ['name' => 'Fixture Passport (edited while hidden)'])->assertSessionHasNoErrors();
    expect($record->fresh()->name)->toBe('Fixture Passport (edited while hidden)')->and($record->fresh()->isHidden())->toBeTrue();

    $visible = avRecord();
    expect($this->get(route('admin.government-ids.show', $visible))->assertOk()->getContent())
        ->toContain('Hide entry')->not->toContain('data-hidden-banner');
});

it('only lets CMS users hide or show entries', function () {
    $record = avRecord();
    heToggle($record, true, heResident())->assertForbidden();
    expect($record->fresh()->isHidden())->toBeFalse();
});

it('removes a hidden entry from the app lists, details and share page, and restores it when shown', function () {
    $id = avRecord();
    $document = Document::create(['name' => 'Fixture Clearance']);
    $other = GovernmentId::create(['name' => 'Still Visible ID']);
    heToggle($id, true);
    heToggle($document, true);
    auth()->logout();

    expect(collect($this->getJson('/api/government-ids')->assertOk()->json('data'))->pluck('name')->all())->toBe(['Still Visible ID']);
    expect($this->getJson('/api/documents')->assertOk()->json('data'))->toBe([]);
    $this->getJson('/api/government-ids/'.$id->id)->assertNotFound();
    $this->getJson('/api/documents/'.$document->id)->assertNotFound();
    $this->get('/ids/'.$id->id)->assertNotFound();
    $this->get('/documents/'.$document->id)->assertNotFound();
    $this->getJson('/api/government-ids/'.$other->id)->assertOk();

    // The switch is off again outside resident requests: the CMS still sees everything.
    expect(ResidentVisibility::active())->toBeFalse()->and(GovernmentId::count())->toBe(2)->and(Document::count())->toBe(1);

    heToggle($id, false);
    heToggle($document, false);
    auth()->logout();
    $this->getJson('/api/government-ids/'.$id->id)->assertOk()->assertJsonPath('data.name', 'Fixture Passport');
    $this->getJson('/api/documents/'.$document->id)->assertOk();
    $this->get('/ids/'.$id->id)->assertOk();
    expect(collect($this->getJson('/api/government-ids')->json('data'))->pluck('name')->all())->toContain('Fixture Passport');
});

it('keeps a requirement that names a hidden record as plain text, without a link, and saves nothing', function () {
    [$target, $paper, $item] = heLinked();
    $stored = DB::table('government_id_requirement_items')->get()->toJson();
    heToggle($paper, true);
    auth()->logout();

    $json = $this->getJson('/api/government-ids/'.$target->id)->assertOk()->json('data');
    $shown = collect($json['requirement_sets'])->flatMap(fn ($set) => $set['groups'])->flatMap(fn ($group) => $group['ways'])->flatMap(fn ($way) => $way['items'])->first();
    expect($shown['name'])->toBe('Fixture Birth Certificate')->and($shown['type'])->toBe('custom')
        ->and($shown['document_id'])->toBeNull()->and($shown['government_id_id'])->toBeNull()
        ->and(DB::table('government_id_requirement_items')->get()->toJson())->toBe($stored)
        ->and($item->fresh()->document_id)->toBe($paper->id);

    heToggle($paper, false);
    auth()->logout();
    $json = $this->getJson('/api/government-ids/'.$target->id)->assertOk()->json('data');
    $shown = collect($json['requirement_sets'])->flatMap(fn ($set) => $set['groups'])->flatMap(fn ($group) => $group['ways'])->flatMap(fn ($way) => $way['items'])->first();
    expect($shown['type'])->toBe('document')->and($shown['document_id'])->toBe($paper->id);
});

it('keeps what a resident saved for a hidden record and brings it back when shown', function () {
    $resident = heResident();
    $kept = GovernmentId::create(['name' => 'Owned and later hidden']);
    $other = GovernmentId::create(['name' => 'Owned and visible']);
    $never = GovernmentId::create(['name' => 'Hidden, never owned']);
    $this->actingAs($resident)->putJson('/api/auth/inventory', ['government_id_ids' => [$kept->id, $other->id], 'document_ids' => []])->assertOk();
    heToggle($kept, true);
    heToggle($never, true);

    $this->actingAs($resident);
    $inventory = json_encode($this->getJson('/api/auth/inventory')->assertOk()->json());
    expect($inventory)->toContain('Owned and visible')->not->toContain('Owned and later hidden')->not->toContain('Hidden, never owned');

    // The app saves the list it can see; hidden records cannot be added, and the hidden saved one is not lost.
    $this->putJson('/api/auth/inventory', ['government_id_ids' => [$other->id, $never->id], 'document_ids' => []])->assertOk();
    expect(DB::table('resident_government_ids')->where('user_id', $resident->id)->pluck('government_id_id')->sort()->values()->all())
        ->toBe([$kept->id, $other->id]);

    heToggle($kept, false);
    $this->actingAs($resident);
    expect(json_encode($this->getJson('/api/auth/inventory')->assertOk()->json()))->toContain('Owned and later hidden');
});

it('leaves hidden records out of Roadmap plans and saved Roadmaps without breaking them', function () {
    [$target, $paper] = heLinked();
    $resident = heResident();
    $this->actingAs($resident);
    $journey = $this->postJson('/api/auth/journeys', ['target_government_id_ids' => [$target->id]])->assertCreated()->json('data');
    expect(json_encode($journey))->toContain('Fixture Birth Certificate');

    heToggle($paper, true);
    $this->actingAs($resident);

    $plan = $this->postJson('/api/auth/roadmap-plan', ['target_government_id_ids' => [$target->id]])->assertOk()->json('data');
    expect(collect($plan['steps'])->where('type', 'document')->all())->toBe([])
        ->and(array_column($plan['steps'], 'name'))->toContain('Fixture Passport');

    // A saved Roadmap that already contains the hidden record still opens.
    $this->getJson('/api/auth/journeys')->assertOk();

    // A hidden record cannot be planned for directly.
    heToggle($target, true);
    $this->actingAs($resident);
    $blocked = $this->postJson('/api/auth/roadmap-plan', ['target_government_id_ids' => [$target->id]])->assertOk()->json('data');
    expect($blocked['blocked'])->toBeTrue()->and(json_encode($blocked))->not->toContain('Fixture Passport');
    $this->getJson('/api/auth/journeys')->assertOk();
});

it('adds the visibility columns without hiding anything, and rolls back cleanly', function () {
    $migration = require database_path('migrations/2026_11_15_000200_add_hidden_from_residents_to_catalog_records.php');
    $record = avRecord();
    expect(GovernmentId::whereNotNull('hidden_at')->count())->toBe(0);
    $before = array_diff_key((array) DB::table('government_ids')->find($record->id), array_flip(['hidden_at', 'hidden_by']));

    $migration->down();
    expect(Schema::hasColumn('government_ids', 'hidden_at'))->toBeFalse()->and((array) DB::table('government_ids')->find($record->id))->toBe($before);
    $migration->up();
    expect(Schema::hasColumn('documents', 'hidden_at'))->toBeTrue()->and(DB::table('government_ids')->whereNotNull('hidden_at')->count())->toBe(0);
});
