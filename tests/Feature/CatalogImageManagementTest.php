<?php

use App\Models\ContentChangeLog;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** A JPEG holding only its header: enough for type and size checks, and needs no image library. */
function jpegOfSize(int $width, int $height, string $name = 'image.jpg'): UploadedFile
{
    $bytes = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00"
        ."\xFF\xC0\x00\x11\x08".pack('n', $height).pack('n', $width)
        ."\x03\x01\x11\x00\x02\x11\x01\x03\x11\x01\xFF\xD9";

    return UploadedFile::fake()->createWithContent($name, $bytes);
}

function imageRoute(string $type, int $id): string
{
    return route('admin.images.update', ['type' => $type, 'id' => $id]);
}

beforeEach(function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->create(['role' => 'researcher']));
});

function uploadImage($test, string $type, int $id)
{
    return $test->post(imageRoute($type, $id), [
        'image' => jpegOfSize(800, 800),
        'original' => jpegOfSize(1600, 900, 'original.jpg'),
    ], ['Accept' => 'application/json']);
}

it('saves a square image and shows it in the resident API for IDs and documents', function () {
    $id = GovernmentId::create(['name' => 'Passport']);
    $document = Document::create(['name' => 'Birth Certificate']);

    foreach ([['government-ids', $id, '/api/government-ids'], ['documents', $document, '/api/documents']] as [$type, $record, $api]) {
        uploadImage($this, $type, $record->id)->assertOk()->assertJsonPath('item.can_recrop', true);
        $record->refresh();
        Storage::disk('public')->assertExists([$record->image_path, $record->image_original_path]);
        expect($this->getJson($api)->assertOk()->json('data.0.image_url'))->toBe($record->image_url)->toStartWith('/api/catalog-images/');
        expect($this->getJson($api.'/'.$record->id)->assertOk()->json('data.image_url'))->toBe($record->image_url);
        $this->get($record->image_url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    }
    expect(ContentChangeLog::where('changed_fields', json_encode(['image']))->count())->toBe(2);
});

it('returns no image address for a record without an image', function () {
    GovernmentId::create(['name' => 'Passport']);

    expect($this->getJson('/api/government-ids')->json('data.0'))->toHaveKey('image_url', null);
});

it('rejects images that are not square, too small, or not pictures', function (callable $image, callable $original = null) {
    $record = GovernmentId::create(['name' => 'Passport']);

    $this->post(imageRoute('government-ids', $record->id), [
        'image' => $image(),
        'original' => $original ? $original() : jpegOfSize(800, 800, 'original.jpg'),
    ], ['Accept' => 'application/json'])->assertUnprocessable();

    expect($record->fresh()->image_path)->toBeNull();
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
})->with([
    'wide' => [fn () => jpegOfSize(800, 600)],
    'too small' => [fn () => jpegOfSize(100, 100)],
    'text file' => [fn () => UploadedFile::fake()->createWithContent('image.jpg', 'not a picture')],
    'svg' => [fn () => UploadedFile::fake()->createWithContent('image.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>')],
    'oversized' => [fn () => UploadedFile::fake()->create('image.jpg', 3000, 'image/jpeg')],
    'small original' => [fn () => jpegOfSize(800, 800), fn () => jpegOfSize(100, 100, 'original.jpg')],
]);

it('requires the original on the first upload', function () {
    $record = Document::create(['name' => 'Clearance']);

    $this->post(imageRoute('documents', $record->id), ['image' => jpegOfSize(800, 800)], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors('original');
});

it('replaces an image and removes the old files', function () {
    $record = GovernmentId::create(['name' => 'Passport']);
    uploadImage($this, 'government-ids', $record->id);
    $old = $record->refresh()->only('image_path', 'image_original_path');

    uploadImage($this, 'government-ids', $record->id)->assertOk();
    $record->refresh();

    expect($record->image_path)->not->toBe($old['image_path']);
    Storage::disk('public')->assertMissing(array_values($old));
    Storage::disk('public')->assertExists([$record->image_path, $record->image_original_path]);
    $this->get('/api/catalog-images/'.basename($old['image_path']))->assertNotFound();
});

it('re-crops using the kept original, changing only the square image', function () {
    $record = GovernmentId::create(['name' => 'Passport']);
    uploadImage($this, 'government-ids', $record->id);
    $old = $record->refresh()->only('image_path', 'image_original_path');

    $this->get(route('admin.images.original', ['type' => 'government-ids', 'id' => $record->id]))->assertOk();
    $this->post(imageRoute('government-ids', $record->id), ['image' => jpegOfSize(800, 800)], ['Accept' => 'application/json'])->assertOk();
    $record->refresh();

    expect($record->image_original_path)->toBe($old['image_original_path'])->and($record->image_path)->not->toBe($old['image_path']);
    Storage::disk('public')->assertMissing($old['image_path']);
    Storage::disk('public')->assertExists($record->image_original_path);
});

it('deletes an image and leaves no address or files behind', function () {
    $record = Document::create(['name' => 'Clearance']);
    uploadImage($this, 'documents', $record->id);

    $this->delete(imageRoute('documents', $record->id), [], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('item.image_url', null);

    expect($record->fresh()->image_path)->toBeNull()->and($record->fresh()->image_original_path)->toBeNull();
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

it('removes image files when the record itself is deleted', function () {
    $record = Document::create(['name' => 'Clearance']);
    uploadImage($this, 'documents', $record->id);

    $this->delete(route('admin.documents.destroy', $record))->assertRedirect();

    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

it('keeps the old image when storing the new one fails', function () {
    $record = GovernmentId::create(['name' => 'Passport']);
    uploadImage($this, 'government-ids', $record->id);
    $path = $record->refresh()->image_path;

    $this->withoutExceptionHandling();
    config(['filesystems.disks.broken' => ['driver' => 'local', 'root' => __FILE__]]);
    config(['filesystems.catalog_images' => 'broken']);

    expect(fn () => uploadImage($this, 'government-ids', $record->id))->toThrow(Exception::class);
    expect($record->fresh()->image_path)->toBe($path);
    Storage::disk('public')->assertExists($path);
});

it('does not change the record revision or research timestamp', function () {
    $record = GovernmentId::create(['name' => 'Passport']);
    $token = \App\Services\CatalogRevision::token($record);
    $updated = $record->fresh()->updated_at;

    $this->travel(5)->minutes();
    uploadImage($this, 'government-ids', $record->id)->assertOk();

    expect(\App\Services\CatalogRevision::token($record))->toBe($token);
    expect($record->fresh()->updated_at->equalTo($updated))->toBeTrue();
});

it('does not expose originals or other files through the public route', function () {
    $record = GovernmentId::create(['name' => 'Passport']);
    uploadImage($this, 'government-ids', $record->id);
    $record->refresh();

    $this->get('/api/catalog-images/'.basename($record->image_original_path))->assertNotFound();
    $this->get('/api/catalog-images/..%2F..%2F.env')->assertNotFound();
    expect($this->getJson('/api/government-ids/'.$record->id)->json('data'))->not->toHaveKey('image_original_path');
});

it('keeps image management behind CMS access', function () {
    $record = GovernmentId::create(['name' => 'Passport']);

    $this->actingAs(User::factory()->create(['role' => 'resident']));
    $this->get(route('admin.images.index'))->assertForbidden();
    $this->post(imageRoute('government-ids', $record->id), [])->assertForbidden();
    $this->delete(imageRoute('government-ids', $record->id))->assertForbidden();

    auth()->logout();
    $this->get(route('admin.images.index'))->assertRedirect(route('login'));
});

it('lists every record on the Images page and opens a requested one', function () {
    $id = GovernmentId::create(['name' => 'Passport']);
    Document::create(['name' => 'Birth Certificate']);

    $this->get(route('admin.images.index', ['open' => 'government-ids:'.$id->id]))->assertOk()
        ->assertSee('Passport')->assertSee('Birth Certificate')->assertSee('government-ids:'.$id->id, false);
});

it('shows the saved image on the ID directory and details pages', function () {
    $record = GovernmentId::create(['name' => 'Passport']);
    uploadImage($this, 'government-ids', $record->id);
    $record->refresh();

    $this->get(route('admin.government-ids.index'))->assertOk()->assertSee($record->image_url, false)->assertDontSee('No image yet');
    $this->get(route('admin.government-ids.show', $record))->assertOk()->assertSee($record->image_url, false)->assertSee('Manage image');
});
