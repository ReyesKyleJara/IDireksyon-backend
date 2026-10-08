<?php

use App\Models\ContentChangeLog;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\GovernmentIdRequirementGroup;
use App\Models\GovernmentIdRequirementItem;
use App\Models\GovernmentIdRequirementSet;
use App\Models\GovernmentIdRequirementWay;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

function waysItem(array $attributes = []): array
{
    return array_replace(['type' => 'custom', 'custom_name' => 'Photo', 'submission_format' => 'not_specified'], $attributes);
}

function waysPayload(array $ways = []): array
{
    return [
        'application_type' => 'new', 'applicant_type' => 'adult',
        'groups' => [[
            'title' => 'Proof of Identity', 'rule' => 'all', 'condition_type' => 'always',
            'ways' => $ways ?: [[
                'required_count' => 1, 'qualification_type' => 'none', 'qualification_scope' => 'every',
                'items' => [waysItem()],
            ]],
        ]],
    ];
}

function editableWays(array $set): array
{
    unset($set['id'], $set['label']);
    foreach ($set['groups'] as &$group) {
        unset($group['condition_label']);
        foreach ($group['ways'] as &$way) {
            foreach ($way['items'] as &$item) {
                unset($item['name']);
            }
            unset($item);
        }
        unset($way);
    }
    unset($group);
    return $set;
}

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
    $this->target = GovernmentId::create(['name' => 'Target ID', 'requirements' => 'Existing API guidance']);
    $this->url = '/admin/government-ids/'.$this->target->id.'/checklists';
});

it('stores alternative ways as one requirement and preserves ID and document references', function () {
    $id = GovernmentId::create(['name' => 'Primary ID']);
    $doc = Document::create(['name' => 'Secondary Document']);
    $ways = [
        ['required_count' => 1, 'qualification_type' => 'current_address', 'qualification_scope' => 'every',
            'items' => [waysItem(['type' => 'government_id', 'government_id_id' => $id->id])]],
        ['required_count' => 2, 'qualification_type' => 'photo_signature', 'qualification_scope' => 'at_least_one',
            'items' => [waysItem(['type' => 'document', 'document_id' => $doc->id]), waysItem(['custom_name' => 'Legacy secondary item'])]],
    ];
    $before = \Illuminate\Support\Arr::except($this->getJson('/api/government-ids/'.$this->target->id)->assertOk()->json('data'), ['requirement_sets']);
    $saved = $this->postJson($this->url, waysPayload($ways))->assertCreated()
        ->assertJsonCount(1, 'checklist.groups')->assertJsonCount(2, 'checklist.groups.0.ways')
        ->assertJsonPath('checklist.groups.0.ways.1.required_count', 2)
        ->assertJsonPath('checklist.groups.0.ways.1.qualification_scope', 'at_least_one')
        ->assertJsonPath('checklist.groups.0.ways.1.qualification_type', 'photo_signature')->json('checklist');
    expect(GovernmentIdRequirementGroup::count())->toBe(1)
        ->and(GovernmentIdRequirementWay::count())->toBe(2)
        ->and(GovernmentIdRequirementItem::where('government_id_id', $id->id)->count())->toBe(1);
    $logs = ContentChangeLog::count();
    $this->putJson($this->url.'/'.$saved['id'], editableWays($saved))->assertOk()->assertJsonPath('checklist', $saved);
    expect(ContentChangeLog::count())->toBe($logs);
    expect(\Illuminate\Support\Arr::except($this->getJson('/api/government-ids/'.$this->target->id)->assertOk()->json('data'), ['requirement_sets']))->toBe($before);
    $this->get('/admin/government-ids/'.$this->target->id)->assertOk()
        ->assertSee('Proof of Identity')->assertDontSee('Edit checklist')->assertDontSee('checklist-dialog');
});

it('rejects counts larger than the options and duplicate accepted records atomically', function () {
    $doc = Document::create(['name' => 'One Document']);
    // Creating the fixture ID and Document legitimately records audit entries.
    $logsBefore = ContentChangeLog::count();
    $assertNoWrites = function () use ($logsBefore) {
        expect(GovernmentIdRequirementSet::count())->toBe(0)
            ->and(GovernmentIdRequirementGroup::count())->toBe(0)
            ->and(GovernmentIdRequirementWay::count())->toBe(0)
            ->and(GovernmentIdRequirementItem::count())->toBe(0)
            ->and(ContentChangeLog::count())->toBe($logsBefore);
    };

    $payload = waysPayload();
    $payload['groups'][0]['ways'][0]['required_count'] = 2;
    $this->postJson($this->url, $payload)->assertUnprocessable()
        ->assertJsonValidationErrors('groups.0.ways.0.required_count');
    $assertNoWrites();

    $payload['groups'][0]['ways'][0]['items'] = array_fill(0, 2, waysItem(['type' => 'document', 'document_id' => $doc->id]));
    $this->postJson($this->url, $payload)->assertUnprocessable()->assertJsonValidationErrors('groups.0.ways.0.items');
    $assertNoWrites();
});

