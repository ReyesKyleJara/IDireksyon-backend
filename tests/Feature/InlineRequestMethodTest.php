<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\Office;
use App\Models\User;
use App\Services\CatalogRevision;
use App\Services\InlineRequestMethod;

beforeEach(function () { $this->actingAs(User::factory()->create(['role' => 'researcher'])); });

dataset('inline owners', [['documents', Document::class], ['government-ids', GovernmentId::class]]);

it('creates and independently edits providers and renders inline editors in both wizards', function ($resource, $model) {
    $owner = $model::create(['name' => 'Inline fixture']);
    foreach (['Counter', 'PSAHelpline.ph', 'PSA Serbilis'] as $index => $provider) {
        $draft = InlineRequestMethod::present($owner->fresh());
        $draft['channel'] = array_replace($draft['channel'], ['provider' => $provider, 'channel' => $index ? 'online' : 'outlet', 'processing' => 'Time '.$index, 'fulfillment' => $index === 1 ? ['email', 'download'] : []]);
        $draft['groups'] = [['rule' => 'all', 'condition_type' => 'always', 'title' => 'Requirements '.$index, 'items' => [['type' => 'custom', 'custom_name' => 'Evidence '.$index, 'submission_format' => 'original', 'instructions' => 'Bring evidence']]]];
        if ($index === 1) $draft['fees'][] = ['label' => 'Provider processing fee', 'type' => 'fixed', 'amount_min' => 155, 'currency' => 'PHP', 'options' => []];
        $draft['steps'] = [['title' => 'Apply '.$index, 'short_description' => 'Instructions '.$index, 'type' => 'general', 'blocks' => []]];
        $this->postJson(route('admin.'.$resource.'.channels', $owner), ['inline_payload' => json_encode($draft), '_catalog_revision' => CatalogRevision::token($owner)])->assertOk()->assertJsonPath('method.channel.provider', $provider);
    }
    $sets = $owner->requirementSets()->get();
    expect($sets)->toHaveCount(3);
    $unchanged = $sets[2]->toArray();
    $draft = InlineRequestMethod::present($owner->fresh(), $sets[1]);
    $draft['channel']['processing'] = 'Updated timeframe';
    $draft['groups'][0]['items'][0]['instructions'] = 'Updated evidence';
    $draft['steps'][0]['short_description'] = 'Updated instructions';
    $draft['metadata'] = ['application_type' => 'renewal', 'applicant_type' => 'minor'];
    $this->postJson(route('admin.'.$resource.'.channels', $owner), ['inline_payload' => json_encode($draft)])->assertOk();
    expect($sets[2]->fresh()->toArray())->toBe($unchanged)
        ->and($sets[1]->fresh()->application_type)->toBe($resource === 'documents' ? 'copy' : 'new')
        ->and($sets[1]->fresh()->groups->first()->items->first()->instructions)->toBe('Updated evidence');
    $legacy = $owner->requirementSets()->create(['application_type' => 'copy', 'applicant_type' => 'all']);
    $legacy->groups()->create(['title' => 'Existing saved requirements', 'rule' => 'all']);
    foreach (['edit-wizard', 'wizard'] as $wizard) {
        $page = $this->get(route('admin.'.$resource.'.'.$wizard.'.show', [$owner, 'channels']))->assertOk()->assertSee('PSAHelpline.ph')->assertSee('+ Add Request Method')->assertSee('inlineRequestMethods', false)->assertDontSee('What does the applicant want to do?')->assertDontSee('Edit Provider Details');
        $page->assertSee('What to Prepare')->assertSee('draft.channel.application_guide_text', false)->assertDontSee('Application Steps')->assertDontSee('+ Add Step')->assertSee('aria-label="Edit fees"', false)->assertDontSee('Required item')->assertDontSee('Requirement description')->assertDontSee('+ Add Requirement')->assertSee('request-methods-title')->assertSee('request-method-columns')->assertSee('request-method-chevron')->assertSee('request-method-choices')->assertDontSee('<select', false)->assertDontSee('<details', false)->assertSee('Configure existing option')->assertDontSee('Needs Setup')->assertDontSee('Method not configured');
        if ($wizard === 'edit-wizard' && getenv('INLINE_BROWSER_DIR')) file_put_contents(getenv('INLINE_BROWSER_DIR').'/'.$resource.'.html', $page->getContent());
        foreach (['requirements', 'fees', 'guide', 'offices'] as $step) $this->get(route('admin.'.$resource.'.'.$wizard.'.show', [$owner, $step, 'path' => $sets[1]->id]))->assertOk();
    }
    $this->getJson('/api/'.$resource.'/'.$owner->id)->assertOk()->assertJsonPath('data.request_paths.1.processing', 'Updated timeframe')->assertJsonPath('data.request_paths.2.processing', 'Time 2');
})->with('inline owners');

