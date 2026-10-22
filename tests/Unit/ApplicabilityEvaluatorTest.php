<?php

use App\Models\GovernmentIdApplicationStep;
use App\Models\GovernmentIdRequirementGroup;
use App\Models\GovernmentIdRequirementSet;
use App\Services\ApplicabilityEvaluator;
use App\Support\ApplicantContext;

it('matches adult and minor profiles at the age boundary', function () {
    $evaluator = new ApplicabilityEvaluator;
    $adult = new GovernmentIdRequirementSet(['application_type' => 'new', 'applicant_type' => 'adult']);
    $minor = new GovernmentIdRequirementSet(['application_type' => 'new', 'applicant_type' => 'minor']);
    expect($evaluator->matchingProfiles([$adult, $minor], new ApplicantContext(18, 'new')))->toBe([$adult])
        ->and($evaluator->matchingProfiles([$adult, $minor], new ApplicantContext(17, 'new')))->toBe([$minor]);
});

it('uses the same context and profile rules for requirements and guide steps', function () {
    $evaluator = new ApplicabilityEvaluator;
    $profile = new GovernmentIdRequirementSet(['application_type' => 'renewal', 'applicant_type' => 'all']);
    $step = new GovernmentIdApplicationStep(['title' => 'Bring previous passport']);
    $step->setRelation('requirementSet', $profile);
    foreach (['new' => false, 'renewal' => true] as $type => $expected) {
        $context = new ApplicantContext(25, $type);
        expect($evaluator->profileApplies($profile, $context))->toBe($expected)
            ->and($evaluator->stepApplies($step, $context))->toBe($expected);
    }
});

it('keeps custom profiles unresolved regardless of their labels and age bounds', function (array $fields) {
    $profile = new GovernmentIdRequirementSet($fields);
    $step = new GovernmentIdApplicationStep;
    $step->setRelation('requirementSet', $profile);
    $evaluator = new ApplicabilityEvaluator;
    $before = $profile->getAttributes();
    $context = new ApplicantContext(20, 'new');
    expect($evaluator->needsRuleConfiguration($profile))->toBeTrue()
        ->and($evaluator->profileApplies($profile, $context))->toBeNull()
        ->and($evaluator->stepApplies($step, $context))->toBeNull()
        ->and($evaluator->matchingProfiles([$profile], $context))->toBe([])
        ->and($profile->getAttributes())->toBe($before);
})->with([
    [['application_type' => 'new', 'applicant_type' => 'custom', 'applicant_type_custom' => 'Students', 'min_age' => 18, 'max_age' => 25]],
    [['application_type' => 'custom', 'application_type_custom' => 'New application', 'applicant_type' => 'adult']],
]);

it('distinguishes unknown facts from a definite mismatch', function () {
    $evaluator = new ApplicabilityEvaluator;
    $profile = new GovernmentIdRequirementSet(['application_type' => 'new', 'applicant_type' => 'adult']);
    expect($evaluator->profileApplies($profile, new ApplicantContext(null, 'new')))->toBeNull()
        ->and($evaluator->profileApplies($profile, new ApplicantContext(null, 'renewal')))->toBeFalse()
        ->and($evaluator->stepApplies(new GovernmentIdApplicationStep, new ApplicantContext))->toBeNull();
});

it('never guesses unresolved group conditions or changes requirement semantics', function () {
    $evaluator = new ApplicabilityEvaluator;
    $group = new GovernmentIdRequirementGroup(['rule' => 'choose_one', 'condition_type' => 'custom', 'condition_custom' => 'Other situation']);
    expect($evaluator->groupApplies($group, new ApplicantContext(20, 'new')))->toBeNull()
        ->and($group->rule)->toBe('choose_one');
    $group->condition_type = 'below_18';
    expect($evaluator->groupApplies($group, new ApplicantContext(17)))->toBeTrue()
        ->and($evaluator->groupApplies($group, new ApplicantContext(18)))->toBeFalse()
        ->and($evaluator->groupApplies($group, new ApplicantContext))->toBeNull();
    $group->condition_type = 'always';
    expect($evaluator->groupApplies($group, new ApplicantContext))->toBeTrue();
});

it('preserves overlapping matches rather than selecting an arbitrary best profile', function () {
    $profiles = [
        new GovernmentIdRequirementSet(['application_type' => 'new', 'applicant_type' => 'all']),
        new GovernmentIdRequirementSet(['application_type' => 'new', 'applicant_type' => 'adult']),
    ];
    expect((new ApplicabilityEvaluator)->matchingProfiles($profiles, new ApplicantContext(18, 'new')))->toBe($profiles);
});
