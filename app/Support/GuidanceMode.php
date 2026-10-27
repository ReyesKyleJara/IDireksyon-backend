<?php

namespace App\Support;

class GuidanceMode
{
    public const LABELS = [
        'guided' => 'Guided Acquisition',
        'informational_only' => 'Informational Only',
    ];

    public static function label(?string $value): string
    {
        return self::LABELS[$value ?? 'guided'] ?? 'Guided Acquisition';
    }
}
