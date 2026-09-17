<?php

use App\Models\Agency;
use App\Models\Barangay;
use App\Models\Category;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\Level;
use App\Models\Office;
use App\Models\User;
use Database\Seeders\CatalogFoundationSeeder;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'super_admin']));
});

it('seeds reference data and inventory safely without replacing existing research', function () {
    $passport = GovernmentId::create(['name' => 'Philippine Passport', 'agency' => 'Existing agency text', 'description' => 'Keep my research', 'source_url' => 'https://example.org/research']);
    $this->seed(CatalogFoundationSeeder::class);
    $counts = [GovernmentId::count(), Document::count(), Agency::count(), Category::count(), Office::count()];
    $passport->refresh();
    expect($passport->description)->toBe('Keep my research')->and($passport->source_url)->toBe('https://example.org/research')->and($passport->inventory_key)->toBe('passport');
    $passport->update(['research_notes' => 'Researcher edited notes', 'availability_status' => 'inactive']);
    $this->seed(CatalogFoundationSeeder::class);
    expect([GovernmentId::count(), Document::count(), Agency::count(), Category::count(), Office::count()])->toBe($counts);
    expect(Barangay::count())->toBe(24)->and(Level::count())->toBe(3)->and(Category::whereNotNull('parent_category_id')->count())->toBe(20);
    expect($passport->fresh()->research_notes)->toBe('Researcher edited notes')->and($passport->fresh()->availability_status)->toBe('inactive');
    expect(GovernmentId::where('availability_status', 'available')->count())->toBe(0);
    expect(Document::where('inventory_key', 'student-permit')->first()->record_type)->toBe('permit');
    expect(GovernmentId::where('inventory_key', 'umid')->first()->availability_status)->toBe('legacy');
    expect(Office::whereNotNull('latitude')->count())->toBe(0);
});

it('manages each reference resource through named forms', function (string $type) {
    $payload = ['name' => 'Test reference', 'status' => 'active'];
    if (in_array($type, ['levels', 'categories'])) {
        $payload['slug'] = 'test-reference';
    }
    if ($type === 'barangays') {
        $payload += ['municipality' => 'Santa Maria', 'province' => 'Bulacan'];
    }
    $this->get('/admin/reference/'.$type)->assertOk();
    $this->get('/admin/reference/'.$type.'/create')->assertOk();
    $response = $this->post('/admin/reference/'.$type, $payload)->assertSessionHasNoErrors();
    $edit = $response->headers->get('Location');
    $this->get($edit)->assertOk()->assertSee('Test reference');
    $url = str_replace('/edit', '', $edit);
    $this->put($url, $payload + ['description' => 'Test note'])->assertSessionHasNoErrors();
    $this->delete($url)->assertRedirect('/admin/reference/'.$type);
})->with(['levels', 'categories', 'agencies', 'barangays', 'offices']);

it('rejects category cycles and protects linked reference records', function () {
    $parent = Category::create(['name' => 'Parent', 'slug' => 'parent']);
    $child = Category::create(['name' => 'Child', 'slug' => 'child', 'parent_category_id' => $parent->id]);
    $this->put('/admin/reference/categories/'.$parent->id, ['name' => 'Parent', 'slug' => 'parent', 'status' => 'active', 'parent_category_id' => $child->id])->assertSessionHasErrors('parent_category_id');
    $this->delete('/admin/reference/categories/'.$parent->id)->assertSessionHas('error');
    expect($parent->fresh())->not->toBeNull();
    $agency = Agency::create(['name' => 'Office owner']);
    GovernmentId::create(['name' => 'Linked ID', 'agency' => 'Office owner', 'agency_id' => $agency->id]);
    $this->delete('/admin/reference/agencies/'.$agency->id)->assertSessionHas('error');
});

it('manages office hours with validation and preserves unchanged interval timestamps', function () {
    $office = Office::create(['name' => 'Test office']);
    $url = '/admin/reference/offices/'.$office->id;
    $payload = ['name' => 'Test office', 'status' => 'needs_research', 'hours_present' => 1, 'hours' => [['day_of_week' => 1, 'opens_at' => '08:00', 'closes_at' => '12:00', 'notes' => 'Research test only']]];
    $this->put($url, $payload)->assertSessionHasNoErrors();
    $hour = $office->hours()->firstOrFail();
    $this->put($url, $payload)->assertSessionHasNoErrors();
    expect($office->hours()->first()->id)->toBe($hour->id);
    $payload['hours'][] = ['day_of_week' => 1, 'opens_at' => '11:00', 'closes_at' => '13:00'];
    $this->put($url, $payload)->assertSessionHasErrors('hours');
    expect($office->hours()->count())->toBe(1);
    $this->put($url, ['name' => 'Test office', 'status' => 'active', 'latitude' => 91, 'longitude' => 1])->assertSessionHasErrors('latitude');
    $this->put($url, ['name' => 'Test office', 'status' => 'needs_research', 'hours_present' => 1, 'hours' => []])->assertSessionHasNoErrors();
    expect($office->hours()->count())->toBe(0);
});

it('links offices and classification without exposing raw keys and filters by relationships', function (string $resource) {
    $agency = Agency::create(['name' => 'Test organization', 'abbreviation' => 'TO']);
    $level = Level::first();
    $category = Category::create(['name' => 'Test category', 'slug' => 'test']);
    $office = Office::create(['name' => 'Test office', 'agency_id' => $agency->id]);
    $payload = ['name' => 'Test service', 'agency_id' => $agency->id, 'level_id' => $level->id, 'category_id' => $category->id, 'office_ids' => [$office->id], 'record_type' => 'credential', 'research_status' => 'needs_research'];
    $response = $this->post('/admin/'.$resource, $payload)->assertSessionHasNoErrors();
    $model = $resource === 'documents' ? Document::class : GovernmentId::class;
    $record = $model::firstOrFail();
    expect($record->offices->first()->id)->toBe($office->id);
    expect(($resource === 'documents' ? $office->documents : $office->governmentIds)->first()->id)->toBe($record->id);
    $this->get('/admin/'.$resource.'?q=TO&level_id='.$level->id.'&category_id='.$category->id)->assertOk()->assertSee('Test service');
    $this->get('/admin/'.$resource.'/'.$record->id.'/edit')->assertOk()->assertSee('Test organization')->assertSee('Test office');
    $this->put('/admin/'.$resource.'/'.$record->id, array_merge($payload, ['office_ids' => ['']]))->assertSessionHasNoErrors();
    expect($record->offices()->count())->toBe(0);
    $this->put('/admin/'.$resource.'/'.$record->id, array_merge($payload, ['level_id' => 99999]))->assertSessionHasErrors('level_id');
    $this->put('/admin/'.$resource.'/'.$record->id, array_merge($payload, ['availability_status' => 'available']))->assertSessionHasErrors(['source_url', 'source_checked_at']);
})->with(['government-ids', 'documents']);

it('redisplays invalid office source dates without a rendering error', function () {
    $this->from('/admin/reference/offices/create')->post('/admin/reference/offices', [
        'name' => 'Invalid office example', 'status' => 'needs_research', 'source_checked_at' => ['invalid'],
    ])->assertSessionHasErrors('source_checked_at');
    $this->get('/admin/reference/offices/create')->assertOk()->assertSee('Invalid office example');
});
