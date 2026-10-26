<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
});

function feeChoices(): array
{
    return ['label' => 'Passport Processing Fee', 'type' => 'options', 'options' => [
        ['label' => 'Regular Processing', 'type' => 'fixed', 'amount_min' => 950, 'description' => '10–15 working days'],
        ['label' => 'Expedited Processing', 'type' => 'fixed', 'amount_min' => 1200, 'description' => '5–7 working days'],
    ]];
}

dataset('fee resources', [['government-ids', GovernmentId::class], ['documents', Document::class]]);

it('saves reloads edits reorders and removes child options without replacing the parent', function ($resource, $model) {
    $record = $model::create(['name' => 'Fee test entry']);
    $url = route('admin.'.$resource.'.edit-wizard.save', [$record, 'fees']);
    $payload = feeChoices();
    $this->post($url, ['fees' => [$payload]])->assertSessionHasNoErrors()->assertRedirect();
    $parent = $record->fees()->sole();
    $ids = $parent->options()->pluck('id')->all();
    expect($parent->type)->toBe('options')->and($parent->amount_min)->toBeNull()->and(count($ids))->toBe(2);
    $this->get(route('admin.'.$resource.'.edit-wizard.show', [$record, 'fees']))->assertOk()
        ->assertSee('Regular Processing')->assertSee('Expedited Processing')->assertSee('+ Add another option');
    foreach ($payload['options'] as $index => &$option) $option['id'] = $ids[$index];
    unset($option);
    $payload['id'] = $parent->id;
    $payload['options'] = array_reverse($payload['options']);
    $payload['options'][0]['notes'] = 'Faster processing';
    $this->post($url, ['fees' => [$payload]])->assertSessionHasNoErrors();
    expect($record->fees()->sole()->id)->toBe($parent->id)
        ->and($parent->options()->pluck('id')->all())->toBe(array_reverse($ids));
    $payload['options'][] = ['label' => 'Another', 'type' => 'free'];
    $this->post($url, ['fees' => [$payload]])->assertSessionHasNoErrors();
    expect($parent->options()->count())->toBe(3);
    array_shift($payload['options']);
    $this->post($url, ['fees' => [$payload]])->assertSessionHasNoErrors();
    expect($parent->options()->count())->toBe(2)->and($parent->options()->whereKey($ids[1])->exists())->toBeFalse()
        ->and($record->fees()->sole()->id)->toBe($parent->id);
    $this->get(route('admin.'.$resource.'.edit-wizard.show', [$record, 'review']))->assertOk()->assertSee('Choose one:')->assertSee('Regular Processing');
    $this->get(route('admin.'.$resource.'.show', $record))->assertOk()->assertSee('Choose one:')->assertSee('Regular Processing');
    $parent->delete();
    expect($parent->options()->count())->toBe(0);
})->with('fee resources');

it('keeps simple fees and all existing amount types unchanged', function ($resource, $model) {
    $record = $model::create(['name' => 'Simple fees']);
    $fees = [
        ['label' => 'Fixed', 'type' => 'fixed', 'amount_min' => 100],
        ['label' => 'Range', 'type' => 'range', 'amount_min' => 10, 'amount_max' => 20],
        ['label' => 'Free', 'type' => 'free'],
        ['label' => 'Optional', 'type' => 'varies', 'is_optional' => true],
    ];
    $this->post(route('admin.'.$resource.'.edit-wizard.save', [$record, 'fees']), ['fees' => $fees])->assertSessionHasNoErrors();
    expect($record->fees()->count())->toBe(4);
    foreach ($record->fees as $fee) expect($fee->options)->toHaveCount(0);
    expect($record->fees->first()->amount_min)->toBe('100.00')->and($record->fees->last()->is_optional)->toBeTrue();
})->with('fee resources');