it('retains structured alternatives guide blocks legacy metadata and office links atomically', function ($resource, $model) {
    $owner = $model::create(['name' => 'Preservation', 'research_metadata' => ['status' => 'draft', 'special_cases' => [['id' => 'keep', 'title' => 'Exceptional case']]]]);
    $office = Office::create(['name' => 'Office', 'status' => 'active']); $owner->offices()->attach($office);
    $set = $owner->requirementSets()->create(['application_type' => 'custom', 'application_type_custom' => 'Legacy transaction', 'applicant_type' => 'custom', 'applicant_type_custom' => 'Legacy applicants', 'min_age' => 23, 'max_age' => 60, 'request_channel' => ['label' => 'Legacy label', 'provider' => 'Legacy provider', 'channel' => 'online', 'fulfillment' => ['pickup'], 'office_ids' => [$office->id], 'appointment' => 'conditional', 'legacy_key' => 'keep']]);
    $group = $set->groups()->create(['title' => 'Choose evidence', 'rule' => 'all', 'condition_type' => 'always']);
    $way = $group->ways()->create(['required_count' => 1, 'qualification_type' => 'photo', 'qualification_scope' => 'every']);
    $item = $group->items()->create(['requirement_way_id' => $way->id, 'type' => 'custom', 'custom_name' => 'Evidence', 'submission_format' => 'photocopy', 'copies' => 2]);
    // requirement_way_id is intentionally guarded; associate through the relationship.
    $item->requirement_way_id = $way->id; $item->save();
    $step = $set->applicationSteps()->create(['title' => 'Apply', 'type' => 'general']);
    $body = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Keep formatted content', 'marks' => [['type' => 'bold']]]]]]];
    $block = $step->blocks()->create(['type' => 'instructions', 'content' => ['items' => [['body' => $body]]]]);
    $draft = InlineRequestMethod::present($owner->fresh(), $set->fresh());
    $draft['channel']['processing'] = '3 days';
    $this->postJson(route('admin.'.$resource.'.channels', $owner), ['inline_payload' => json_encode($draft)])->assertOk();
    expect($item->fresh()->requirement_way_id)->toBe($way->id)->and($item->fresh()->copies)->toBe(2)
        ->and($block->fresh()->content)->toBe(['items' => [['body' => $body]]])
        ->and($set->fresh()->min_age)->toBe(23)->and($set->fresh()->request_channel['legacy_key'])->toBe('keep')
        ->and($set->fresh()->request_channel['office_ids'])->toBe([$office->id]);
    $draft['groups'] = [];
    $draft['channel']['processing'] = 'Must not save';
    $this->postJson(route('admin.'.$resource.'.channels', $owner), ['inline_payload' => json_encode($draft)])->assertUnprocessable();
    expect($set->fresh()->request_channel['processing'])->toBe('3 days')->and($owner->fresh()->research_metadata['special_cases'][0]['id'])->toBe('keep');
})->with('inline owners');

