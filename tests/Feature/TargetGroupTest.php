<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
});

dataset('target group catalogs', [
    'government IDs' => [GovernmentId::class, 'government-ids', 'government_ids'],
    'documents' => [Document::class, 'documents', 'documents'],
]);

it('defaults existing rows to null and rolls back without altering catalog metadata', function () {
    $migration = require database_path('migrations/2026_10_28_000000_add_target_group_to_catalog_tables.php');
    $migration->down();
    $original = ['name' => 'Existing research', 'description' => 'Preserve content',
        'application_availability' => 'limited_conditional', 'acquisition_type' => 'profession_based'];
    foreach (['government_ids', 'documents'] as $table) DB::table($table)->insert($original);
    $migration->up();
    foreach (['government_ids', 'documents'] as $table) {
        expect(DB::table($table)->sole()->target_group)->toBeNull();
        DB::table($table)->update(['target_group' => 'Seafarers']);
    }
    $migration->down();
    foreach (['government_ids', 'documents'] as $table) {
        $row = (array) DB::table($table)->sole();
        expect(array_intersect_key($row, $original))->toBe($original)->and($row)->not->toHaveKey('target_group');
    }
    $migration->up();
});

it('allows omitted target groups in direct and wizard creation', function ($model, $resource, $table) {
    DB::table($table)->insert(['name' => 'Database default']);
    expect($model::sole()->target_group)->toBeNull()->and((new $model)->target_group)->toBeNull();
    $this->post(route('admin.'.$resource.'.store'), ['name' => 'Direct default'])->assertSessionHasNoErrors();
    expect($model::where('name', 'Direct default')->sole()->target_group)->toBeNull();
    $page = $this->get(route('admin.'.$resource.'.create'))->assertOk()
        ->assertSee('Target Group / Qualification')->assertSee('maxlength="1000"', false);
    $this->post(route('admin.'.$resource.'.wizard.store'), [
        'creation_key' => $page->viewData('creationKey'), 'name' => 'Wizard default',
    ])->assertSessionHasNoErrors();
    $record = $model::where('name', 'Wizard default')->sole();
    expect($record->target_group)->toBeNull();
    foreach (['wizard', 'edit-wizard'] as $mode) {
        $this->get(route('admin.'.$resource.'.'.$mode.'.show', [$record, 'review']))->assertOk()
            ->assertDontSee('Target Group / Qualification');
    }
    $this->get(route('admin.'.$resource.'.show', $record))->assertOk()->assertDontSee('Target Group / Qualification');
})->with('target group catalogs');

it('creates edits preserves and clears a free text target group through direct forms', function ($model, $resource) {
    $data = ['name' => 'Direct target', 'target_group' => '  Seafarers  ',
        'application_availability' => 'temporarily_unavailable', 'acquisition_type' => 'profession_based'];
    $this->post(route('admin.'.$resource.'.store'), $data)->assertSessionHasNoErrors();
    $record = $model::sole();
    expect($record->target_group)->toBe('Seafarers');
    foreach ([['target_group' => 'Residents aged 60+'], [], ['target_group' => '   '], ['target_group' => null]] as $update) {
        $previous = $record->fresh()->target_group;
        $this->put(route('admin.'.$resource.'.update', $record), ['name' => $record->name] + $update)->assertSessionHasNoErrors();
        $expected = array_key_exists('target_group', $update) ? (filled($update['target_group']) ? $update['target_group'] : null) : $previous;
        expect($record->fresh()->target_group)->toBe($expected)
            ->and($record->fresh()->application_availability)->toBe('temporarily_unavailable')
            ->and($record->fresh()->acquisition_type)->toBe('profession_based');
    }
})->with('target group catalogs');

it('saves and preserves target groups across both wizard flows and supports clearing', function ($model, $resource) {
    $key = $this->get(route('admin.'.$resource.'.create'))->viewData('creationKey');
    $this->post(route('admin.'.$resource.'.wizard.store'), [
        'creation_key' => $key, 'name' => 'Wizard target', 'target_group' => 'Seafarers',
        'application_availability' => 'limited_conditional', 'acquisition_type' => 'profession_based',
    ])->assertSessionHasNoErrors();
    $record = $model::sole();
    expect($record->target_group)->toBe('Seafarers');
    foreach (['wizard', 'edit-wizard'] as $mode) {
        $prefix = 'admin.'.$resource.'.'.$mode;
        $this->get(route($prefix.'.show', [$record, 'basic']))->assertOk()->assertSee('Seafarers');
        $this->post(route($prefix.'.save', [$record, 'basic']), ['name' => $record->name])->assertSessionHasNoErrors();
        $this->post(route($prefix.'.save', [$record, 'fees']), ['fees' => []])->assertSessionHasNoErrors();
        expect($record->fresh()->target_group)->toBe('Seafarers');
        $this->get(route($prefix.'.show', [$record, 'review']))->assertOk()
            ->assertSee('Target Group / Qualification: Seafarers');
        $this->post(route($prefix.'.save', [$record, 'basic']), ['name' => $record->name, 'target_group' => 'Maritime professionals'])
            ->assertSessionHasNoErrors();
        expect($record->fresh()->target_group)->toBe('Maritime professionals');
        $this->get(route($prefix.'.show', [$record, 'basic']))->assertOk()->assertSee('Maritime professionals');
        $this->get(route('admin.'.$resource.'.show', $record))->assertOk()->assertSee('Target Group / Qualification')->assertSee('Maritime professionals');
        $this->post(route($prefix.'.save', [$record, 'basic']), ['name' => $record->name, 'target_group' => '   '])
            ->assertSessionHasNoErrors();
        expect($record->fresh()->target_group)->toBeNull()
            ->and($record->fresh()->application_availability)->toBe('limited_conditional')
            ->and($record->fresh()->acquisition_type)->toBe('profession_based');
        $this->get(route($prefix.'.show', [$record, 'review']))->assertOk()->assertDontSee('Target Group / Qualification');
        $this->get(route('admin.'.$resource.'.show', $record))->assertOk()->assertDontSee('Target Group / Qualification');
        $this->post(route($prefix.'.save', [$record, 'basic']), ['name' => $record->name, 'target_group' => 'Seafarers'])->assertSessionHasNoErrors();
    }
})->with('target group catalogs');

