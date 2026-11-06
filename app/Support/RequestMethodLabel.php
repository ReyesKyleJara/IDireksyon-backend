<?php

namespace App\Support;

use App\Models\GovernmentIdRequirementSet;

/** CMS wording only: never changes stored scenarios or resident API labels. */
class RequestMethodLabel
{
    public static function configured(GovernmentIdRequirementSet $path): bool
    {
        return filled($path->request_channel['label'] ?? null)
            && in_array($path->request_channel['channel'] ?? null, ['outlet', 'online', 'partner', 'hybrid'], true);
    }

    public static function label(GovernmentIdRequirementSet $path, bool $isDocument): string
    {
        $channel = $path->request_channel ?? [];
        $transaction = $path->application_type === 'custom' ? $path->application_type_custom : ([
            'copy' => 'Certified Copy', 'new' => 'New Application', 'renewal' => 'Renewal',
            'replacement' => 'Replacement', 'correction' => 'Correction', 'retrieval' => 'Retrieval',
            'authentication' => 'Authentication',
        ][$path->application_type] ?? $path->application_type);
        $applicant = $path->applicant_type === 'all' ? '' : explode(' • ', $path->display_label)[0];
        if (! self::configured($path)) {
            return implode(' — ', array_filter(['Not configured', ($isDocument || $path->application_type !== 'new') ? $transaction : '', $applicant]));
        }
        $method = ['outlet' => 'In Person', 'online' => 'Online', 'partner' => 'Service Partner', 'hybrid' => 'Other / Hybrid'][$channel['channel']];
        $label = trim($channel['label']);
        if (! $isDocument && $path->application_type === 'new') {
            $label = trim(preg_replace('/^(?:(?:All Applicants|New Applicant|New Application|First[- ]Time Application)\s*(?:[•—–|:-]\s*)?)+/iu', '', $label));
            $label = $label ?: $method;
        } elseif ($label === $path->display_label || $label === $method) {
            $label = $transaction.' — '.$method;
        }
        if ($isDocument && filled($transaction) && ! str_contains(mb_strtolower($label), mb_strtolower($transaction))) $label = $transaction.' — '.$label;
        foreach ([$channel['provider'] ?? '', $applicant] as $detail) {
            if (filled($detail) && ! str_contains(mb_strtolower($label), mb_strtolower($detail))) $label .= ' — '.$detail;
        }
        return $label;
    }
}
