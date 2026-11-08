<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Services\RoadmapOptionFacts;

function optionFactsOwner(string $model = GovernmentId::class)
{
    return $model::create(['name' => 'Arbitrary acquisition']);
}

function optionFactsSet($owner, array $attributes = [])
{
    return $owner->requirementSets()->create([
        'application_type' => 'new', 'applicant_type' => 'all', ...$attributes,
    ]);
}

function optionFactsText(string $text = ''): array
{
    return ['type' => 'doc', 'content' => [['type' => 'paragraph',
        'content' => $text === '' ? [] : [['type' => 'text', 'text' => $text]],
    ]]];
}

it('does not interpret missing fees or a legacy summary as free', function ($model) {
    $owner = optionFactsOwner($model);
    $owner->update(['fee' => 'Free or varies; confirm with the office.', 'fee_type' => 'fixed', 'fee_min' => 100]);
    expect(RoadmapOptionFacts::forRecord($owner)['cost'])->toBeNull();
})->with([[GovernmentId::class], [Document::class]]);

it('reports an explicitly encoded free fee as zero', function ($model) {
    $owner = optionFactsOwner($model);
    $owner->fees()->create(['label' => 'Issuance', 'type' => 'free', 'currency' => 'PHP']);
    expect(RoadmapOptionFacts::forRecord($owner)['cost'])->toBe(['min' => 0.0, 'max' => 0.0, 'currency' => 'PHP']);
})->with([[GovernmentId::class], [Document::class]]);

it('sums known fixed and range mandatory fees without inventing one preferred amount', function ($model) {
    $owner = optionFactsOwner($model);
    $owner->fees()->create(['label' => 'Issuance', 'type' => 'fixed', 'amount_min' => 100.25, 'currency' => 'PHP']);
    $owner->fees()->create(['label' => 'Service', 'type' => 'range', 'amount_min' => 20.15, 'amount_max' => 40.35, 'currency' => 'PHP']);
    expect(RoadmapOptionFacts::forRecord($owner)['cost'])->toBe(['min' => 120.4, 'max' => 140.6, 'currency' => 'PHP']);
})->with([[GovernmentId::class], [Document::class]]);

it('keeps the whole cost unknown when a required fee is variable or incomplete', function (array $fee) {
    $owner = optionFactsOwner();
    $owner->fees()->create(['label' => 'Known charge', 'type' => 'fixed', 'amount_min' => 50, 'currency' => 'PHP']);
    $owner->fees()->create(['label' => 'Uncertain charge', 'currency' => 'PHP', ...$fee]);
    expect(RoadmapOptionFacts::forRecord($owner)['cost'])->toBeNull();
})->with([
    'variable' => [['type' => 'varies']],
    'missing fixed amount' => [['type' => 'fixed', 'amount_min' => null]],
    'missing range maximum' => [['type' => 'range', 'amount_min' => 10]],
    'reversed range' => [['type' => 'range', 'amount_min' => 20, 'amount_max' => 10]],
    'negative amount' => [['type' => 'fixed', 'amount_min' => -1]],
]);

it('does not choose a fee option for the resident even when its amounts are known', function ($model) {
    $owner = optionFactsOwner($model);
    $fee = $owner->fees()->create(['label' => 'Processing speed', 'type' => 'options', 'currency' => 'PHP']);
    $fee->options()->create(['label' => 'Regular', 'type' => 'fixed', 'amount_min' => 100, 'currency' => 'PHP']);
    $fee->options()->create(['label' => 'Express', 'type' => 'fixed', 'amount_min' => 200, 'currency' => 'PHP']);
    expect(RoadmapOptionFacts::forRecord($owner)['cost'])->toBeNull();
})->with([[GovernmentId::class], [Document::class]]);

it('does not add unlike currencies', function () {
    $owner = optionFactsOwner();
    foreach (['PHP', 'USD'] as $currency) {
        $owner->fees()->create(['label' => $currency, 'type' => 'fixed', 'amount_min' => 100, 'currency' => $currency]);
    }
    expect(RoadmapOptionFacts::forRecord($owner)['cost'])->toBeNull();
});

it('skips optional fees without converting an optional-only record into free', function () {
    $owner = optionFactsOwner();
    $owner->fees()->create(['label' => 'Optional delivery', 'type' => 'varies', 'is_optional' => true]);
    expect(RoadmapOptionFacts::forRecord($owner)['cost'])->toBeNull();
    $owner->fees()->create(['label' => 'Issuance', 'type' => 'fixed', 'amount_min' => 75, 'currency' => 'PHP']);
    $owner->unsetRelation('fees');
    expect(RoadmapOptionFacts::forRecord($owner)['cost'])->toBe(['min' => 75.0, 'max' => 75.0, 'currency' => 'PHP']);
});