it('reuses shared fees and adds isolated provider fees without changing other providers', function ($resource, $model) {
    $owner = $model::create(['name' => 'Fees']);
    $fee = $owner->fees()->create(['label' => 'Shared charge', 'type' => 'fixed', 'amount_min' => 100, 'currency' => 'PHP']);
    $sets = collect(['First', 'Second'])->map(fn ($name) => $owner->requirementSets()->create(['application_type' => 'copy', 'applicant_type' => 'all', 'request_channel' => ['provider' => $name, 'label' => $name, 'channel' => 'online']]));
    $draft = InlineRequestMethod::present($owner->fresh(), $sets[0]);
    expect($draft['channel']['fee_ids'])->toBeNull()->and($draft['fees'][0]['shared'])->toBeTrue();
    $draft['channel']['processing'] = 'Saved';
    $this->postJson(route('admin.'.$resource.'.channels', $owner), ['inline_payload' => json_encode($draft)])->assertOk();
    expect($owner->fees()->count())->toBe(1);
    $draft['fees'][] = ['label' => 'Provider fee', 'type' => 'fixed', 'amount_min' => 50, 'currency' => 'PHP', 'options' => []];
    $this->postJson(route('admin.'.$resource.'.channels', $owner), ['inline_payload' => json_encode($draft)])->assertOk();
    expect($owner->fees()->count())->toBe(2)->and($sets[0]->fresh()->request_channel['fee_ids'])->toHaveCount(2)->and($sets[1]->fresh()->request_channel['fee_ids'])->toBe([$fee->id]);
    $draft = InlineRequestMethod::present($owner->fresh(), $sets[0]->fresh());
    $draft['fees'][0]['amount_min'] = 999;
    $this->postJson(route('admin.'.$resource.'.channels', $owner), ['inline_payload' => json_encode($draft)])->assertOk();
    expect($fee->fresh()->amount_min)->toBe('100.00')->and($sets[1]->fresh()->request_channel['fee_ids'])->toBe([$fee->id])
        ->and($owner->fees()->count())->toBe(3);
})->with('inline owners');

it('rejects foreign paths stale revisions and invalid input without creating records', function ($resource, $model) {
    $owner = $model::create(['name' => 'Safety']);
    $other = $model::create(['name' => 'Other']);
    $set = $other->requirementSets()->create(['application_type' => 'copy', 'applicant_type' => 'all']);
    $draft = InlineRequestMethod::present($owner);
    $draft['channel']['channel'] = 'online'; $draft['channel']['provider'] = 'Provider'; $draft['id'] = $set->id;
    $url = route('admin.'.$resource.'.channels', $owner);
    $this->postJson($url, ['inline_payload' => json_encode($draft)])->assertNotFound();
    $draft['id'] = null; $draft['channel']['portal_url'] = 'javascript:bad';
    $this->postJson($url, ['inline_payload' => json_encode($draft)])->assertUnprocessable();
    $revision = CatalogRevision::token($owner); $owner->update(['name' => 'Changed']);
    $this->postJson($url, ['inline_payload' => json_encode($draft), '_catalog_revision' => $revision])->assertUnprocessable();
    expect($owner->requirementSets()->count())->toBe(0);
})->with('inline owners');

it('saves provider details without revalidating or rewriting incomplete legacy content', function ($resource, $model) {
    $owner = $model::create(['name' => 'Legacy research']);
    $set = $owner->requirementSets()->create(['application_type' => 'renewal', 'applicant_type' => 'minor']);
    $group = $set->groups()->create(['title' => 'Unfinished research', 'rule' => 'all']);
    $step = $set->applicationSteps()->create(['title' => 'Legacy guide', 'type' => 'general']);
    $block = $step->blocks()->create(['type' => 'reminder', 'content' => ['body' => 'Older plain text']]);
    $beforeGroup = $group->fresh()->toArray(); $beforeBlock = $block->fresh()->toArray();
    $draft = InlineRequestMethod::present($owner->fresh(), $set);
    $draft['channel']['provider'] = 'Legacy provider'; $draft['channel']['channel'] = 'outlet';
    $this->postJson(route('admin.'.$resource.'.channels', $owner), ['inline_payload' => json_encode($draft)])->assertOk();
    expect($group->fresh()->toArray())->toBe($beforeGroup)->and($block->fresh()->toArray())->toBe($beforeBlock)
        ->and($set->fresh()->application_type)->toBe('renewal')->and($set->fresh()->applicant_type)->toBe('minor');
})->with('inline owners');

