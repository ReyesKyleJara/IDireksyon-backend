<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\GovernmentIdPrerequisite;
use App\Models\User;
use App\Services\GovernmentIdApplicability;
use App\Services\PrerequisitePlanner;
use App\Support\ApplicantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->target = GovernmentId::create(['name' => 'Non-Professional License']);
    $this->permit = GovernmentId::create(['name' => 'Student Permit']);
    $this->document = Document::create(['name' => 'Birth Certificate']);
    $this->profile = $this->target->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'adult']);
    $this->planner = app(PrerequisitePlanner::class);
    $this->url = '/admin/government-ids/'.$this->target->id.'/prerequisites';
});

function explicitPrerequisite(GovernmentId $owner, GovernmentId|Document $prerequisite, array $attributes = []): GovernmentIdPrerequisite
{
    return $owner->prerequisites()->create([
        $prerequisite instanceof Document ? 'prerequisite_document_id' : 'prerequisite_government_id_id' => $prerequisite->id,
        ...$attributes,
    ]);
}

it('plans direct Everyone ID and Document prerequisites without requiring applicant facts', function (string $kind) {
    $record = $kind === 'government_id' ? $this->permit : $this->document;
    $link = explicitPrerequisite($this->target, $record, ['note' => 'Obtain first']);
    $plan = $this->planner->plan($this->target, new ApplicantContext);
    expect($plan->status)->toBe('ready')
        ->and(array_map(fn ($node) => $node->key(), $plan->orderedMissing))->toBe([$kind.':'.$record->id, 'government_id:'.$this->target->id])
        ->and($plan->edges[0]['prerequisite_id'])->toBe($link->id)
        ->and($plan->edges[0]['applicability_profile_id'])->toBeNull()
        ->and($plan->orderedMissing[0]->depth)->toBe(1)
        ->and($plan->orderedMissing[1]->isTarget)->toBeTrue();
})->with(['government_id', 'document']);

it('uses the existing profile evaluator and excludes nonmatching profile prerequisites', function () {
    explicitPrerequisite($this->target, $this->permit, ['requirement_set_id' => $this->profile->id]);
    $matched = $this->planner->plan($this->target, new ApplicantContext(18, 'new'));
    expect($matched->status)->toBe('ready')->and(count($matched->orderedMissing))->toBe(2)
        ->and($matched->edges[0]['applicability_profile_id'])->toBe($this->profile->id);
    foreach ([new ApplicantContext(17, 'new'), new ApplicantContext(18, 'renewal')] as $context) {
        $excluded = $this->planner->plan($this->target, $context);
        expect($excluded->status)->toBe('ready')->and(count($excluded->orderedMissing))->toBe(1)->and($excluded->edges)->toBe([]);
    }
    $this->profile->update(['application_type' => 'renewal']);
    expect(count($this->planner->plan($this->target, new ApplicantContext(18, 'renewal'))->orderedMissing))->toBe(2);
});

it('retains incomplete applicability and shared links without guessing a profile', function () {
    $specific = explicitPrerequisite($this->target, $this->permit, ['requirement_set_id' => $this->profile->id]);
    explicitPrerequisite($this->target, $this->document);
    $plan = $this->planner->plan($this->target, new ApplicantContext);
    expect($plan->status)->toBe('manual_confirmation_required')
        ->and($plan->pendingApplicability[0]['prerequisite_ids'])->toBe([$specific->id])
        ->and($plan->pendingApplicability[0]['missing_context'])->toBe(['age', 'application_type'])
        ->and($plan->orderedMissing[0]->kind)->toBe('document');
});

it('preserves custom profiles as manual only without inferring Students or age text', function () {
    $custom = $this->target->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'custom', 'applicant_type_custom' => 'Students age 18+', 'min_age' => 18]);
    $link = explicitPrerequisite($this->target, $this->permit, ['requirement_set_id' => $custom->id]);
    $context = new ApplicantContext(20, 'new');
    $automatic = $this->planner->plan($this->target, $context);
    expect($automatic->status)->toBe('manual_confirmation_required')
        ->and($automatic->edges)->toBe([])
        ->and($automatic->pendingApplicability[0]['prerequisite_ids'])->toBe([$link->id]);
    $manual = $this->planner->plan($this->target, $context, $custom->id);
    expect($manual->status)->toBe('ready')->and($manual->edges[0]['applicability_profile_id'])->toBe($custom->id);
});

