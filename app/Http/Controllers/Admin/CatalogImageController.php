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
    public function update(Request $request, string $type, int $id): JsonResponse
    {
        $current = $this->record($type, $id);
        // The browser sends the confirmed square as JPEG or PNG; files are stored exactly as sent.
        $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:2048', 'dimensions:ratio=1/1,min_width=300,max_width=2000'],
            'original' => [
                Rule::requiredIf(! $current->image_original_path), 'nullable', 'file', 'mimes:jpg,jpeg,png',
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
        return CatalogImages::item($type, $record);
    }
}
