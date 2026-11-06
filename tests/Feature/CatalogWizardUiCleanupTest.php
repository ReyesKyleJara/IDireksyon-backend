<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\User;
use App\Support\RequestMethodLabel;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
    expect(config('database.default'))->toBe('sqlite');
    expect(config('database.connections.sqlite.database'))->toBe(':memory:');
});

dataset('cleanup catalog owners', [['government-ids', GovernmentId::class], ['documents', Document::class]]);

function cleanupPageXpath($response): DOMXPath
{
    $dom = new DOMDocument();
    @$dom->loadHTML($response->getContent());
    return new DOMXPath($dom);
}

it('renders one compact method context across Add and Edit without changing saved content', function ($resource, $model, $count) {
    $owner = $model::create(['name' => 'UI fixture']);
    $transaction = $resource === 'government-ids' ? 'new' : 'copy';
    $first = $owner->requirementSets()->create(['application_type' => $transaction, 'applicant_type' => 'all', 'request_channel' => $count ? ['label' => 'In Person', 'channel' => 'outlet', 'provider' => 'PSA CRS Outlet'] : null]);
    if ($count === 2) $owner->requirementSets()->create(['application_type' => $resource === 'documents' ? 'retrieval' : 'new', 'applicant_type' => 'minor', 'request_channel' => ['label' => 'Online', 'channel' => 'online']]);
    $first->applicationSteps()->create(['title' => 'Saved procedure', 'type' => 'general']);
    $first->groups()->create(['title' => 'Saved evidence', 'rule' => 'all']);
    $before = $owner->requirementSets()->with('groups', 'applicationSteps')->get()->toArray();
    $apiBefore = $this->getJson('/api/'.$resource.'/'.$owner->id)->assertOk()->json();
    foreach (['wizard', 'edit-wizard'] as $wizard) foreach (['requirements', 'fees', 'guide'] as $step) {
        $page = $this->get(route('admin.'.$resource.'.'.$wizard.'.show', [$owner, $step, 'path' => $first->id]))->assertOk()
            ->assertSee('Manage Request Methods')->assertDontSee('Currently editing')->assertDontSee('Set Up First Request Option')
            ->assertDontSee('record-level dependencies')->assertDontSee('Change shared/all')->assertDontSee('Requirements are not complete yet')
            ->assertDontSee('name="request_channel[label]"', false);
        $xpath = cleanupPageXpath($page);
        expect($xpath->query('//*[@aria-label="Request method context"]')->length)->toBe(1)
            ->and($xpath->query('//select[@id="channel-context"]')->length)->toBe($count === 2 ? 1 : 0)
            ->and($xpath->query('//*[contains(@class,"admin-panel") and @aria-label="Request method context"]')->length)->toBe(0);
        if (!$count) $page->assertSee('Create a request method in Step 2 first.');
        else $page->assertSee('Editing Request Method:')->assertSee('PSA CRS Outlet');
        if ($count === 2) {
            expect($xpath->query('//select[@id="channel-context"]/option[@selected]')->length)->toBe(1);
            $page->assertSee('Minor');
        }
        $visible = html_entity_decode(strip_tags($page->getContent()));
        if ($resource === 'government-ids') expect($visible)->not->toContain('First-Time Application');
    }
    foreach (['wizard', 'edit-wizard'] as $wizard) {
        $page = $this->get(route('admin.'.$resource.'.'.$wizard.'.show', [$owner, 'offices']))->assertOk()
            ->assertDontSee('Manage Request Methods')->assertDontSee('Create a request method in Step 2 first.')
            ->assertSee('No Request Method is required.');
        expect(cleanupPageXpath($page)->query('//*[@aria-label="Request method context"]')->length)->toBe(0);
    }
    expect($owner->requirementSets()->with('groups', 'applicationSteps')->get()->toArray())->toBe($before);
    expect($this->getJson('/api/'.$resource.'/'.$owner->id)->assertOk()->json())->toBe($apiBefore);
    if ($count === 1) {
        $legacy = $owner->requirementSets()->create(['application_type' => $transaction, 'applicant_type' => 'minor']);
        $page = $this->get(route('admin.'.$resource.'.edit-wizard.show', [$owner, 'fees', 'path' => $legacy->id]))->assertOk()->assertSee('Not configured — '.($resource === 'documents' ? 'Certified Copy — ' : '').'Minor');
        expect(cleanupPageXpath($page)->query('//select[@id="channel-context"]')->length)->toBe(0);
        expect($legacy->fresh()->applicant_type)->toBe('minor');
    }

})->with('cleanup catalog owners')->with([0, 1, 2]);

it('keeps legacy scenarios explicit and preserves applicant conditions and document transactions in labels', function ($resource, $model) {
    $owner = $model::create(['name' => 'Legacy fixture']);
    $legacy = $owner->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    $copy = $owner->requirementSets()->create(['application_type' => 'copy', 'applicant_type' => 'all', 'request_channel' => ['label' => 'In Person', 'channel' => 'outlet']]);
    $minor = $owner->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'minor', 'request_channel' => ['label' => 'New Application — Online', 'channel' => 'online']]);
    $legacyAttributes = $legacy->fresh()->getAttributes();
    $page = $this->get(route('admin.'.$resource.'.edit-wizard.show', [$owner, 'channels']))->assertOk()->assertSee('Configure existing option')->assertDontSee('Needs Setup')->assertDontSee('Method not configured')->assertSee('+ Add Request Method')->assertSee('Save Method');
    if ($resource === 'government-ids') expect(html_entity_decode(strip_tags($page->getContent())))->not->toContain('First-Time Application');
    expect(RequestMethodLabel::configured($legacy))->toBeFalse()->and(RequestMethodLabel::label($copy, true))->toContain('Certified Copy');
    $this->get(route('admin.'.$resource.'.edit-wizard.show', [$owner, 'guide', 'path' => $legacy->id]))->assertOk()->assertSee('Not configured');
    expect($legacy->fresh()->getAttributes())->toBe($legacyAttributes)->and($owner->requirementSets()->count())->toBe(3);
    // Cleaning a legacy display label is presentation-only.
    $minor->request_channel = ['label' => 'All Applicants • First-Time Application', 'channel' => 'outlet'];
    expect(RequestMethodLabel::label($minor, false))->toBe('In Person — Minor');
})->with('cleanup catalog owners');

it('hides transaction selectors for both catalogs while preserving saved meanings', function ($resource, $model) {
    $owner = $model::create(['name' => 'Form fixture']);
    $create = $this->get(route('admin.'.$resource.'.request-options.create', $owner))->assertOk();
    $xpath = cleanupPageXpath($create);
    expect($xpath->query('//select[@name="application_type"]')->length)->toBe(0);
    $create->assertDontSee('Choose a transaction')->assertDontSee('What does the applicant want to do?')->assertSee('Save Method');
    $saved = $owner->requirementSets()->create(['application_type' => $resource === 'documents' ? 'retrieval' : 'renewal', 'applicant_type' => 'custom', 'applicant_type_custom' => 'Applicants abroad', 'min_age' => 21, 'request_channel' => ['label' => 'Saved name', 'channel' => 'online']]);
    $before = $saved->fresh()->getAttributes();
    $edit = $this->get(route('admin.'.$resource.'.request-options.edit', [$owner, $saved->id]))->assertOk()->assertSee('Applicants abroad');
    expect(cleanupPageXpath($edit)->query('//select[@name="application_type"]')->length)->toBe(0);
    expect($saved->fresh()->getAttributes())->toBe($before);
})->with('cleanup catalog owners');
