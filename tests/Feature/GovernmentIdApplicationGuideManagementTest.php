<?php

use App\Models\ContentChangeLog;
use App\Models\GovernmentId;
use App\Models\GovernmentIdApplicationStep;
use App\Models\GovernmentIdApplicationStepBlock;
use App\Models\GovernmentIdRequirementSet;
use App\Models\User;
use App\Services\GovernmentIdApplicationGuide;
use App\Support\GuideRichText;

function guideDocument(string $text = 'Prepare before visiting'): array
{
    return ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text, 'marks' => [['type' => 'bold']]]]]]];
}

function guideScenario(array $overrides = []): array
{
    return array_replace([
        'application_type' => 'new', 'applicant_type' => 'adult',
        'steps' => [[
            'title' => 'Prepare your application', 'short_description' => 'Before visiting the office', 'type' => 'general',
            'blocks' => [
                ['type' => 'instructions', 'section_title' => 'What to do', 'content' => ['items' => [['body' => guideDocument()]]]],
                ['type' => 'requirements', 'section_title' => 'Documents to bring', 'content' => ['intro' => guideDocument('Bring the applicable items below.')]],
            ],
        ]],
    ], $overrides);
}

function editableGuide(GovernmentId $id): array
{
    return array_map(function ($row) { unset($row['label']); return $row; }, app(GovernmentIdApplicationGuide::class)->present($id));
}

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
    $this->target = GovernmentId::create(['name' => 'Application guide example', 'application_process' => 'Keep the earlier instructions']);
    $this->url = '/admin/government-ids/'.$this->target->id;
});

it('renders the editor on create and edit and keeps View read only', function () {
    $this->get('/admin/government-ids/create')->assertOk()->assertSee('governmentIdGuide', false)->assertSee('Apply step');
    $this->get($this->url.'/edit')->assertOk()->assertSee('Application Guide')->assertSee('Apply step')->assertDontSee('Existing guide text')->assertDontSee('name="application_process"', false);
    $this->get($this->url)->assertOk()->assertDontSee('Apply step');
});

it('creates a new ID scenario steps and formatted blocks together', function () {
    $this->post('/admin/government-ids', ['name' => 'New guide ID', 'application_guide_payload' => json_encode([guideScenario()])])->assertSessionHasNoErrors();
    $id = GovernmentId::where('name', 'New guide ID')->firstOrFail();
    $set = $id->requirementSets()->firstOrFail();
    expect($set->applicant_type)->toBe('adult')->and($set->min_age)->toBe(18);
    $step = $set->applicationSteps()->firstOrFail();
    expect($step->blocks()->count())->toBe(2)->and($step->blocks->first()->content['items'][0]['body'])->toBe(guideDocument());
    $this->get('/admin/government-ids/'.$id->id)->assertOk()->assertSee('<strong>Prepare before visiting</strong>', false)->assertSee('Prepare your application');
});

it('reuses scenarios preserves identities and reorders or deletes only submitted steps', function () {
    $set = $this->target->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'adult']);
    $other = $this->target->requirementSets()->create(['application_type' => 'renewal', 'applicant_type' => 'minor']);
    $keep = $other->applicationSteps()->create(['title' => 'Keep untouched']);
    $row = guideScenario(['id' => $set->id]);
    $row['steps'][] = ['title' => 'Visit the branch', 'type' => 'office_visit', 'blocks' => []];
    $this->put($this->url, ['name' => $this->target->name, 'application_guide_payload' => json_encode([$row])])->assertSessionHasNoErrors();
    $rows = editableGuide($this->target);
    $row = $rows[0];
    $ids = array_column($row['steps'], 'id');
    $blockId = $row['steps'][0]['blocks'][0]['id'];
    $row['steps'] = array_reverse($row['steps']);
    $row['steps'][1]['blocks'] = [$row['steps'][1]['blocks'][0]];
    $this->put($this->url, ['name' => $this->target->name, 'application_guide_payload' => json_encode([$row])])->assertSessionHasNoErrors();
    expect($set->applicationSteps()->pluck('id')->all())->toBe(array_reverse($ids));
    $this->assertDatabaseHas('government_id_application_step_blocks', ['id' => $blockId]);
    expect(GovernmentIdApplicationStepBlock::count())->toBe(1);
    $row['steps'] = [];
    $this->put($this->url, ['name' => $this->target->name, 'application_guide_payload' => json_encode([$row])])->assertSessionHasNoErrors();
    $this->assertModelExists($set); $this->assertModelExists($keep);
    expect($set->applicationSteps()->count())->toBe(0)->and(GovernmentIdApplicationStepBlock::count())->toBe(0);
});