it('requires confirmation for overlapping matches rather than treating alternatives as cumulative prerequisites', function () {
    $other = $this->target->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    explicitPrerequisite($this->target, $this->permit, ['requirement_set_id' => $this->profile->id]);
    explicitPrerequisite($this->target, $this->document, ['requirement_set_id' => $other->id]);
    $plan = $this->planner->plan($this->target, new ApplicantContext(20, 'new'));
    expect($plan->status)->toBe('manual_confirmation_required')->and($plan->pendingApplicability[0]['status'])->toBe('multiple_matches')->and($plan->edges)->toBe([]);
});

it('treats owned prerequisites as satisfied and prunes owned ID branches', function (string $kind) {
    explicitPrerequisite($this->target, $this->permit);
    explicitPrerequisite($this->permit, $this->document);
    if ($kind === 'government_id') {
        $context = new ApplicantContext(governmentIdIds: [$this->permit->id]);
    } else {
        $context = new ApplicantContext(documentIds: [$this->document->id]);
    }
    $plan = $this->planner->plan($this->target, $context);
    $owned = collect($plan->nodes)->first(fn ($node) => $node->kind === $kind && ! $node->isTarget);
    expect($owned->satisfied)->toBeTrue()->and($owned->order)->toBeNull()
        ->and(count($plan->orderedMissing))->toBe($kind === 'government_id' ? 1 : 2);
    if ($kind === 'government_id') {
        expect(array_column($plan->nodes, 'kind'))->not->toContain('document');
    }
})->with(['government_id', 'document']);

it('produces deterministic recursive order and deduplicates shared prerequisites', function () {
    $other = GovernmentId::create(['name' => 'Another Permit']);
    explicitPrerequisite($this->target, $other, ['sort_order' => 5]);
    explicitPrerequisite($this->target, $this->permit, ['sort_order' => 1]);
    explicitPrerequisite($this->permit, $this->document);
    explicitPrerequisite($other, $this->document);
    $plan = $this->planner->plan($this->target, new ApplicantContext);
    expect(array_column($plan->orderedMissing, 'name'))->toBe([$this->document->name, $this->permit->name, $other->name, $this->target->name])
        ->and(array_column($plan->orderedMissing, 'order'))->toBe([1, 2, 3, 4])
        ->and($plan->orderedMissing[0]->depth)->toBe(2)
        ->and(count($plan->edges))->toBe(4);
});

it('reports manual applicability on a recursive ID instead of applying the root selection to it', function () {
    explicitPrerequisite($this->target, $this->permit);
    $custom = $this->permit->requirementSets()->create(['application_type' => 'custom', 'application_type_custom' => 'Other situation', 'applicant_type' => 'all']);
    explicitPrerequisite($this->permit, $this->document, ['requirement_set_id' => $custom->id]);
    $plan = $this->planner->plan($this->target, new ApplicantContext(20, 'new'), $this->profile->id);
    expect($plan->status)->toBe('manual_confirmation_required')->and($plan->pendingApplicability[0]['government_id_id'])->toBe($this->permit->id);
});

it('returns a simple target plan when no explicit prerequisites exist and ignores ordinary ID and Document references', function () {
    $group = $this->profile->groups()->create(['title' => 'Choose evidence', 'rule' => 'or']);
    $group->items()->create(['type' => 'government_id', 'government_id_id' => $this->permit->id]);
    $group->items()->create(['type' => 'document', 'document_id' => $this->document->id]);
    $plan = $this->planner->plan($this->target, new ApplicantContext);
    expect($plan->status)->toBe('ready')->and(array_column($plan->orderedMissing, 'name'))->toBe([$this->target->name])->and($plan->edges)->toBe([]);
    $owned = $this->planner->plan($this->target, new ApplicantContext(governmentIdIds: [$this->target->id]));
    expect($owned->orderedMissing)->toBe([])->and($owned->nodes[0]->satisfied)->toBeTrue();
});

it('rejects duplicate prerequisites for Everyone and a selected profile', function (bool $profile) {
    $attributes = $profile ? ['requirement_set_id' => $this->profile->id] : [];
    explicitPrerequisite($this->target, $this->permit, $attributes);
    expect(fn () => explicitPrerequisite($this->target, $this->permit, $attributes))->toThrow(ValidationException::class);
    expect(GovernmentIdPrerequisite::count())->toBe(1);
})->with([false, true]);

it('allows the same prerequisite in distinct scopes and deduplicates it in the plan', function () {
    explicitPrerequisite($this->target, $this->permit);
    explicitPrerequisite($this->target, $this->permit, ['requirement_set_id' => $this->profile->id]);
    $plan = $this->planner->plan($this->target, new ApplicantContext(20, 'new'));
    expect(count($plan->edges))->toBe(2)->and(count($plan->orderedMissing))->toBe(2);
});