it('validates malformed ways and qualification fields', function ($field, $value) {
    $payload = waysPayload();
    if ($field === 'qualification_scope') {
        $payload['groups'][0]['ways'][0]['qualification_type'] = 'photo';
        $payload['groups'][0]['ways'][0]['required_count'] = 2;
        $payload['groups'][0]['ways'][0]['items'][] = waysItem(['custom_name' => 'Second item']);
    }
    $payload['groups'][0]['ways'][0][$field] = $value;
    $this->postJson($this->url, $payload)->assertUnprocessable();
    expect(GovernmentIdRequirementWay::count())->toBe(0);
})->with([
    ['required_count', 0], ['required_count', 1.5], ['required_count', 51],
    ['qualification_type', 'invalid'], ['qualification_type', 'custom'],
    ['qualification_scope', 'invalid'], ['items', 'invalid'], ['items', []],
]);

it('keeps custom quantity separate from submission copies and normalizes unused values', function () {
    $payload = waysPayload();
    $payload['groups'][0]['condition_type'] = 'representative';
    $payload['groups'][0]['ways'][0]['qualification_custom'] = 'stale';
    $payload['groups'][0]['ways'][0]['qualification_scope'] = 'at_least_one';
    $payload['groups'][0]['ways'][0]['items'][0] = waysItem([
        'quantity' => 2, 'submission_format' => 'photocopy', 'copies' => 1, 'instructions' => 'White background.',
    ]);
    $saved = $this->postJson($this->url, $payload)->assertCreated()
        ->assertJsonPath('checklist.groups.0.ways.0.items.0.quantity', 2)
        ->assertJsonPath('checklist.groups.0.ways.0.items.0.copies', 1)
        ->assertJsonPath('checklist.groups.0.ways.0.qualification_custom', null)
        ->assertJsonPath('checklist.groups.0.ways.0.qualification_scope', 'every')->json('checklist');
    $payload = editableWays($saved);
    $payload['groups'][0]['ways'][0]['items'][0]['quantity'] = 0;
    $this->putJson($this->url.'/'.$saved['id'], $payload)->assertUnprocessable();
    expect(GovernmentIdRequirementItem::sole()->quantity)->toBe(2);
});

it('converts legacy all-required entries without losing item IDs or submission details', function () {
    $set = $this->target->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'adult']);
    $group = $set->groups()->create(['rule' => 'all', 'condition_type' => 'spouse_surname']);
    $one = $group->items()->create(waysItem(['submission_format' => 'original_photocopy', 'copies' => 1]));
    $two = $group->items()->create(waysItem(['custom_name' => 'Another item']));
    $payload = waysPayload();
    $payload['groups'][0]['id'] = $group->id;
    $payload['groups'][0]['condition_type'] = 'spouse_surname';
    $payload['groups'][0]['ways'][0]['required_count'] = 2;
    $payload['groups'][0]['ways'][0]['items'] = [
        $one->only(['id', ...GovernmentIdRequirementItem::EDITABLE_FIELDS]),
        $two->only(['id', ...GovernmentIdRequirementItem::EDITABLE_FIELDS]),
    ];
    $this->putJson($this->url.'/'.$set->id, $payload)->assertOk()
        ->assertJsonPath('checklist.groups.0.ways.0.required_count', 2)
        ->assertJsonPath('checklist.groups.0.ways.0.items.0.id', $one->id)
        ->assertJsonPath('checklist.groups.0.ways.0.items.0.copies', 1);
    expect($group->items()->count())->toBe(2);
    unset($payload['groups'][0]['ways']);
    $payload['groups'][0]['items'] = [waysItem()];
    $this->putJson($this->url.'/'.$set->id, $payload)->assertUnprocessable();
    expect($group->items()->count())->toBe(2);
});

it('rejects foreign ways and items and rolls back the entire update', function () {
    $one = $this->postJson($this->url, waysPayload())->assertCreated()->json('checklist');
    $two = $this->postJson($this->url, waysPayload())->assertCreated()->json('checklist');
    $payload = editableWays($one);
    $payload['application_type'] = 'renewal';
    $payload['groups'][0]['ways'][0]['id'] = $two['groups'][0]['ways'][0]['id'];
    $this->putJson($this->url.'/'.$one['id'], $payload)->assertNotFound();
    $payload['groups'][0]['ways'][0]['id'] = $one['groups'][0]['ways'][0]['id'];
    $payload['groups'][0]['ways'][0]['items'][0]['id'] = $two['groups'][0]['ways'][0]['items'][0]['id'];
    $this->putJson($this->url.'/'.$one['id'], $payload)->assertNotFound();
    expect(GovernmentIdRequirementSet::find($one['id'])->application_type)->toBe('new');
});