it('preserves guides when omitted and leaves existing resident API fields unchanged', function () {
    $listBefore = $this->getJson('/api/government-ids')->assertOk()->json();
    $detailBefore = \Illuminate\Support\Arr::except($this->getJson('/api/government-ids/'.$this->target->id)->assertOk()->json('data'), ['requirement_sets']);
    $this->put($this->url, ['name' => $this->target->name, 'application_guide_payload' => json_encode([guideScenario()])])->assertSessionHasNoErrors();
    $this->put($this->url, ['name' => $this->target->name])->assertSessionHasNoErrors();
    expect(GovernmentIdApplicationStep::count())->toBe(1)
        ->and($this->getJson('/api/government-ids')->assertOk()->json())->toBe($listBefore)
        ->and(\Illuminate\Support\Arr::except($this->getJson('/api/government-ids/'.$this->target->id)->assertOk()->json('data'), ['requirement_sets']))->toBe($detailBefore);
});

it('rejects malformed guides without partially changing the ID', function ($payload) {
    $before = ContentChangeLog::count();
    $this->putJson($this->url, ['name' => 'Must not save', 'application_guide_payload' => is_string($payload) ? $payload : json_encode($payload)])->assertUnprocessable();
    expect($this->target->fresh()->name)->toBe('Application guide example')->and(GovernmentIdApplicationStep::count())->toBe(0)->and(ContentChangeLog::count())->toBe($before);
})->with([
    'bad json' => ['{'],
    'object instead of list' => ['{}'],
    'bad scenario' => [['bad']],
    'missing steps' => [[['application_type' => 'new', 'applicant_type' => 'adult']]],
    'blank title' => [[guideScenario(['steps' => [['title' => '  ', 'type' => 'general', 'blocks' => []]]])]],
    'bad blocks' => [[guideScenario(['steps' => [['title' => 'A step', 'type' => 'general', 'blocks' => 'bad']]])]],
    'reversed age' => [[guideScenario(['applicant_type' => 'custom', 'min_age' => 30, 'max_age' => 18])]],
]);

it('rejects unsafe rich text links and copied reference data', function () {
    $unsafe = [
        ['type' => 'reminder', 'content' => ['body' => ['type' => 'script', 'text' => 'alert(1)']]],
        ['type' => 'official_link', 'content' => ['label' => 'Click', 'url' => 'javascript:alert(1)']],
        ['type' => 'fees', 'content' => ['intro' => guideDocument(), 'amount' => 500]],
        ['type' => 'custom', 'content' => ['body' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'attrs' => ['style' => 'color:red']]]]]],
    ];
    foreach ($unsafe as $block) {
        $row = guideScenario(); $row['steps'][0]['blocks'] = [$block];
        $this->putJson($this->url, ['name' => $this->target->name, 'application_guide_payload' => json_encode([$row])])->assertUnprocessable();
    }
    expect(GovernmentIdApplicationStep::count())->toBe(0);
    expect(GuideRichText::render(guideDocument('<script>alert(1)</script>')))->not->toContain('<script>')->toContain('&lt;script&gt;');
});

