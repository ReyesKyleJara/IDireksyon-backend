<?php

namespace App\Support;

class ApplicationAvailability
{
    public const LABELS = [
        'available' => 'Available',
        'limited_conditional' => 'Limited / Conditional',
        'temporarily_unavailable' => 'Temporarily Unavailable',
        'discontinued' => 'Not Issued Anymore',
    ];

    public static function label(?string $value): string
    {
        return self::LABELS[$value ?? 'available'] ?? 'Available';
    }
}
