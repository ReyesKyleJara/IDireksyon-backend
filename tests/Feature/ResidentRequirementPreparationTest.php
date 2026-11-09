<?php

use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function preparationItem($owner, array $attributes = [])
{
    $set = $owner->requirementSets()->create(['application_type' => 'new', 'applicant_type' => 'all']);
    $group = $set->groups()->create(['title' => 'Supporting requirement', 'rule' => 'all']);
    $way = $group->ways()->create(['required_count' => 1]);
    $item = $group->items()->make([
        'requirement_way_id' => $way->id, 'type' => 'custom', 'custom_name' => 'Application form', ...$attributes,
    ]);
    $item->requirement_way_id = $way->id;
    $item->save();

    return $item;
}

dataset('preparation targets', [
    'ID' => [GovernmentId::class, 'government_id', 'resident_id_preparations'],
    'document' => [Document::class, 'document', 'resident_document_preparations'],
]);

it('autosaves preparation independently of inventory, other applications and residents', function ($model, $type, $table) {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $target = $model::create(['name' => 'Target']);
    $another = $model::create(['name' => 'Another application']);
    $credential = GovernmentId::create(['name' => 'Owned credential']);
    $doc = Document::create(['name' => 'Owned document']);
    $user->ownedGovernmentIds()->attach($credential);
    $user->ownedDocuments()->attach($doc);
    $item = preparationItem($target);
    $linked = preparationItem($target, ['type' => 'government_id', 'government_id_id' => $credential->id]);
    $foreign = preparationItem($another);
    $url = "/api/auth/requirement-preparation/$type/$target->id";
    $token = $user->createToken('test')->plainTextToken;
    $this->withToken($token)->getJson($url)->assertOk()->assertJsonPath('data.items', []);
    expect(DB::table($table)->count())->toBe(0);
    foreach ([true, false, true] as $prepared) {
        $this->putJson($url, ['item_id' => $item->id, 'prepared' => $prepared])
            ->assertOk()->assertJsonPath('data.items.0.prepared', $prepared);
    }
    $this->putJson($url, ['item_id' => $linked->id, 'prepared' => true])->assertOk();
    $this->putJson($url, ['item_id' => $linked->id, 'prepared' => false])->assertOk();
    $this->getJson($url)->assertOk()->assertJsonCount(2, 'data.items')->assertJsonPath('data.items.0.prepared', true);
    expect(DB::table($table)->where('user_id', $user->id)->count())->toBe(2)
        ->and($user->ownedGovernmentIds()->pluck('government_ids.id')->all())->toBe([$credential->id])
        ->and($user->ownedDocuments()->pluck('documents.id')->all())->toBe([$doc->id]);
    $this->putJson($url, ['item_id' => $foreign->id, 'prepared' => true])->assertUnprocessable();
    $this->putJson($url, ['item_id' => $item->id, 'prepared' => true, 'user_id' => $other->id])->assertUnprocessable();
    $this->getJson("/api/auth/requirement-preparation/$type/$another->id")->assertOk()->assertJsonPath('data.items', []);
    app('auth')->forgetGuards();
    $this->withToken($other->createToken('test')->plainTextToken)->getJson($url)
        ->assertOk()->assertJsonPath('data.items', []);
})->with('preparation targets');

it('requires an authenticated active resident and valid record/item inputs', function () {
    $target = GovernmentId::create(['name' => 'Target']);
    $url = "/api/auth/requirement-preparation/government_id/$target->id";
    $this->getJson($url)->assertUnauthorized();
    foreach ([['role' => 'researcher'], ['is_active' => false]] as $attributes) {
        $user = User::factory()->create($attributes);
        app('auth')->forgetGuards();
        $this->withToken($user->createToken('test')->plainTextToken)->getJson($url)->assertForbidden();
        $this->putJson($url, ['item_id' => 1, 'prepared' => true])->assertForbidden();
    }
    $user = User::factory()->create();
    app('auth')->forgetGuards();
    $this->withToken($user->createToken('test')->plainTextToken);
    $this->getJson('/api/auth/requirement-preparation/unknown/1')->assertNotFound();
    $this->getJson('/api/auth/requirement-preparation/government_id/999999')->assertNotFound();
    foreach ([[], ['item_id' => 999999, 'prepared' => true], ['item_id' => 1, 'prepared' => 'invalid']] as $data) {
        $this->putJson($url, $data)->assertUnprocessable();
    }
});

it('uses separate foreign-keyed item namespaces and cascades preparation cleanup', function () {
    $user = User::factory()->create();
    $id = GovernmentId::create(['name' => 'ID target']);
    $doc = Document::create(['name' => 'Document target']);
    $idItem = preparationItem($id);
    $docItem = preparationItem($doc);
    app('auth')->forgetGuards();
    $this->withToken($user->createToken('test')->plainTextToken);
    $this->putJson("/api/auth/requirement-preparation/government_id/$id->id", ['item_id' => $idItem->id, 'prepared' => true])->assertOk();
    $this->getJson("/api/auth/requirement-preparation/document/$doc->id")->assertOk()->assertJsonPath('data.items', []);
    $this->putJson("/api/auth/requirement-preparation/document/$doc->id", ['item_id' => $docItem->id, 'prepared' => true])->assertOk();
    $idItem->delete();
    expect(DB::table('resident_id_preparations')->count())->toBe(0)
        ->and(DB::table('resident_document_preparations')->count())->toBe(1);
    $user->delete();
    expect(DB::table('resident_document_preparations')->count())->toBe(0);
});

it('rolls back only preparation tables and can recreate them without changing inventory', function () {
    $user = User::factory()->create();
    $id = GovernmentId::create(['name' => 'Owned ID']);
    $user->ownedGovernmentIds()->attach($id);
    $migration = require database_path('migrations/2026_11_03_000000_create_resident_requirement_preparation_tables.php');
    $migration->down();
    expect(Schema::hasTable('resident_id_preparations'))->toBeFalse()
        ->and(Schema::hasTable('resident_document_preparations'))->toBeFalse()
        ->and($user->ownedGovernmentIds()->pluck('government_ids.id')->all())->toBe([$id->id]);
    $migration->up();
    expect(Schema::hasTable('resident_id_preparations'))->toBeTrue()
        ->and(DB::table('resident_id_preparations')->count())->toBe(0);
});

it('leaves owned documents unchecked until the resident confirms and preserves explicit choices', function () {
    $user = User::factory()->create();
    $target = GovernmentId::create(['name' => 'Application']);
    $document = Document::create(['name' => 'Birth certificate']);
    $user->ownedDocuments()->attach($document);
    $item = preparationItem($target, ['type' => 'document', 'document_id' => $document->id]);
    $url = "/api/auth/requirement-preparation/government_id/$target->id";
    $this->withToken($user->createToken('test')->plainTextToken)->getJson($url)
        ->assertOk()->assertJsonPath('data.items', []);
    $this->putJson($url, ['item_id' => $item->id, 'prepared' => true])->assertOk();
    $this->getJson($url)->assertOk()->assertJsonPath('data.items.0.prepared', true);
    $this->putJson($url, ['item_id' => $item->id, 'prepared' => false])->assertOk();
    $this->getJson($url)->assertOk()->assertJsonPath('data.items.0.prepared', false);
    expect($user->ownedDocuments()->pluck('documents.id')->all())->toBe([$document->id]);
});