it('validates type and maximum length through all save routes', function ($model, $resource) {
    $record = $model::create(['name' => 'Keep research', 'target_group' => 'Existing group']);
    foreach ([str_repeat('x', 1001), ['invalid'], 123] as $invalid) {
        $data = ['name' => $record->name, 'target_group' => $invalid];
        $this->postJson(route('admin.'.$resource.'.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('target_group');
        $this->putJson(route('admin.'.$resource.'.update', $record), $data)->assertUnprocessable()->assertJsonValidationErrors('target_group');
        $key = $this->get(route('admin.'.$resource.'.create'))->viewData('creationKey');
        $this->postJson(route('admin.'.$resource.'.wizard.store'), $data + ['creation_key' => $key])
            ->assertUnprocessable()->assertJsonValidationErrors('target_group');
        foreach (['wizard', 'edit-wizard'] as $mode) {
            $this->postJson(route('admin.'.$resource.'.'.$mode.'.save', [$record, 'basic']), $data)
                ->assertUnprocessable()->assertJsonValidationErrors('target_group');
        }
    }
    expect($record->fresh()->target_group)->toBe('Existing group')->and($model::count())->toBe(1);
    $this->put(route('admin.'.$resource.'.update', $record), ['name' => $record->name, 'target_group' => str_repeat('x', 1000)])
        ->assertSessionHasNoErrors();
    expect($record->fresh()->target_group)->toBe(str_repeat('x', 1000));
})->with('target group catalogs');

it('escapes free text and preserves entered target group after validation errors', function ($model, $resource) {
    $text = '<script>alert("test")</script>';
    $record = $model::create(['name' => 'Safe display', 'target_group' => $text]);
    foreach (['wizard', 'edit-wizard'] as $mode) {
        foreach (['basic', 'review'] as $step) {
            $this->get(route('admin.'.$resource.'.'.$mode.'.show', [$record, $step]))
                ->assertOk()->assertSee($text)->assertDontSee($text, false);
        }
    }
    $this->get(route('admin.'.$resource.'.show', $record))->assertOk()->assertSee($text)->assertDontSee($text, false);
    $url = route('admin.'.$resource.'.edit-wizard.show', [$record, 'basic']);
    $this->from($url)->post(route('admin.'.$resource.'.edit-wizard.save', [$record, 'basic']), ['name' => '', 'target_group' => 'Retain input'])
        ->assertSessionHasErrors('name')->assertSessionHasInput('target_group', 'Retain input');
    $this->get($url)->assertOk()->assertSee('Retain input');
})->with('target group catalogs');

it('exposes null and text without filtering catalogs or changing inventory ownership', function () {
    foreach ([null, 'Seafarers'] as $target) {
        GovernmentId::create(['name' => $target ?? 'General', 'target_group' => $target,
            'application_availability' => 'discontinued', 'acquisition_type' => 'profession_based']);
        Document::create(['name' => $target ?? 'General', 'target_group' => $target]);
    }
    $list = $this->getJson('/api/government-ids')->assertOk()->assertJsonCount(2, 'data')->json('data');
    expect(array_column($list, 'target_group'))->toBe([null, 'Seafarers']);
    foreach (GovernmentId::all() as $record) {
        $this->getJson('/api/government-ids/'.$record->id)->assertOk()->assertJsonPath('data.target_group', $record->target_group)
            ->assertJsonPath('data.application_availability', 'discontinued')->assertJsonPath('data.acquisition_type', 'profession_based');
    }
    $resident = User::factory()->create(['role' => 'resident', 'is_active' => true]);
    $resident->ownedGovernmentIds()->attach(GovernmentId::where('name', 'Seafarers')->sole());
    $resident->ownedDocuments()->attach(Document::where('name', 'Seafarers')->sole());
    $response = $this->actingAs($resident)->getJson('/api/auth/inventory')->assertOk()
        ->assertJsonCount(2, 'data.government_ids')->assertJsonCount(2, 'data.documents');
    foreach (['government_ids', 'documents'] as $kind) {
        $rows = $response->json('data.'.$kind);
        expect(array_column($rows, 'target_group'))->toBe([null, 'Seafarers'])
            ->and(array_column($rows, 'owned'))->toBe([false, true]);
    }
});