it('prevents duplicate Document links including Everyone at the database boundary', function () {
    $link = explicitPrerequisite($this->target, $this->document);
    expect(fn () => explicitPrerequisite($this->target, $this->document))->toThrow(ValidationException::class);
    expect(fn () => DB::table('government_id_prerequisites')->insert($link->only([
        'government_id_id', 'requirement_set_id', 'prerequisite_document_id',
    ])))->toThrow(QueryException::class);
});

it('rejects a root manual profile belonging to another ID', function () {
    $other = $this->permit->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    expect(fn () => $this->planner->plan($this->target, new ApplicantContext, $other->id))->toThrow(ModelNotFoundException::class);
});

it('rejects direct self references missing references invalid combinations and foreign profiles', function (string $case) {
    $otherProfile = $this->permit->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    $data = match ($case) {
        'self' => ['prerequisite_government_id_id' => $this->target->id],
        'missing' => ['prerequisite_government_id_id' => 999999],
        'both' => ['prerequisite_government_id_id' => $this->permit->id, 'prerequisite_document_id' => $this->document->id],
        'neither' => [],
        'profile' => ['prerequisite_government_id_id' => $this->permit->id, 'requirement_set_id' => $otherProfile->id],
    };
    expect(fn () => $this->target->prerequisites()->create($data))->toThrow(ValidationException::class);
})->with(['self', 'missing', 'both', 'neither', 'profile']);

it('rejects two-node and multi-level cycles across all profiles on creation and editing', function () {
    explicitPrerequisite($this->target, $this->permit);
    expect(fn () => explicitPrerequisite($this->permit, $this->target))->toThrow(ValidationException::class, 'Circular');
    $third = GovernmentId::create(['name' => 'Third ID']);
    explicitPrerequisite($this->permit, $third);
    expect(fn () => explicitPrerequisite($third, $this->target))->toThrow(ValidationException::class, 'Circular');
    $leaf = explicitPrerequisite($third, $this->document);
    expect(fn () => $leaf->update(['prerequisite_document_id' => null, 'prerequisite_government_id_id' => $this->target->id]))->toThrow(ValidationException::class, 'Circular');
    expect($leaf->fresh()->prerequisite_document_id)->toBe($this->document->id);
});

it('defensively reports cycles introduced through unsupported bulk writes', function () {
    explicitPrerequisite($this->target, $this->permit);
    DB::table('government_id_prerequisites')->insert(['government_id_id' => $this->permit->id, 'prerequisite_government_id_id' => $this->target->id]);
    $plan = $this->planner->plan($this->target, new ApplicantContext);
    expect($plan->status)->toBe('configuration_error')->and($plan->orderedMissing)->toBe([])->and($plan->errors[0]['code'])->toBe('invalid_prerequisite_graph');
});

it('enforces duplicate and exclusive non-self constraints at the database boundary', function (string $case) {
    explicitPrerequisite($this->target, $this->permit);
    $data = ['government_id_id' => $this->target->id, 'prerequisite_government_id_id' => $this->permit->id];
    $data = match ($case) {
        'duplicate' => $data,
        'both' => [...$data, 'prerequisite_document_id' => $this->document->id],
        'neither' => ['government_id_id' => $this->target->id],
        'self' => [...$data, 'prerequisite_government_id_id' => $this->target->id],
    };
    expect(fn () => DB::table('government_id_prerequisites')->insert($data))->toThrow(QueryException::class);
})->with(['duplicate', 'both', 'neither', 'self']);

it('offers structured CMS selectors and safely creates edits and removes scoped links', function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
    $this->target->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'custom', 'applicant_type_custom' => 'Students']);
    $this->get($this->url.'/create')->assertOk()->assertSee('Everyone')->assertSee('Adult')->assertSee('Age 18+')->assertSee('Needs rule setup')->assertSee('Search existing IDs or Documents')->assertDontSee('Scenario ID');
    $this->post($this->url, ['prerequisite_government_id_id' => $this->permit->id, 'requirement_set_id' => $this->profile->id, 'sort_order' => 2, 'note' => 'Required by agency'])->assertRedirect($this->url)->assertSessionHasNoErrors();
    $link = $this->target->prerequisites()->sole();
    $this->get($this->url)->assertOk()->assertSee('Student Permit')->assertSee('Requires Before Application');
    $this->get($this->url.'/'.$link->id.'/edit')->assertOk()->assertSee('Required by agency');
    $this->put($this->url.'/'.$link->id, ['prerequisite_document_id' => $this->document->id, 'sort_order' => 1])->assertRedirect($this->url)->assertSessionHasNoErrors();
    expect($link->fresh()->requirement_set_id)->toBeNull()->and($link->fresh()->prerequisite_government_id_id)->toBeNull()->and($link->fresh()->prerequisite_document_id)->toBe($this->document->id);
    $this->delete($this->url.'/'.$link->id)->assertRedirect($this->url);
    $this->assertModelExists($this->permit);
    $this->assertModelExists($this->document);
});