it('blocks cross ID scenario step and block adoption with transaction rollback', function () {
    $other = GovernmentId::create(['name' => 'Another ID']);
    foreach ([$this->target, $other] as $id) $this->put('/admin/government-ids/'.$id->id, ['name' => $id->name, 'application_guide_payload' => json_encode([guideScenario()])])->assertSessionHasNoErrors();
    $mine = editableGuide($this->target)[0]; $theirs = editableGuide($other)[0];
    foreach (['scenario', 'step', 'block'] as $level) {
        $row = $mine;
        if ($level === 'scenario') $row['id'] = $theirs['id'];
        elseif ($level === 'step') $row['steps'][0]['id'] = $theirs['steps'][0]['id'];
        else $row['steps'][0]['blocks'][0]['id'] = $theirs['steps'][0]['blocks'][0]['id'];
        $this->putJson($this->url, ['name' => 'Do not save', 'application_guide_payload' => json_encode([$row])])->assertUnprocessable();
        expect($this->target->fresh()->name)->toBe('Application guide example');
    }
    expect(GovernmentIdApplicationStep::count())->toBe(2)->and(GovernmentIdApplicationStepBlock::count())->toBe(4);
});

it('uses live scenario requirements fees offices and processing time on View', function () {
    $row = guideScenario();
    $row['steps'][0]['blocks'] = array_map(fn ($type) => ['type' => $type, 'content' => ['intro' => guideDocument()]], ['requirements', 'fees', 'offices', 'processing_time']);
    $this->put($this->url, ['name' => $this->target->name, 'application_guide_payload' => json_encode([$row])])->assertSessionHasNoErrors();
    $set = $this->target->requirementSets()->firstOrFail();
    $group = $set->groups()->create(['title' => 'Proof to bring', 'rule' => 'all']);
    $item = $group->items()->create(['type' => 'custom', 'custom_name' => 'Updated supporting item']);
    $this->target->fees()->create(['label' => 'Current processing fee', 'type' => 'fixed', 'amount_min' => 123]);
    $office = \App\Models\Office::create(['name' => 'Nearby branch']);
    $this->target->offices()->attach($office->id);
    $this->target->update(['processing_time' => '7 working days']);
    $this->get($this->url)->assertOk()->assertSee('Updated supporting item')->assertSee('Current processing fee')->assertSee('Nearby branch')->assertSee('7 working days');
    $item->update(['custom_name' => 'Revised supporting item']);
    $this->get($this->url)->assertOk()->assertSee('Revised supporting item')->assertDontSee('Updated supporting item');
    foreach (GovernmentIdApplicationStepBlock::all() as $block) expect(array_keys($block->content))->toBe(['intro']);
});

it('retains the guide payload after unrelated validation failure and renders malformed old data safely', function () {
    $payload = json_encode([guideScenario()]);
    $this->from($this->url.'/edit')->put($this->url, ['name' => '', 'application_guide_payload' => $payload])->assertSessionHasErrors('name')->assertSessionHasInput('application_guide_payload', $payload);
    $this->get($this->url.'/edit')->assertOk()->assertSee('governmentIdGuide', false);
    $this->withSession(['_old_input' => ['application_guide_payload' => ['bad']]])->get($this->url.'/edit')->assertOk();
});

it('records guide only changes but skips unchanged saves', function () {
    $this->put($this->url, ['name' => $this->target->name, 'application_guide_payload' => json_encode([guideScenario()])])->assertSessionHasNoErrors();
    $rows = editableGuide($this->target);
    $before = ContentChangeLog::count();
    $this->put($this->url, ['name' => $this->target->name, 'application_guide_payload' => json_encode($rows)])->assertSessionHasNoErrors();
    expect(ContentChangeLog::count())->toBe($before);
    $rows[0]['steps'][0]['title'] = 'Updated guide step';
    $this->put($this->url, ['name' => $this->target->name, 'application_guide_payload' => json_encode($rows)])->assertSessionHasNoErrors();
    expect(ContentChangeLog::latest('id')->first()->changed_fields)->toContain('application_guide');
});

