<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentChangeLog;
use App\Services\CatalogImages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CatalogImageController extends Controller
{
    private const TYPE_LABELS = ['government-ids' => 'Government ID', 'documents' => 'Document'];

    public function index(Request $request)
    {
        $items = collect(CatalogImages::TYPES)
            ->flatMap(fn ($model, $type) => $model::query()->orderBy('name')->get()
                ->map(fn (Model $record) => $this->item($type, $record)))
            ->sortBy(fn ($item) => mb_strtolower($item['name']))
            ->values();

        return view('admin.images.index', ['items' => $items, 'open' => $request->query('open')]);
    }

    public function update(Request $request, string $type, int $id): JsonResponse
    {
        $current = $this->record($type, $id);
        // The browser sends JPEG files only; any picture format is converted there first.
        $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg', 'max:2048', 'dimensions:ratio=1/1,min_width=300,max_width=2000'],
            'original' => [
                Rule::requiredIf(! $current->image_original_path), 'nullable', 'file', 'mimes:jpg,jpeg',
                'max:2048', 'dimensions:min_width=300,min_height=300',
            ],
        ], [
            'image.dimensions' => 'The saved image must be square and between 300 and 2000 pixels wide.',
            'original.dimensions' => 'The picture must be at least 300 by 300 pixels.',
        ]);

        $record = DB::transaction(function () use ($request, $current) {
            $record = $current->newQuery()->lockForUpdate()->findOrFail($current->getKey());
            CatalogImages::save($record, $request->file('image'), $request->file('original'));
            ContentChangeLog::record($record, 'updated', ['image']);

            return $record;
        });

        return response()->json(['message' => 'Image saved.', 'item' => $this->item($type, $record)]);
    }

    public function destroy(string $type, int $id): JsonResponse
    {
        $current = $this->record($type, $id);

        $record = DB::transaction(function () use ($current) {
            $record = $current->newQuery()->lockForUpdate()->findOrFail($current->getKey());
            CatalogImages::remove($record);
            ContentChangeLog::record($record, 'updated', ['image']);

            return $record;
        });

        return response()->json(['message' => 'Image deleted.', 'item' => $this->item($type, $record)]);
    }

    public function original(string $type, int $id)
    {
        $path = $this->record($type, $id)->image_original_path;
        abort_unless($path && CatalogImages::disk()->exists($path), 404);

        return CatalogImages::disk()->response($path, basename($path), ['Cache-Control' => 'private, no-store']);
    }

    private function record(string $type, int $id): Model
    {
        return CatalogImages::TYPES[$type]::query()->findOrFail($id);
    }

    private function item(string $type, Model $record): array
    {
        $key = ['type' => $type, 'id' => $record->getKey()];

        return [
            'key' => $type.':'.$record->getKey(),
            'type' => $type,
            'type_label' => self::TYPE_LABELS[$type],
            'name' => $record->name,
            'image_url' => $record->image_url,
            'can_recrop' => (bool) $record->image_original_path,
            'broken' => false,
            'save_url' => route('admin.images.update', $key, false),
            'original_url' => route('admin.images.original', $key, false),
            'details_url' => route($type === 'documents' ? 'admin.documents.show' : 'admin.government-ids.show', $record->getKey(), false),
        ];
    }
}
