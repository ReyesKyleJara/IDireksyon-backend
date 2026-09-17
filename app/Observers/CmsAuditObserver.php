<?php

namespace App\Observers;

use App\Models\ContentChangeLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CmsAuditObserver
{
    public function created(Model $record): void
    {
        ContentChangeLog::record($record, 'created', array_keys($record->getAttributes()));
    }

    public function updated(Model $record): void
    {
        $fields = array_diff(array_keys($record->getChanges()), ['password', 'remember_token', 'created_at', 'updated_at', 'last_updated']);
        if ($fields) {
            ContentChangeLog::record($record, 'updated', $fields);
        }
        if ($record instanceof User && $record->wasChanged('password')) {
            ContentChangeLog::record($record, 'password_changed');
        }
    }

    public function deleted(Model $record): void
    {
        ContentChangeLog::record($record, 'deleted');
    }
}