it('scopes CMS mutations and rejects invalid input without losing existing links', function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
    $link = explicitPrerequisite($this->permit, $this->document);
    $this->putJson($this->url.'/'.$link->id, ['prerequisite_government_id_id' => $this->permit->id])->assertNotFound();
    $this->deleteJson($this->url.'/'.$link->id)->assertNotFound();
    $this->postJson($this->url, ['prerequisite_government_id_id' => $this->target->id])->assertUnprocessable();
    $this->postJson($this->url, ['prerequisite_document_id' => $this->document->id, 'sort_order' => -1])->assertUnprocessable();
    $this->postJson($this->url, ['prerequisite_document_id' => $this->document->id, 'note' => str_repeat('x', 501)])->assertUnprocessable();
    $this->assertModelExists($link);
});

it('protects referenced IDs Documents and profiles in CMS and foreign keys', function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
    explicitPrerequisite($this->target, $this->permit, ['requirement_set_id' => $this->profile->id]);
    explicitPrerequisite($this->target, $this->document);
    $this->delete('/admin/government-ids/'.$this->permit->id)->assertSessionHas('error');
    $this->delete('/admin/government-ids/'.$this->target->id)->assertSessionHas('error');
    $this->delete('/admin/documents/'.$this->document->id)->assertSessionHas('error');
    $this->deleteJson('/admin/government-ids/'.$this->target->id.'/requirement-sets/'.$this->profile->id)->assertUnprocessable();
    $this->deleteJson('/admin/government-ids/'.$this->target->id.'/checklists/'.$this->profile->id)->assertUnprocessable();
    $this->get('/admin/government-ids/'.$this->permit->id)->assertSee('Used as a prerequisite by 1');
    $this->get('/admin/government-ids/'.$this->target->id.'/requirement-sets')->assertSee('1 sequencing prerequisites');
    foreach ([$this->target, $this->permit, $this->document, $this->profile] as $record) {
        expect(fn () => $record->delete())->toThrow(QueryException::class);
    }
});

it('does not change existing requirement or guide results when explicit prerequisites are configured', function () {
    $group = $this->profile->groups()->create(['title' => 'All documents', 'rule' => 'all']);
    $group->items()->create(['type' => 'document', 'document_id' => $this->document->id, 'quantity' => 2]);
    $step = $this->profile->applicationSteps()->create(['title' => 'Visit office']);
    $step->blocks()->create(['type' => 'text', 'content' => ['body' => 'Preserve guide']]);
    $context = new ApplicantContext(20, 'new');
    $before = app(GovernmentIdApplicability::class)->evaluate($this->target->fresh(), $context);
    explicitPrerequisite($this->target, $this->permit, ['requirement_set_id' => $this->profile->id]);
    $this->planner->plan($this->target, $context);
    expect(app(GovernmentIdApplicability::class)->evaluate($this->target->fresh(), $context))->toBe($before);
});

it('requires CMS authentication', function () {
    $this->get($this->url)->assertRedirect('/login');
    $this->post($this->url, ['prerequisite_government_id_id' => $this->permit->id])->assertRedirect('/login');
});

it('adds and removes only the prerequisite table while preserving pre-existing content', function () {
    config(['database.connections.prerequisite_migration' => config('database.connections.sqlite')]);
    $previous = DB::getDefaultConnection();
    DB::setDefaultConnection('prerequisite_migration');
    try {
        $this->artisan('migrate', ['--database' => 'prerequisite_migration', '--force' => true])->assertSuccessful();
        $owner = GovernmentId::create(['name' => 'Preserved catalog ID']);
        $profile = $owner->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'adult']);
        $group = $profile->groups()->create(['title' => 'Preserved checklist', 'rule' => 'all']);
        $step = $profile->applicationSteps()->create(['title' => 'Preserved guide']);
        $block = $step->blocks()->create(['type' => 'text', 'content' => ['body' => 'Preserved block']]);
        $records = [$owner, $profile, $group, $step, $block];
        $before = array_map(fn ($record) => $record->fresh()->getAttributes(), $records);
        $migration = require database_path('migrations/2026_10_22_000000_create_government_id_prerequisites_table.php');
        $migration->down();
        $migration->up();
        expect(array_map(fn ($record) => $record->fresh()->getAttributes(), $records))->toBe($before)
            ->and(GovernmentIdPrerequisite::count())->toBe(0)
            ->and(DB::select('PRAGMA foreign_key_check'))->toBe([]);
    } finally {
        DB::setDefaultConnection($previous);
        DB::purge('prerequisite_migration');
    }
});
