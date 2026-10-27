<?php

namespace App\Support;

class AcquisitionType
{
    public const LABELS = [
        'general_public' => 'General Public',
        'profession_based' => 'Profession-Based',
        'employment_based' => 'Employment-Based',
        'sector_specific' => 'Sector-Specific',
        'special_eligibility' => 'Special Eligibility',
    ];

    public static function label(?string $value): string
    {
        return self::LABELS[$value ?? 'general_public'] ?? 'General Public';
    }
}
