<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\User;
use App\Services\CatalogRevision;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
    expect(config('database.connections.sqlite.database'))->toBe(':memory:');
});

dataset('visibility owners', [['documents', Document::class], ['government-ids', GovernmentId::class]]);

it('places visibility controls in their sections instead of Basic Information', function ($resource, $model) {
    $owner = $model::create(['name' => 'Visibility fixture']);
    $this->get(route('admin.'.$resource.'.edit-wizard.show', [$owner, 'basic']))->assertOk()->assertDontSee('Hide resident sections')->assertDontSee('section_visibility[', false)->assertDontSee('research_metadata[hidden_sections]', false);
    foreach (['wizard', 'edit-wizard'] as $wizard) foreach (['requirements', 'fees', 'guide', 'offices'] as $step) {
        $page = $this->get(route('admin.'.$resource.'.'.$wizard.'.show', [$owner, $step]))->assertOk()->assertSee('name="section_visibility['.$step.']"', false);
        if ($step === 'guide') $page->assertSee('name="section_visibility[troubleshooting]"', false)->assertSee('form="catalog-step-form"', false);
    }
})->with('visibility owners');

it('hides and restores one section without deleting content or changing other metadata', function ($resource, $model) {
    $owner = $model::create(['name' => 'Visibility fixture', 'research_metadata' => ['status' => 'needs_research', 'hidden_sections' => ['offices'], 'special_cases' => [['id' => 'kept', 'title' => 'Keep case']]]]);
    $set = $owner->requirementSets()->create(['application_type' => 'copy', 'applicant_type' => 'all']);
    $set->applicationSteps()->create(['title' => 'Saved guide', 'type' => 'general']);
    $url = route('admin.'.$resource.'.edit-wizard.save', [$owner, 'guide']);
    foreach ([0, 1] as $visible) {
        $this->post($url, ['path' => $set->id, 'section_visibility' => ['guide' => $visible], '_catalog_revision' => CatalogRevision::token($owner)])->assertSessionHasNoErrors();
        expect(in_array('guide', $owner->fresh()->research_metadata['hidden_sections']))->toBe(!$visible)
            ->and($owner->fresh()->research_metadata['hidden_sections'])->toContain('offices')
            ->and($owner->fresh()->research_metadata['status'])->toBe('needs_research')
            ->and($owner->fresh()->research_metadata['special_cases'][0]['id'])->toBe('kept')
            ->and($set->applicationSteps()->count())->toBe(1);
        $this->getJson('/api/'.$resource.'/'.$owner->id)->assertOk()->assertJsonCount($visible ? 1 : 0, 'data.request_paths.0.guide');
    }
    $this->post($url, ['section_visibility' => ['troubleshooting' => 0]])->assertSessionHasNoErrors();
    expect($owner->fresh()->research_metadata['hidden_sections'])->toContain('troubleshooting', 'offices');
    $this->post(route('admin.'.$resource.'.edit-wizard.save', [$owner, 'basic']), ['name' => 'Updated fixture', 'research_metadata' => ['status' => 'draft', 'sources' => []]])->assertSessionHasNoErrors();
    expect($owner->fresh()->research_metadata['hidden_sections'])->toContain('troubleshooting', 'offices');
})->with('visibility owners');

it('rejects invalid and unrelated section updates and rolls back on content validation errors', function ($resource, $model) {
    $owner = $model::create(['name' => 'Safety fixture', 'research_metadata' => ['hidden_sections' => ['guide']]]);
    $url = route('admin.'.$resource.'.edit-wizard.save', [$owner, 'fees']);
    $this->postJson($url, ['section_visibility' => ['guide' => 1]])->assertUnprocessable();
    $this->postJson($url, ['section_visibility' => ['fees' => 'invalid']])->assertUnprocessable();
    $this->postJson($url, ['section_visibility' => ['fees' => 0], 'fees' => [['label' => 'Invalid fee', 'type' => 'fixed']]])->assertUnprocessable();
    expect($owner->fresh()->research_metadata['hidden_sections'])->toBe(['guide']);
})->with('visibility owners');