it('saves separate preparation guides per provider while retaining complete structured requirements', function ($resource, $model) {
    $owner = $model::create(['name' => 'Preparation fixture']);
    $sets = collect(['Counter', 'Online service'])->map(fn ($provider) => $owner->requirementSets()->create(['application_type' => 'copy', 'applicant_type' => 'all', 'request_channel' => ['provider' => $provider, 'label' => $provider, 'channel' => 'online', 'preparation' => 'Existing '.$provider.' preparation']]));
    $group = $sets[0]->groups()->create(['title' => 'Accepted evidence', 'rule' => 'choose_one', 'condition_type' => 'always']);
    foreach (['Evidence one', 'Evidence two'] as $name) $group->items()->create(['type' => 'custom', 'custom_name' => $name, 'submission_format' => 'original', 'copies' => 1, 'instructions' => 'Keep structured instructions']);
    $snapshot = $sets[0]->fresh()->load('groups.items', 'groups.ways')->toArray();
    $draft = InlineRequestMethod::present($owner->fresh(), $sets[0]->fresh());
    $url = route('admin.'.$resource.'.channels', $owner);
    foreach (['Bring your accepted ID and appointment slip.\nProvide accurate birth details.', ''] as $preparation) {
        $draft['channel']['preparation'] = $preparation;
        $response = $this->postJson($url, ['inline_payload' => json_encode($draft)])->assertOk();
        expect($response->json('method.channel.preparation'))->toBe($preparation);
        $current = $sets[0]->fresh()->load('groups.items', 'groups.ways')->toArray();
        expect($current['groups'])->toBe($snapshot['groups'])
            ->and($sets[1]->fresh()->request_channel['preparation'])->toBe('Existing Online service preparation');
    }
    $this->get(route('admin.'.$resource.'.edit-wizard.show', [$owner, 'requirements', 'path' => $sets[0]->id]))->assertOk()->assertSee('Evidence one')->assertSee('Evidence two');
    foreach ([['invalid array'], str_repeat('a', 10001)] as $invalid) {
        $draft['channel']['preparation'] = $invalid;
        $this->postJson($url, ['inline_payload' => json_encode($draft)])->assertUnprocessable();
        expect($sets[0]->fresh()->request_channel['preparation'])->toBe('');
    }
})->with('inline owners');

it('creates a provider with preparation text without making checklist items', function ($resource, $model) {
    $owner = $model::create(['name' => 'New provider fixture']);
    $draft = InlineRequestMethod::present($owner);
    $draft['channel'] = [...$draft['channel'], 'provider' => 'Service', 'channel' => 'outlet', 'preparation' => 'Bring the appointment slip.'];
    $this->postJson(route('admin.'.$resource.'.channels', $owner), ['inline_payload' => json_encode($draft)])->assertOk();
    $set = $owner->requirementSets()->sole();
    expect($set->request_channel['preparation'])->toBe('Bring the appointment slip.')->and($set->groups()->count())->toBe(0);
    $this->get(route('admin.'.$resource.'.edit-wizard.show', [$owner, 'channels']))->assertOk()->assertSee('Bring the appointment slip.')->assertSee('What to Prepare')->assertDontSee('Required item')->assertDontSee('Requirement description');
})->with('inline owners');

it('saves a provider application-guide paragraph without rewriting structured steps or other providers', function ($resource, $model) {
    $owner = $model::create(['name' => 'Paragraph guide fixture']);
    $sets = collect(['Counter', 'Online'])->map(fn ($provider) => $owner->requirementSets()->create(['application_type' => 'copy', 'applicant_type' => 'all', 'request_channel' => ['provider' => $provider, 'label' => $provider, 'channel' => 'online', 'preparation' => 'Keep preparation', 'application_guide_text' => 'Existing '.$provider.' guide']]));
    $step = $sets[0]->applicationSteps()->create(['title' => 'Keep structured step', 'type' => 'general', 'short_description' => 'Keep instructions']);
    $step->blocks()->create(['type' => 'reminder', 'content' => ['body' => 'Legacy reminder content']]);
    $before = $sets[0]->applicationSteps()->with('blocks')->get()->toArray();
    $draft = InlineRequestMethod::present($owner->fresh(), $sets[0]->fresh());
    $url = route('admin.'.$resource.'.channels', $owner);
    foreach (["Complete the form and submit it at the counter.\nKeep your reference number.", ''] as $paragraph) {
        $draft['channel']['application_guide_text'] = $paragraph;
        $this->postJson($url, ['inline_payload' => json_encode($draft)])->assertOk()->assertJsonPath('method.channel.application_guide_text', $paragraph);
        expect($sets[0]->fresh()->request_channel['application_guide_text'])->toBe($paragraph)
            ->and($sets[0]->fresh()->request_channel['preparation'])->toBe('Keep preparation')
            ->and($sets[1]->fresh()->request_channel['application_guide_text'])->toBe('Existing Online guide')
            ->and($sets[0]->applicationSteps()->with('blocks')->get()->toArray())->toBe($before);
    }
    foreach ([['invalid'], str_repeat('a', 10001)] as $invalid) {
        $draft['channel']['application_guide_text'] = $invalid;
        $this->postJson($url, ['inline_payload' => json_encode($draft)])->assertUnprocessable();
        expect($sets[0]->fresh()->request_channel['application_guide_text'])->toBe('');
    }
    $this->get(route('admin.'.$resource.'.edit-wizard.show', [$owner, 'guide', 'path' => $sets[0]->id]))->assertOk()->assertSee('Keep structured step');
})->with('inline owners');

