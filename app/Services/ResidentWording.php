<?php

namespace App\Services;

use App\Models\Document;
use App\Models\GovernmentId;

/**
 * Resident-friendly wording for the app's record details.
 *
 * The CMS stores choices such as "No age requirement" or "Varies". Residents get short,
 * natural sentences instead, and choices that say nothing ("none", "not applicable") are left
 * out. Only the API response changes: CMS data is never rewritten, and this runs after
 * CatalogEncoding::detail(), so agency-confirmation fingerprints are not affected.
 */
class ResidentWording
{
    /** Lines the CMS writes for "no requirement" or "not applicable" choices (older saved text). */
    private const EMPTY_ELIGIBILITY_LINES = [
        'Age: Not applicable.', 'Age: No age requirement.',
        'Citizenship: Not applicable.', 'Citizenship: No specific citizenship requirement.',
        'Residency: Not applicable.', 'Residency: No specific residency requirement.',
    ];

    public static function apply(array $data, GovernmentId|Document $record): array
    {
        if (array_key_exists('eligibility', $data)) {
            $data['eligibility'] = self::eligibility($record);
        }
        $hasNote = filled($data['processing_time_note'] ?? null);
        if (array_key_exists('processing_time', $data)) {
            $data['processing_time'] = self::varying($data['processing_time'], $hasNote ? null : 'Varies. Please ask the office.');
        }
        if (array_key_exists('validity', $data)) {
            $data['validity'] = self::varying($data['validity'], 'Varies. Please ask the issuing office.');
        }
        if (is_string($data['fee'] ?? null)) {
            $data['fee'] = preg_replace('/:\s*Varies\b/', ': Amount varies', $data['fee']);
        }
        if (is_array($data['fees'] ?? null)) {
            $data['fees'] = self::fees($data['fees']);
        }
        if (is_array($data['request_paths'] ?? null)) {
            $data['request_paths'] = array_map(function ($path) {
                if (! is_array($path)) {
                    return $path;
                }
                if (array_key_exists('processing', $path)) {
                    $path['processing'] = self::varying($path['processing'], 'Varies. Please ask the office.');
                }
                if (is_array($path['fees'] ?? null)) {
                    $path['fees'] = self::fees($path['fees']);
                }

                return $path;
            }, $data['request_paths']);
        }

        return $data;
    }

    /**
     * One natural sentence from the CMS eligibility choices, plus any other conditions.
     * Null when nothing restricts who can apply, so the app hides the section.
     */
    public static function eligibility(GovernmentId|Document $record): ?string
    {
        $age = $record->eligibility_age_type;
        $citizenship = $record->eligibility_citizenship;
        $residency = $record->eligibility_residency;

        if ($age === null && $citizenship === null && $residency === null) {
            // Free text from before the structured fields: keep it, minus "no requirement" lines.
            $lines = collect(preg_split('/\R/', (string) $record->eligibility))
                ->map(fn ($line) => trim($line))
                ->reject(fn ($line) => $line === '' || in_array($line, self::EMPTY_ELIGIBILITY_LINES, true));

            return $lines->isEmpty() ? null : $lines->implode("\n");
        }

        $min = $record->eligibility_min_age;
        $max = $record->eligibility_max_age;
        $agePhrase = match ($age) {
            'minimum' => $min !== null ? "aged {$min} or older" : null,
            'range' => $min !== null && $max !== null ? "aged {$min} to {$max}" : null,
            default => null,
        };
        $place = match ($residency) {
            'philippines' => 'the Philippines',
            'santa_maria' => 'Santa Maria, Bulacan',
            default => null,
        };

        $sentences = [];
        if ($citizenship === 'filipino') {
            $qualifiers = array_filter([$agePhrase, $place ? "living in {$place}" : null]);
            $any = $residency === 'custom' ? '' : ' of any age';
            $sentences[] = 'Filipino citizens'.($qualifiers ? ' '.implode(' ', $qualifiers) : $any).'.';
        } elseif ($agePhrase !== null) {
            $sentences[] = ($place ? "Residents of {$place} " : 'Applicants ').$agePhrase.'.';
        } elseif ($place !== null) {
            $sentences[] = "Residents of {$place}.";
        }
        if ($residency === 'custom' && filled($record->eligibility_residency_custom)) {
            $sentences[] = self::sentence($record->eligibility_residency_custom);
        }
        $lines = $sentences === [] ? [] : [implode(' ', $sentences)];
        foreach (preg_split('/\R/', (string) $record->eligibility_other_conditions) as $line) {
            if (trim($line) !== '') {
                $lines[] = trim($line);
            }
        }

        return $lines === [] ? null : implode("\n", $lines);
    }

    /** "Not applicable" is hidden; a bare "Varies" gets a short hint. Anything else is kept. */
    private static function varying(mixed $value, ?string $variesText): mixed
    {
        if (! is_string($value)) {
            return $value;
        }
        $plain = strtolower(trim($value));
        if ($plain === 'not applicable') {
            return null;
        }

        return $plain === 'varies' && $variesText !== null ? $variesText : $value;
    }

    /** Fee amounts shown as "Varies" read "Amount varies". */
    private static function fees(array $fees): array
    {
        return array_map(function ($fee) {
            if (! is_array($fee)) {
                return $fee;
            }
            if (($fee['display_amount'] ?? null) === 'Varies') {
                $fee['display_amount'] = 'Amount varies';
            }
            if (is_array($fee['options'] ?? null)) {
                $fee['options'] = self::fees($fee['options']);
            }

            return $fee;
        }, $fees);
    }

    private static function sentence(string $text): string
    {
        $text = trim($text);

        return preg_match('/[.!?]$/', $text) ? $text : $text.'.';
    }
}