it('rejects incomplete choices invalid ranges and stolen child identities atomically', function ($resource, $model) {
    $record = $model::create(['name' => 'Keep']);
    $url = route('admin.'.$resource.'.edit-wizard.save', [$record, 'fees']);
    $payload = feeChoices();
    $this->post($url, ['fees' => [$payload]])->assertSessionHasNoErrors();
    $parent = $record->fees()->sole();
    $payload['id'] = $parent->id;
    $bad = $payload; array_pop($bad['options']);
    $this->postJson($url, ['fees' => [$bad]])->assertUnprocessable();
    $bad = $payload; $bad['options'][0]['type'] = 'range'; $bad['options'][0]['amount_max'] = 1;
    $this->postJson($url, ['fees' => [$bad]])->assertUnprocessable()->assertJsonValidationErrors('fees.0.options.0.amount_max');
    $other = $record->fees()->create(['label' => 'Other', 'type' => 'options']);
    $foreign = $other->options()->create(['label' => 'Other choice', 'type' => 'free']);
    $payload['options'][0]['id'] = $foreign->id;
    $this->postJson($url, ['fees' => [$payload]])->assertUnprocessable();
    expect($parent->fresh()->label)->toBe('Passport Processing Fee')->and($parent->options()->count())->toBe(2)
        ->and($foreign->fresh()->label)->toBe('Other choice');
})->with('fee resources');

it('serializes nested choose-one options and preserves simple API shape', function () {
    $record = GovernmentId::create(['name' => 'API fee test']);
    $this->post(route('admin.government-ids.edit-wizard.save', [$record, 'fees']), ['fees' => [feeChoices(), ['label' => 'Simple', 'type' => 'free']]])->assertSessionHasNoErrors();
    foreach (['/api/government-ids/'.$record->id, '/api/government-ids'] as $url) {
        $data = $this->getJson($url)->assertOk()->json('data');
        $data = isset($data['fees']) ? $data : collect($data)->firstWhere('id', $record->id);
        expect($data['fees'][0]['selection_rule'])->toBe('choose_one')->and($data['fees'][0]['display_amount'])->toBe('Choose one')
            ->and($data['fees'][0]['options'])->toHaveCount(2)->and($data['fees'][0]['options'][0]['amount_min'])->toBe('950.00')
            ->and($data['fees'][0]['options'][1]['description'])->toBe('5–7 working days')
            ->and($data['fees'][1])->not->toHaveKey('options');
        expect($data['fee'])->toContain('OR')->not->toContain('2150');
    }
});

it('blocks rollback before deleting saved options in either table', function () {
    $record = Document::create(['name' => 'Rollback test']);
    $parent = $record->fees()->create(['label' => 'Keep', 'type' => 'options']);
    $parent->options()->create(['label' => 'Keep option', 'type' => 'free']);
    $migration = require database_path('migrations/2026_10_27_000000_create_fee_options_tables.php');
    expect(fn () => $migration->down())->toThrow(RuntimeException::class, 'Rollback stopped');
    expect(Schema::hasTable('government_id_fee_options'))->toBeTrue()->and($parent->options()->count())->toBe(1);
});

it('keeps full-form fee identity and supports free range and varies child amounts', function ($resource, $model) {
    $record = $model::create(['name' => 'Full form fee choices']);
    $payload = feeChoices();
    $payload['options'] = [
        ['label' => 'Waived', 'type' => 'free', 'amount_min' => 99],
        ['label' => 'Variable', 'type' => 'varies'],
        ['label' => 'Range', 'type' => 'range', 'amount_min' => 10, 'amount_max' => 20],
    ];
    $this->put(route('admin.'.$resource.'.update', $record), ['name' => $record->name, 'fees' => [$payload]])->assertSessionHasNoErrors();
    $parent = $record->fees()->sole();
    $payload['id'] = $parent->id;
    foreach ($payload['options'] as $index => &$option) $option['id'] = $parent->options[$index]->id;
    unset($option);
    $payload['options'][0]['notes'] = 'Updated waiver';
    $this->put(route('admin.'.$resource.'.update', $record), ['name' => $record->name, 'fees' => [$payload]])->assertSessionHasNoErrors();
    expect($record->fees()->sole()->id)->toBe($parent->id)
        ->and($parent->options()->first()->amount_min)->toBeNull()
        ->and($parent->options()->first()->notes)->toBe('Updated waiver')
        ->and($parent->options()->where('type', 'range')->sole()->amount_max)->toBe('20.00');
    $this->put(route('admin.'.$resource.'.update', $record), ['name' => $record->name, 'description' => 'Other section'])->assertSessionHasNoErrors();
    expect($parent->options()->count())->toBe(3);
})->with('fee resources');
