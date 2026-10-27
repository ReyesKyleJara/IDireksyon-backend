<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\User;
use App\Support\AcquisitionType;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
});

dataset('acquisition catalogs', [
    'government IDs' => [GovernmentId::class, 'government-ids', 'government_ids'],
    'documents' => [Document::class, 'documents', 'documents'],
]);

it('defaults omitted acquisition type in database inserts and models', function ($model, $resource, $table) {
    DB::table($table)->insert(['name' => 'Legacy-style insert']);
    expect($model::sole()->acquisition_type)->toBe('general_public')
        ->and((new $model)->acquisition_type)->toBe('general_public');
    $this->get(route('admin.'.$resource.'.edit', $model::sole()))->assertOk()
        ->assertSee('value="general_public" selected', false);
})->with('acquisition catalogs');

it('backfills existing rows without losing content and rolls back only the added metadata', function () {
    $migration = require database_path('migrations/2026_10_27_000100_add_acquisition_type_to_catalog_tables.php');
    $migration->down();
    foreach (['government_ids', 'documents'] as $table) {
        DB::table($table)->insert(['name' => 'Before migration', 'description' => 'Preserve research', 'application_availability' => 'discontinued']);
    }
    $migration->up();
    foreach (['government_ids', 'documents'] as $table) {
        $row = DB::table($table)->sole();
        expect($row->acquisition_type)->toBe('general_public')->and($row->description)->toBe('Preserve research')
            ->and($row->application_availability)->toBe('discontinued');
    }
    $migration->down();
    foreach (['government_ids', 'documents'] as $table) {
        expect(DB::table($table)->sole()->description)->toBe('Preserve research')
            ->and(DB::table($table)->sole()->application_availability)->toBe('discontinued');
    }
    $migration->up();
});

it('creates and updates each supported value through normal forms and wizards', function ($model, $resource) {
    foreach (AcquisitionType::LABELS as $value => $label) {
        $this->post(route('admin.'.$resource.'.store'), ['name' => 'Direct '.$label, 'acquisition_type' => $value, 'application_availability' => 'limited_conditional'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $record = $model::where('name', 'Direct '.$label)->sole();
        expect($record->acquisition_type)->toBe($value);
        $this->put(route('admin.'.$resource.'.update', $record), ['name' => $record->name, 'acquisition_type' => 'general_public'])
            ->assertSessionHasNoErrors();
        expect($record->fresh()->acquisition_type)->toBe('general_public');

        $key = $this->get(route('admin.'.$resource.'.create'))->assertOk()
            ->assertSee('Acquisition Type')->assertSee($label)->viewData('creationKey');
        $this->post(route('admin.'.$resource.'.wizard.store'), [
            'creation_key' => $key, 'name' => 'Wizard '.$label, 'acquisition_type' => $value,
            'application_availability' => 'limited_conditional',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $record = $model::where('name', 'Wizard '.$label)->sole();
        expect($record->acquisition_type)->toBe($value);
        $this->get(route('admin.'.$resource.'.edit-wizard.show', [$record, 'basic']))
            ->assertOk()->assertSee('value="'.$value.'" selected', false);
        foreach (['wizard', 'edit-wizard'] as $mode) {
            $this->get(route('admin.'.$resource.'.'.$mode.'.show', [$record, 'review']))
                ->assertOk()->assertSee('Acquisition Type: '.$label)->assertDontSee('profession_based');
        }
        $this->get(route('admin.'.$resource.'.show', $record))->assertOk()
            ->assertSee('Acquisition Type')->assertSee($label)->assertDontSee('profession_based');
        $this->post(route('admin.'.$resource.'.edit-wizard.save', [$record, 'basic']), ['name' => $record->name])
            ->assertSessionHasNoErrors();
        expect($record->fresh()->acquisition_type)->toBe($value);
        $next = $value === 'general_public' ? 'profession_based' : 'general_public';
        $this->post(route('admin.'.$resource.'.edit-wizard.save', [$record, 'basic']), [
            'name' => $record->name, 'acquisition_type' => $next,
        ])->assertSessionHasNoErrors();
        expect($record->fresh()->acquisition_type)->toBe($next)
            ->and($record->fresh()->application_availability)->toBe('limited_conditional');
        $this->post(route('admin.'.$resource.'.edit-wizard.save', [$record, 'fees']), ['fees' => []])
            ->assertSessionHasNoErrors();
        expect($record->fresh()->acquisition_type)->toBe($next)
            ->and($record->fresh()->application_availability)->toBe('limited_conditional');
        $this->get(route('admin.'.$resource.'.edit-wizard.show', [$record, 'basic']))
            ->assertOk()->assertSee('value="'.$next.'" selected', false);
    }
})->with('acquisition catalogs');

it('rejects invalid acquisition type in full forms and both wizard flows', function ($model, $resource) {
    $record = $model::create(['name' => 'Keep value', 'acquisition_type' => 'special_eligibility']);
    foreach (['informational_only', 'unknown', '', null, ['general_public']] as $invalid) {
        $data = ['name' => 'Keep value', 'acquisition_type' => $invalid];
        $this->postJson(route('admin.'.$resource.'.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('acquisition_type');
        $this->putJson(route('admin.'.$resource.'.update', $record), $data)->assertUnprocessable()->assertJsonValidationErrors('acquisition_type');
        $key = $this->get(route('admin.'.$resource.'.create'))->viewData('creationKey');
        $this->postJson(route('admin.'.$resource.'.wizard.store'), $data + ['creation_key' => $key])
            ->assertUnprocessable()->assertJsonValidationErrors('acquisition_type');
        foreach (['wizard', 'edit-wizard'] as $mode) {
            $this->postJson(route('admin.'.$resource.'.'.$mode.'.save', [$record, 'basic']), $data)
                ->assertUnprocessable()->assertJsonValidationErrors('acquisition_type');
        }
    }
    expect($record->fresh()->acquisition_type)->toBe('special_eligibility')->and($model::count())->toBe(1);
})->with('acquisition catalogs');

it('exposes acquisition type without filtering public and resident catalogs', function () {
    $ids = [];
    foreach (array_keys(AcquisitionType::LABELS) as $value) {
        $ids[] = GovernmentId::create(['name' => $value, 'acquisition_type' => $value, 'application_availability' => 'limited_conditional']);
        Document::create(['name' => $value, 'acquisition_type' => $value, 'application_availability' => 'limited_conditional']);
    }
    $this->getJson('/api/government-ids')->assertOk()->assertJsonCount(5, 'data');
    foreach ($ids as $id) {
        $this->getJson('/api/government-ids/'.$id->id)->assertOk()
            ->assertJsonPath('data.acquisition_type', $id->acquisition_type);
    }
    $resident = User::factory()->create(['role' => 'resident', 'is_active' => true]);
    $data = $this->actingAs($resident)->getJson('/api/auth/inventory')->assertOk()
        ->assertJsonCount(5, 'data.government_ids')->assertJsonCount(5, 'data.documents')->json('data');
    foreach (['government_ids', 'documents'] as $key) {
        expect(array_column($data[$key], 'acquisition_type'))->toEqualCanonicalizing(array_keys(AcquisitionType::LABELS));
    }
    $public = $this->getJson('/api/government-ids')->json('data');
    expect(array_column($public, 'acquisition_type'))->toEqualCanonicalizing(array_keys(AcquisitionType::LABELS));
});