it('creates and reopens a paragraph-only method without generating structured guide steps', function ($resource, $model) {
    $owner = $model::create(['name' => 'Paragraph-only method']);
    $draft = InlineRequestMethod::present($owner);
    $draft['channel'] = [...$draft['channel'], 'provider' => 'Counter', 'channel' => 'outlet', 'application_guide_text' => 'Submit the completed request at the counter.'];
    $this->postJson(route('admin.'.$resource.'.channels', $owner), ['inline_payload' => json_encode($draft)])->assertOk()->assertJsonPath('method.channel.application_guide_text', 'Submit the completed request at the counter.');
    $set = $owner->requirementSets()->sole();
    expect($set->applicationSteps()->count())->toBe(0);
    $this->get(route('admin.'.$resource.'.edit-wizard.show', [$owner, 'channels']))->assertOk()->assertSee('Submit the completed request at the counter.')->assertSee('draft.channel.application_guide_text', false)->assertDontSee('+ Add Step');
    unset($draft['channel']['application_guide_text']); $draft['id'] = $set->id;
    $this->postJson(route('admin.'.$resource.'.channels', $owner), ['inline_payload' => json_encode($draft)])->assertOk()->assertJsonPath('method.channel.application_guide_text', 'Submit the completed request at the counter.');
})->with('inline owners');

it('isolates provider fees and persists removing all fees without deleting reusable records', function ($resource, $model) {
    $owner = $model::create(['name' => 'Isolated fees']);
    $sets = collect(['Counter', 'Online'])->map(fn ($name) => $owner->requirementSets()->create(['application_type' => 'copy', 'applicant_type' => 'all', 'request_channel' => ['provider' => $name, 'label' => $name, 'channel' => 'online', 'fee_ids' => []]]));
    $url = route('admin.'.$resource.'.channels', $owner);
    foreach ($sets as $index => $set) {
        $draft = InlineRequestMethod::present($owner->fresh(), $set->fresh());
        expect($draft['fees'])->toBe([]);
        $draft['fees'] = [['label' => 'Provider charge '.$index, 'type' => 'fixed', 'amount_min' => 100 + $index, 'currency' => 'PHP', 'options' => []]];
        $this->postJson($url, ['inline_payload' => json_encode($draft)])->assertOk();
    }
    $draft = InlineRequestMethod::present($owner->fresh(), $sets[0]->fresh());
    expect($draft['fees'])->toHaveCount(1)->and($draft['fees'][0]['label'])->toBe('Provider charge 0');
    expect(InlineRequestMethod::present($owner->fresh())['fees'])->toBe([]);
    $secondBefore = $sets[1]->fresh()->toArray();
    $draft['fees'][0]['amount_min'] = 250;
    $this->postJson($url, ['inline_payload' => json_encode($draft)])->assertOk()->assertJsonPath('method.fees.0.amount_min', '250.00');
    expect($sets[1]->fresh()->toArray())->toBe($secondBefore);
    $draft = InlineRequestMethod::present($owner->fresh(), $sets[0]->fresh());
    $removedId = $draft['fees'][0]['id'];
    $draft['fees'] = [];
    // Even a stale fee_ids list cannot resurrect a removed row.
    $draft['channel']['fee_ids'] = [$removedId];
    foreach ([1, 2] as $_) {
        $this->postJson($url, ['inline_payload' => json_encode($draft)])->assertOk()->assertJsonPath('method.fees', [])->assertJsonPath('method.channel.fee_ids', []);
        $draft = InlineRequestMethod::present($owner->fresh(), $sets[0]->fresh());
    }
    expect($owner->fees()->count())->toBe(2)->and($sets[1]->fresh()->toArray())->toBe($secondBefore);
    $this->getJson('/api/'.$resource.'/'.$owner->id)->assertOk()->assertJsonCount(0, 'data.request_paths.0.fees')->assertJsonCount(1, 'data.request_paths.1.fees');
})->with('inline owners');

