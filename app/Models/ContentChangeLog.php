<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ContentChangeLog extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['changed_fields' => 'array', 'created_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function record(Model $record, string $action, array $fields = []): void
    {
        if (! auth()->user()?->canAccessCms()) {
            return;
        }
        self::create([
            'user_id' => auth()->id(), 'action' => $action,
            'entity_type' => Str::snake(class_basename($record)),
            'entity_id' => $record->getKey(),
            'entity_name' => $record->getAttribute('name') ?? $record->getAttribute('username'),
            // Field names only: never log passwords, hashes, tokens or submitted values.
            'changed_fields' => array_values(array_diff($fields, ['password', 'remember_token', 'created_at', 'updated_at', 'last_updated'])),
            'created_at' => now(),
        ]);
    }
}
