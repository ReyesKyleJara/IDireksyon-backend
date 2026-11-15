<?php

namespace App\Models\Concerns;

use App\Support\ResidentVisibility;

/**
 * Requirement items and prerequisites that point to a hidden ID or document.
 *
 * Residents still need to know the requirement, so it is shown as plain text with the record's
 * name and no link. Only the copy loaded for the resident request changes; nothing is saved.
 */
trait PlainTextWhenLinkedRecordHidden
{
    public static function bootPlainTextWhenLinkedRecordHidden(): void
    {
        static::retrieved(function ($row) {
            if (! ResidentVisibility::active()) {
                return;
            }
            $name = ResidentVisibility::hiddenName($row->type, $row->type === 'document' ? $row->document_id : $row->government_id_id);
            if ($name !== null) {
                // sync: true keeps the model "clean", so it can never be saved back by accident.
                $row->setRawAttributes([...$row->getAttributes(), 'type' => 'custom', 'custom_name' => $name,
                    'government_id_id' => null, 'document_id' => null], true);
            }
        });
    }
}
