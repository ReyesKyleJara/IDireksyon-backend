<?php

namespace App\Models\Concerns;

use App\Services\CatalogImages;

trait HasCatalogImage
{
    protected static function bootHasCatalogImage(): void
    {
        static::deleted(fn ($record) => CatalogImages::discard($record->image_path, $record->image_original_path));
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? CatalogImages::url($this->image_path) : null;
    }
}