it('does not advertise partial general fees as the total when methods have required charges', function ($model) {
    $owner = optionFactsOwner($model);
    $owner->fees()->create(['label' => 'General charge', 'type' => 'fixed', 'amount_min' => 100, 'currency' => 'PHP']);
    $methodFee = $owner->fees()->create(['label' => 'Method charge', 'type' => 'fixed', 'amount_min' => 50, 'currency' => 'PHP']);
    optionFactsSet($owner, ['request_channel' => ['label' => 'Postal copy', 'channel' => 'online', 'fee_ids' => [$methodFee->id]]]);
    expect(RoadmapOptionFacts::forRecord($owner)['cost'])->toBeNull();
})->with([[GovernmentId::class], [Document::class]]);

it('does not hide a known general total because of an optional method charge', function () {
    $owner = optionFactsOwner();
    $owner->fees()->create(['label' => 'General charge', 'type' => 'fixed', 'amount_min' => 100, 'currency' => 'PHP']);
    $optional = $owner->fees()->create(['label' => 'Optional delivery', 'type' => 'varies', 'is_optional' => true]);
    optionFactsSet($owner, ['request_channel' => ['label' => 'Delivery', 'channel' => 'online', 'fee_ids' => [$optional->id]]]);
    expect(RoadmapOptionFacts::forRecord($owner)['cost'])->toBe(['min' => 100.0, 'max' => 100.0, 'currency' => 'PHP']);
});

it('finds meaningful structured guide text but not titles or empty placeholders', function ($model) {
    $owner = optionFactsOwner($model);
    $empty = optionFactsSet($owner);
    $step = $empty->applicationSteps()->create(['title' => 'Apply']);
    $step->blocks()->create(['type' => 'instructions', 'content' => ['items' => [['body' => optionFactsText()]]]]);
    $complete = optionFactsSet($owner, ['application_type' => 'copy']);
    $guidedStep = $complete->applicationSteps()->create(['title' => 'Submit application']);
    $guidedStep->blocks()->create(['type' => 'instructions', 'content' => ['items' => [['body' => optionFactsText('Submit the signed application at the counter.')]]]]);
    $description = optionFactsSet($owner, ['application_type' => 'renewal']);
    $description->applicationSteps()->create(['title' => 'Renew at the counter', 'short_description' => 'Bring the completed renewal form.']);
    expect(RoadmapOptionFacts::forRecord($owner)['guide_set_ids'])->toBe([$complete->id, $description->id]);
})->with([[GovernmentId::class], [Document::class]]);

it('recognizes meaningful channel instructions and legacy instructions independently', function () {
    $owner = optionFactsOwner();
    $owner->update(['application_process' => 'Visit the issuing office with the completed form.']);
    $channel = optionFactsSet($owner, ['request_channel' => ['application_guide_text' => 'Submit the online form and retain the reference number.']]);
    optionFactsSet($owner, ['application_type' => 'copy', 'request_channel' => ['application_guide_text' => '   ']]);
    $facts = RoadmapOptionFacts::forRecord($owner);
    expect($facts['guide_set_ids'])->toBe([$channel->id])->and($facts['has_legacy_guide'])->toBeTrue();
});

it('does not use preparation-only text as a complete acquisition guide', function () {
    $owner = optionFactsOwner();
    optionFactsSet($owner, ['request_channel' => ['preparation' => 'Bring your originals.']]);
    expect(RoadmapOptionFacts::forRecord($owner)['guide_set_ids'])->toBe([]);
});

it('respects hidden guide and fee sections without changing their stored content', function () {
    $owner = optionFactsOwner();
    $owner->update(['application_process' => 'Visit the issuing office.', 'research_metadata' => ['hidden_sections' => ['fees', 'guide']]]);
    $owner->fees()->create(['label' => 'Issuance', 'type' => 'free']);
    $set = optionFactsSet($owner, ['request_channel' => ['application_guide_text' => 'Complete the application.']]);
    expect(RoadmapOptionFacts::forRecord($owner))->toBe(['cost' => null, 'guide_set_ids' => [], 'has_legacy_guide' => false]);
    expect($owner->fresh()->application_process)->toBe('Visit the issuing office.')
        ->and($set->fresh()->request_channel['application_guide_text'])->toBe('Complete the application.')
        ->and($owner->fees()->count())->toBe(1);
});