it('protects guide saves from guests residents and inactive researchers', function () {
    $data = ['name' => $this->target->name, 'application_guide_payload' => json_encode([guideScenario()])];
    auth()->logout(); $this->putJson($this->url, $data)->assertUnauthorized();
    foreach ([['role' => 'resident'], ['role' => 'researcher', 'is_active' => false]] as $attributes) {
        $this->actingAs(User::factory()->create($attributes)); $this->putJson($this->url, $data)->assertForbidden();
    }
    expect(GovernmentIdApplicationStep::count())->toBe(0);
});

it('rolls back guide and ID changes if the guide audit fails', function () {
    $this->withoutExceptionHandling();
    $dispatcher = ContentChangeLog::getEventDispatcher(); ContentChangeLog::setEventDispatcher(clone $dispatcher);
    ContentChangeLog::creating(function ($log) { if (in_array('application_guide', $log->changed_fields ?? [])) throw new RuntimeException('Guide audit failed'); });
    try {
        expect(fn () => $this->put($this->url, ['name' => 'Rollback name', 'application_guide_payload' => json_encode([guideScenario()])]))->toThrow(RuntimeException::class, 'Guide audit failed');
        expect($this->target->fresh()->name)->toBe('Application guide example')->and(GovernmentIdApplicationStep::count())->toBe(0)->and(GovernmentIdRequirementSet::count())->toBe(0);
    } finally { ContentChangeLog::setEventDispatcher($dispatcher); }
});


it('accepts the installed editor link attributes and renders safe links', function () {
    $document = guideDocument('Official appointment page');
    $document['content'][0]['content'][0]['marks'] = [['type' => 'link', 'attrs' => [
        'href' => 'https://example.gov.ph/appointments', 'target' => '_blank',
        'rel' => 'noopener noreferrer nofollow', 'class' => null, 'title' => null,
    ]]];
    $row = guideScenario();
    $row['steps'][0]['blocks'] = [['type' => 'custom', 'content' => ['body' => $document]]];
    $this->put($this->url, ['name' => $this->target->name, 'application_guide_payload' => json_encode([$row])])->assertSessionHasNoErrors();
    $this->get($this->url)->assertOk()->assertSee('href="https://example.gov.ph/appointments"', false);
    $document['content'][0]['content'][0]['marks'][0]['attrs']['href'] = 'javascript:alert(1)';
    expect(GuideRichText::render($document))->toBe('');
});


it('shows structured content without legacy duplicates while preserving stored compatibility text', function () {
    $this->target->update([
        'eligibility' => 'Example citizenship eligibility',
        'requirements' => 'Legacy requirements summary',
        'prerequisite_notes' => 'Legacy prerequisite summary',
        'renewal_process' => 'Earlier renewal instructions',
    ]);
    $this->get($this->url)->assertOk()
        ->assertSee('Legacy requirements summary')->assertSee('Keep the earlier instructions');
    $this->put($this->url, [
        'name' => $this->target->name,
        'application_guide_payload' => json_encode([guideScenario()]),
    ])->assertSessionHasNoErrors();
    $set = $this->target->requirementSets()->firstOrFail();
    // A guide-only scenario must not hide older requirements.
    $this->get($this->url)->assertOk()->assertSee('Legacy requirements summary');
    $group = $set->groups()->create(['title' => 'Proof to bring', 'rule' => 'all']);
    $group->items()->create(['type' => 'custom', 'custom_name' => 'Structured supporting item']);
    $this->get($this->url)->assertOk()
        ->assertSee('Example citizenship eligibility')->assertSee('Structured supporting item')
        ->assertSee('Prepare your application')->assertSee('Earlier renewal instructions')
        ->assertDontSee('Requirements &amp; Eligibility', false)
        ->assertDontSee('Legacy requirements summary')->assertDontSee('Legacy prerequisite summary')
        ->assertDontSee('Keep the earlier instructions')->assertDontSee('Existing Guide Text');
    expect($this->target->fresh()->application_process)->toBe('Keep the earlier instructions')
        ->and($this->target->fresh()->requirements)->toBe('Legacy requirements summary');
    $set->applicationSteps()->delete();
    $this->get($this->url)->assertOk()->assertSee('Keep the earlier instructions');
});
