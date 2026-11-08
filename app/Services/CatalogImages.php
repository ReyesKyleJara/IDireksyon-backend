<?php

namespace App\Services;

use App\Models\Document;
use App\Models\GovernmentId;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/** Stores the square image of each ID and document, and its uncropped source. */
class CatalogImages
{
    public const TYPES = ['government-ids' => GovernmentId::class, 'documents' => Document::class];

    /** Only this folder is reachable through the public image route. */
    public const CROPS = 'catalog-images';

    public const ORIGINALS = 'catalog-images/originals';

    public static function diskName(): string
    {
        return config('filesystems.catalog_images');
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(self::diskName());
    }

    /** Root-relative, so the address stays right behind any host or proxy. */
    public static function url(string $path): string
    {
        return route('catalog-images.show', ['file' => basename($path)], false);
    }

    /**
     * Saves the new files first and removes the replaced ones only after the
     * record points at the new files, so a failure never leaves a broken link.
     */
    public static function save(Model $record, UploadedFile $image, ?UploadedFile $original): void
    {
        $path = $image->store(self::CROPS, self::diskName());
        $originalPath = $original?->store(self::ORIGINALS, self::diskName());
        $created = array_values(array_filter([$path, $originalPath]));

        if (! $path || ($original && ! $originalPath)) {
            self::disk()->delete($created);
            throw new RuntimeException('The image file could not be stored.');
        }

        $replaced = array_filter([$record->image_path, $original ? $record->image_original_path : null]);

        try {
            self::write($record, [
                'image_path' => $path,
                'image_original_path' => $originalPath ?? $record->image_original_path,
            ]);
        } catch (Throwable $exception) {
            self::disk()->delete($created);
            throw $exception;
        }

        self::discard(...$replaced);
    }

    public static function remove(Model $record): void
    {
        $paths = array_filter([$record->image_path, $record->image_original_path]);
        self::write($record, ['image_path' => null, 'image_original_path' => null]);
        self::discard(...$paths);
    }

    /** Deletes files once the surrounding database transaction has succeeded. */
    public static function discard(?string ...$paths): void
    {
        $paths = array_values(array_filter($paths));
        if ($paths !== []) {
            DB::afterCommit(fn () => self::disk()->delete($paths));
        }
    }

    /** An image change is not a content edit, so research timestamps stay as they are. */
    private static function write(Model $record, array $columns): void
    {
        $record->timestamps = false;
        $record->forceFill($columns)->save();
        $record->timestamps = true;
    }
}
