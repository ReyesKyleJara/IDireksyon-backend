<?php

namespace App\Support;

/** Reads the catalog's free-text processing time for ranking only; it is never shown as a promise. */
class ProcessingTime
{
    private const DAYS_PER_UNIT = ['hour' => 0, 'day' => 1, 'week' => 7, 'month' => 30];

    /** The slowest estimate in days, or null when the text states no usable duration ("Varies"). */
    public static function estimatedDays(?string $text): ?int
    {
        $text = trim((string) $text);
        if ($text === '' || mb_strlen($text) > 40) {
            return null;
        }
        if (preg_match('/\b(same[- ]day|on[- ]the[- ]spot)\b/i', $text)) {
            return 0;
        }
        if (! preg_match('/(\d+)(?:\s*(?:-|–|—|to)\s*(\d+))?\s*(?:calendar\s+|working\s+|business\s+)?(hour|day|week|month)/iu', $text, $match)) {
            return null;
        }

        return (int) ($match[2] !== '' ? $match[2] : $match[1]) * self::DAYS_PER_UNIT[strtolower($match[3])];
    }
}
