<?php

namespace App\Services;

use App\Models\Document;
use App\Models\GovernmentId;
use App\Support\GuideRichText;
use App\Support\ResidentRequirements;

/** Conservative CMS facts for ranking; missing research never means free or complete. */
class RoadmapOptionFacts
{
    public static function forRecord(Document|GovernmentId $owner): array
    {
        $owner->loadMissing(['fees.options', 'requirementSets.applicationSteps.blocks']);
        $hidden = $owner->research_metadata['hidden_sections'] ?? [];
        $guideSets = [];
        if (! in_array('guide', $hidden, true)) {
            foreach ($owner->requirementSets as $set) {
                $hasInstructions = self::hasText($set->request_channel['application_guide_text'] ?? null)
                    || $set->applicationSteps->contains(fn ($step) => self::hasText($step->title)
                        && (self::hasText($step->short_description)
                            || $step->blocks->contains(fn ($block) => self::blockHasText($block))));
                if ($hasInstructions) {
                    $guideSets[] = $set->id;
                }
            }
        }

        return [
            'cost' => in_array('fees', $hidden, true) ? null : self::cost($owner),
            'guide_set_ids' => $guideSets,
            'has_legacy_guide' => ! in_array('guide', $hidden, true) && self::hasText($owner->application_process),
        ];
    }

    private static function cost(Document|GovernmentId $owner): ?array
    {
        $required = $owner->fees->reject(fn ($fee) => $fee->is_optional);
        if ($required->isEmpty()) {
            return null;
        }
        // Fees for unchosen request methods cannot be added as though every
        // method were compulsory, or discarded to advertise a partial total.
        $methodFeeIds = $owner->requirementSets
            ->filter(fn ($set) => ResidentRequirements::isRequestMethod($set))
            ->flatMap(fn ($set) => $set->request_channel['fee_ids'] ?? [])
            ->map(fn ($id) => (int) $id)->all();
        $currency = null;
        $minimum = $maximum = 0;
        foreach ($required as $fee) {
            if (in_array($fee->id, $methodFeeIds, true)) {
                return null;
            }
            $rowCurrency = strtoupper(trim((string) $fee->currency));
            if (! preg_match('/^[A-Z]{3}$/', $rowCurrency) || ($currency !== null && $currency !== $rowCurrency)) {
                return null;
            }
            $currency = $rowCurrency;
            if ($fee->type === 'free') {
                $low = $high = 0.0;
            } elseif ($fee->type === 'fixed' && self::validAmount($fee->amount_min)) {
                $low = $high = (float) $fee->amount_min;
            } elseif ($fee->type === 'range' && self::validAmount($fee->amount_min)
                && self::validAmount($fee->amount_max) && (float) $fee->amount_max >= (float) $fee->amount_min) {
                $low = (float) $fee->amount_min;
                $high = (float) $fee->amount_max;
            } else {
                // An unselected fee option, variable fee or missing amount is unknown.
                return null;
            }
            $minimum += (int) round($low * 100);
            $maximum += (int) round($high * 100);
        }

        return ['min' => (float) ($minimum / 100), 'max' => (float) ($maximum / 100), 'currency' => $currency];
    }

    private static function validAmount(mixed $value): bool
    {
        return is_numeric($value) && is_finite((float) $value) && (float) $value >= 0;
    }

    private static function hasText(mixed $value): bool
    {
        if (is_array($value)) {
            $value = GuideRichText::render($value);
        }
        if (! is_string($value)) {
            return false;
        }
        $text = trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $text !== '' && ! in_array(strtolower(preg_replace('/[^a-z0-9]/i', '', $text)), ['na', 'notapplicable'], true);
    }

    private static function blockHasText($block): bool
    {
        $content = $block->content ?? [];
        if (in_array($block->type, ['instructions', 'checklist'], true)) {
            return collect($content['items'] ?? [])->contains(fn ($item) => self::hasText($item['body'] ?? null));
        }
        if ($block->type === 'official_link') {
            return self::hasText($content['label'] ?? null) && GuideRichText::safeUrl($content['url'] ?? null);
        }

        return self::hasText($content['body'] ?? $content['intro'] ?? null);
    }
}
