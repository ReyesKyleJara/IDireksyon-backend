<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\User;
use App\Support\ApplicationAvailability;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
});

dataset('availability catalogs', [
    'government IDs' => [GovernmentId::class, 'government-ids', 'government_ids'],
    'documents' => [Document::class, 'documents', 'documents'],
]);

it('defaults omitted availability in database inserts and models', function ($model, $resource, $table) {
    DB::table($table)->insert(['name' => 'Legacy-style insert']);
    expect($model::sole()->application_availability)->toBe('available')
        ->and((new $model)->application_availability)->toBe('available');
    $this->get(route('admin.'.$resource.'.edit', $model::sole()))->assertOk()
        ->assertSee('value="available" selected', false);
})->with('availability catalogs');

it('backfills existing rows without losing content and rolls back only the added metadata', function () {
    $migration = require database_path('migrations/2026_10_27_000000_add_application_availability_to_catalog_tables.php');
    $migration->down();
    foreach (['government_ids', 'documents'] as $table) {
        DB::table($table)->insert(['name' => 'Before migration', 'description' => 'Preserve research']);
    }
    $migration->up();
    foreach (['government_ids', 'documents'] as $table) {
        $row = DB::table($table)->sole();
        expect($row->application_availability)->toBe('available')->and($row->description)->toBe('Preserve research');
    }
    $migration->down();
    foreach (['government_ids', 'documents'] as $table) {
        expect(DB::table($table)->sole()->description)->toBe('Preserve research');
    }
    $migration->up();
});

it('creates and updates each supported value through normal forms and wizards', function ($model, $resource) {
    foreach (ApplicationAvailability::LABELS as $value => $label) {
        $this->post(route('admin.'.$resource.'.store'), ['name' => 'Direct '.$label, 'application_availability' => $value])
            ->assertSessionHasNoErrors()->assertRedirect();
        $record = $model::where('name', 'Direct '.$label)->sole();
        expect($record->application_availability)->toBe($value);
        $this->put(route('admin.'.$resource.'.update', $record), ['name' => $record->name, 'application_availability' => 'available'])
            ->assertSessionHasNoErrors();
        expect($record->fresh()->application_availability)->toBe('available');

        $key = $this->get(route('admin.'.$resource.'.create'))->assertOk()
            ->assertSee('Application Availability')->assertSee($label)->viewData('creationKey');
        $this->post(route('admin.'.$resource.'.wizard.store'), [
            'creation_key' => $key, 'name' => 'Wizard '.$label, 'application_availability' => $value,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $record = $model::where('name', 'Wizard '.$label)->sole();
        expect($record->application_availability)->toBe($value);
        $this->get(route('admin.'.$resource.'.edit-wizard.show', [$record, 'basic']))
            ->assertOk()->assertSee('value="'.$value.'" selected', false);
        foreach (['wizard', 'edit-wizard'] as $mode) {
            $this->get(route('admin.'.$resource.'.'.$mode.'.show', [$record, 'review']))
                ->assertOk()->assertSee('Application Availability: '.$label)->assertDontSee('limited_conditional');
        }
        $this->get(route('admin.'.$resource.'.show', $record))->assertOk()
            ->assertSee('Application Availability')->assertSee($label)->assertDontSee('limited_conditional');
        $this->post(route('admin.'.$resource.'.edit-wizard.save', [$record, 'basic']), ['name' => $record->name])
            ->assertSessionHasNoErrors();
        expect($record->fresh()->application_availability)->toBe($value);
        $next = $value === 'available' ? 'limited_conditional' : 'available';
        $this->post(route('admin.'.$resource.'.edit-wizard.save', [$record, 'basic']), [
            'name' => $record->name, 'application_availability' => $next,
        ])->assertSessionHasNoErrors();
        expect($record->fresh()->application_availability)->toBe($next);
        $this->get(route('admin.'.$resource.'.edit-wizard.show', [$record, 'basic']))
            ->assertOk()->assertSee('value="'.$next.'" selected', false);
    }
})->with('availability catalogs');

it('rejects invalid availability in full forms and both wizard flows', function ($model, $resource) {
    $record = $model::create(['name' => 'Keep value', 'application_availability' => 'discontinued']);
    foreach (['informational_only', 'unknown', '', null, ['available']] as $invalid) {
        $data = ['name' => 'Keep value', 'application_availability' => $invalid];
        $this->postJson(route('admin.'.$resource.'.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('application_availability');
        $this->putJson(route('admin.'.$resource.'.update', $record), $data)->assertUnprocessable()->assertJsonValidationErrors('application_availability');
        $key = $this->get(route('admin.'.$resource.'.create'))->viewData('creationKey');
        $this->postJson(route('admin.'.$resource.'.wizard.store'), $data + ['creation_key' => $key])
            ->assertUnprocessable()->assertJsonValidationErrors('application_availability');
        foreach (['wizard', 'edit-wizard'] as $mode) {
            $this->postJson(route('admin.'.$resource.'.'.$mode.'.save', [$record, 'basic']), $data)
                ->assertUnprocessable()->assertJsonValidationErrors('application_availability');
        }
    }
    expect($record->fresh()->application_availability)->toBe('discontinued')->and($model::count())->toBe(1);
})->with('availability catalogs');

it('exposes availability without filtering public and resident catalogs', function () {
    $ids = [];
    foreach (array_keys(ApplicationAvailability::LABELS) as $value) {
        $ids[] = GovernmentId::create(['name' => $value, 'application_availability' => $value]);
        Document::create(['name' => $value, 'application_availability' => $value]);
    }
    $this->getJson('/api/government-ids')->assertOk()->assertJsonCount(4, 'data');
    foreach ($ids as $id) {
        $this->getJson('/api/government-ids/'.$id->id)->assertOk()
            ->assertJsonPath('data.application_availability', $id->application_availability);
    }
    $resident = User::factory()->create(['role' => 'resident', 'is_active' => true]);
    $data = $this->actingAs($resident)->getJson('/api/auth/inventory')->assertOk()
        ->assertJsonCount(4, 'data.government_ids')->assertJsonCount(4, 'data.documents')->json('data');
    foreach (['government_ids', 'documents'] as $key) {
        expect(array_column($data[$key], 'application_availability'))->toEqualCanonicalizing(array_keys(ApplicationAvailability::LABELS));
    }
    $public = $this->getJson('/api/government-ids')->json('data');
    expect(array_column($public, 'application_availability'))->toEqualCanonicalizing(array_keys(ApplicationAvailability::LABELS));
});