it('unlinks a shared fee only from the selected provider and preserves special case references', function ($resource, $model) {
    $owner = $model::create(['name' => 'Shared removal']);
    $fee = $owner->fees()->create(['label' => 'Shared fee', 'type' => 'free']);
    $owner->update(['research_metadata' => ['special_cases' => [['id' => 'case', 'title' => 'Keep case', 'fee_ids' => [$fee->id]]]]]);
    $sets = collect(['First', 'Second'])->map(fn ($name) => $owner->requirementSets()->create(['application_type' => 'copy', 'applicant_type' => 'all', 'request_channel' => ['provider' => $name, 'label' => $name, 'channel' => 'online', 'fee_ids' => [$fee->id]]]));
    $draft = InlineRequestMethod::present($owner->fresh(), $sets[0]); $draft['fees'] = [];
    $this->postJson(route('admin.'.$resource.'.channels', $owner), ['inline_payload' => json_encode($draft)])->assertOk()->assertJsonPath('method.fees', []);
    expect($owner->fees()->count())->toBe(1)->and($sets[1]->fresh()->request_channel['fee_ids'])->toBe([$fee->id])
        ->and($owner->fresh()->research_metadata['special_cases'][0]['fee_ids'])->toBe([$fee->id]);
})->with('inline owners');

it('requires only provider and category when all optional content is empty', function ($resource, $model) {
    $owner = $model::create(['name' => 'Optional fields']);
    $draft = InlineRequestMethod::present($owner);
    $draft['channel']['provider'] = 'Provider'; $draft['channel']['channel'] = 'outlet';
    $url = route('admin.'.$resource.'.channels', $owner);
    $this->postJson($url, ['inline_payload' => json_encode($draft)])->assertOk()->assertJsonPath('method.fees', []);
    expect($owner->fees()->count())->toBe(0)->and($owner->requirementSets()->sole()->request_channel['fulfillment'])->toBe([]);
    $draft = InlineRequestMethod::present($owner->fresh(), $owner->requirementSets()->sole());
    $draft['fees'][] = ['label' => 'Fee not yet confirmed', 'type' => 'varies', 'amount_min' => null, 'amount_max' => null, 'currency' => 'PHP', 'notes' => '', 'options' => []];
    $this->postJson($url, ['inline_payload' => json_encode($draft)])->assertOk();
    $draft = InlineRequestMethod::present($owner->fresh(), $owner->requirementSets()->sole());
    $draft['channel']['provider'] = '';
    $this->postJson($url, ['inline_payload' => json_encode($draft)])->assertUnprocessable();
})->with('inline owners');

it('copies shared fee options for a provider edit without changing the original option IDs or amounts', function ($resource, $model) {
    $owner = $model::create(['name' => 'Shared option edit']);
    $fee = $owner->fees()->create(['label' => 'Shared options', 'type' => 'options', 'currency' => 'PHP']);
    foreach ([['Regular', 100], ['Express', 200]] as [$label, $amount]) $fee->options()->create(['label' => $label, 'type' => 'fixed', 'amount_min' => $amount, 'currency' => 'PHP']);
    $sets = collect(['First', 'Second'])->map(fn ($name) => $owner->requirementSets()->create(['application_type' => 'copy', 'applicant_type' => 'all', 'request_channel' => ['provider' => $name, 'label' => $name, 'channel' => 'online', 'fee_ids' => [$fee->id]]]));
    $before = $fee->options()->get()->toArray();
    $draft = InlineRequestMethod::present($owner->fresh(), $sets[0]);
    $draft['fees'][0]['notes'] = 'Provider-only note';
    $this->postJson(route('admin.'.$resource.'.channels', $owner), ['inline_payload' => json_encode($draft)])->assertOk()->assertJsonPath('method.fees.0.notes', 'Provider-only note');
    $copy = $owner->fees()->where('id', '!=', $fee->id)->sole();
    expect($sets[0]->fresh()->request_channel['fee_ids'])->toBe([$copy->id])
        ->and($sets[1]->fresh()->request_channel['fee_ids'])->toBe([$fee->id])
        ->and($fee->options()->get()->toArray())->toBe($before)
        ->and($fee->fresh()->notes)->toBeNull()
        ->and($copy->options()->pluck('amount_min')->all())->toBe(['100.00', '200.00']);
    $draft = InlineRequestMethod::present($owner->fresh(), $sets[0]->fresh());
    $this->postJson(route('admin.'.$resource.'.channels', $owner), ['inline_payload' => json_encode($draft)])->assertOk();
    expect($owner->fees()->count())->toBe(2);
})->with('inline owners');