it('cascades removed ways and requirements without deleting directory records', function () {
    $doc = Document::create(['name' => 'Keep this document']);
    $payload = waysPayload();
    $payload['groups'][0]['ways'][0]['items'] = [waysItem(['type' => 'document', 'document_id' => $doc->id])];
    $payload['groups'][0]['ways'][] = waysPayload()['groups'][0]['ways'][0];
    $saved = $this->postJson($this->url, $payload)->assertCreated()->json('checklist');
    $payload = editableWays($saved);
    $removed = array_pop($payload['groups'][0]['ways']);
    $this->putJson($this->url.'/'.$saved['id'], $payload)->assertOk();
    $this->assertDatabaseMissing('government_id_requirement_ways', ['id' => $removed['id']]);
    $this->assertDatabaseMissing('government_id_requirement_items', ['id' => $removed['items'][0]['id']]);
    $this->deleteJson($this->url.'/'.$saved['id'])->assertOk();
    expect(GovernmentIdRequirementWay::count())->toBe(0)->and(GovernmentIdRequirementItem::count())->toBe(0);
    $this->assertModelExists($doc);
});

it('preserves pre-existing rows when adding the schema and guards rollback once used', function () {
    $migration = require database_path('migrations/2026_10_05_000000_add_requirement_ways_and_item_quantities.php');
    $migration->down();
    $set = $this->target->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'adult']);
    $group = $set->groups()->create(['rule' => 'choose_one']);
    $first = $group->items()->create(waysItem(['instructions' => 'Keep this instruction']));
    $group->items()->create(waysItem(['custom_name' => 'Alternative']));
    $migration->up();
    expect($group->fresh()->rule)->toBe('choose_one')
        ->and($first->fresh()->instructions)->toBe('Keep this instruction')
        ->and($first->fresh()->requirement_way_id)->toBeNull()
        ->and($group->ways()->count())->toBe(0);
    $group->ways()->create(['required_count' => 1]);
    expect(fn () => $migration->down())->toThrow(RuntimeException::class, 'Rollback stopped');
    expect(Schema::hasTable('government_id_requirement_ways'))->toBeTrue();
});


it('does not allow moving an existing item into another way through forged identifiers', function () {
    $payload = waysPayload();
    $payload['groups'][0]['ways'][] = waysPayload()['groups'][0]['ways'][0];
    $saved = $this->postJson($this->url, $payload)->assertCreated()->json('checklist');
    $payload = editableWays($saved);
    $payload['groups'][0]['ways'][0]['items'][0]['id'] = $saved['groups'][0]['ways'][1]['items'][0]['id'];
    array_pop($payload['groups'][0]['ways']);
    $this->putJson($this->url.'/'.$saved['id'], $payload)->assertNotFound();
    expect(GovernmentIdRequirementWay::count())->toBe(2)->and(GovernmentIdRequirementItem::count())->toBe(2);
});

it('rolls back ways and item writes if recording the edit fails', function () {
    $this->withoutExceptionHandling();
    $dispatcher = ContentChangeLog::getEventDispatcher();
    ContentChangeLog::setEventDispatcher(clone $dispatcher);
    ContentChangeLog::creating(function () { throw new RuntimeException('Audit failure'); });
    try {
        expect(fn () => $this->postJson($this->url, waysPayload()))->toThrow(RuntimeException::class, 'Audit failure');
        expect(GovernmentIdRequirementSet::count())->toBe(0)
            ->and(GovernmentIdRequirementGroup::count())->toBe(0)
            ->and(GovernmentIdRequirementWay::count())->toBe(0)
            ->and(GovernmentIdRequirementItem::count())->toBe(0);
    } finally {
        ContentChangeLog::setEventDispatcher($dispatcher);
    }
});

it('rejects advanced writes from residents and inactive staff', function () {
    $saved = $this->postJson($this->url, waysPayload())->assertCreated()->json('checklist');
    foreach ([['role' => 'resident'], ['role' => 'researcher', 'is_active' => false]] as $attributes) {
        $this->actingAs(User::factory()->create($attributes));
        $this->postJson($this->url, waysPayload())->assertForbidden();
        $this->putJson($this->url.'/'.$saved['id'], editableWays($saved))->assertForbidden();
        $this->deleteJson($this->url.'/'.$saved['id'])->assertForbidden();
    }
    expect(GovernmentIdRequirementSet::count())->toBe(1);
});
