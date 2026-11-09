<?php

namespace App\Services;

/** Read-only interpretation of the existing resident Requirements projection. */
class JourneyRequirements
{
    /** Conditions a resident can confirm about an accepted item. Custom wording stays manual. */
    public const CONFIRMABLE_QUALIFICATIONS = ['unexpired', 'current_address', 'photo', 'signature', 'photo_signature'];

    public static function confirmable(array $way): bool
    {
        return in_array($way['qualification_type'], self::CONFIRMABLE_QUALIFICATIONS, true);
    }

    /** Ways that can guide a choice. Ownership alone only satisfies an unqualified way. */
    public static function usable(array $way): bool
    {
        return $way['required_count'] >= 1 && ($way['qualification_type'] === 'none' || self::confirmable($way));
    }

    public static function itemKey(array $item): ?string
    {
        $type = $item['type'];
        $id = $item[$type.'_id'] ?? null;

        return in_array($type, ['government_id', 'document'], true) && $id
            ? $type.':'.$id : null;
    }

    public static function suggestible(array $record): bool
    {
        return $record['guidance_mode'] === 'guided'
            && $record['include_in_smart_sequencing']
            && $record['application_availability'] === 'available';
    }

    public function resolve(string $owner, array $sets, array $owned, ?int $age, array $selections, array $catalog): array
    {
        $rows = [];
        $pending = [];
        if (! $sets) {
            return compact('rows', 'pending');
        }

        // Unknown age/custom audience does not silently choose an application path.
        $sets = array_values(array_filter($sets, fn ($set) => $this->audience($set, $age) !== false));
        $chosen = $selections['paths'][$owner] ?? null;
        $set = collect($sets)->firstWhere('id', $chosen);
        if (! $set && count($sets) === 1 && $this->audience($sets[0], $age) === true) {
            $set = $sets[0];
        }
        if (! $set) {
            $options = array_values(array_map(fn ($set) => [
                'set_id' => $set['id'], 'name' => $set['display_label'].(filled($set['path_label'] ?? null) ? ' — '.$set['path_label'] : ''),
                'needs_review' => $this->audience($set, $age) === null,
            ], $sets));
            if ($options) {
                $pending[] = [
                    'key' => $owner.':path', 'kind' => 'path', 'owner_key' => $owner,
                    'name' => 'Choose application requirements for '.$catalog[$owner]['name'], 'message' => 'Select the application and applicant checklist that applies to you.',
                    'suggestions' => $options,
                ];
            } else {
                $rows[] = $this->manual('Requirements', 'No saved requirement path matches the available applicant data. Review with the issuing office.');
            }

            return compact('rows', 'pending');
        }

        foreach ($set['groups'] as $group) {
            $label = $group['title'] ?: (collect($group['ways'])->flatMap(fn ($way) => $way['items'])->pluck('name')->unique()->implode(' / ') ?: 'Requirement options');
            $applies = match ($group['condition_type']) {
                'always' => true,
                'below_18' => $age === null ? null : $age < 18,
                default => null,
            };
            if ($applies === false) {
                continue;
            }
            if ($applies === null) {
                $rows[] = $this->manual($label, 'Review applicability: '.($group['condition_label'] ?: 'Condition cannot be verified from the available data.'), 'conditional');

                continue;
            }

            $key = $owner.':set:'.$set['id'].':group:'.$group['id'];
            $ways = array_map(function ($way) use ($owned) {
                $items = [];
                foreach ($way['items'] as $item) {
                    $identity = self::itemKey($item) ?? 'manual:'.$item['id'];
                    $items[$identity] = $item;
                }
                $way['items_by_key'] = $items;
                $way['owned'] = array_keys(array_intersect_key($items, $owned));
                $way['verifiable'] = $way['qualification_type'] === 'none';
                $way['confirmable'] = self::confirmable($way);

                return $way;
            }, $group['ways']);
            $confirmOwned = function (array $way) use (&$rows, &$pending, $key, $owner, $set, $group, $label) {
                if ($way['confirmable'] && $way['owned']) {
                    $rows[] = $this->manual($label, null, 'confirmation');
                    $pending[] = $this->confirmation($key, $owner, $set['id'], $group['id'], $label, $way);
                }
            };

            // OR ways are alternatives: never pool Inventory matches across ways.
            $satisfied = collect($ways)->first(fn ($way) => $way['verifiable'] && $way['required_count'] > 0 && count($way['owned']) >= $way['required_count']);
            if ($satisfied) {
                $rows[] = ['type' => 'requirement', 'id' => $group['id'], 'name' => $label, 'status' => 'satisfied', 'condition' => null];

                continue;
            }

            // Ownership is self-reported: only the resident can confirm a qualified item.
            $confirmWay = collect($ways)->first(fn ($way) => $way['confirmable'] && $way['required_count'] > 0 && count($way['owned']) >= $way['required_count']);
            if ($confirmWay) {
                $confirmOwned($confirmWay);

                continue;
            }

            // One way requiring every item is mandatory, even when some items are manual.
            if (count($ways) === 1 && self::usable($ways[0]) && $ways[0]['required_count'] === count($ways[0]['items_by_key'])) {
                foreach ($ways[0]['items_by_key'] as $itemKey => $item) {
                    if (self::itemKey($item) === null) {
                        $rows[] = $this->manual($item['name'], $item['instructions'] ?? null, 'task');
                    } else {
                        $rows[] = $this->linked($itemKey, $item['name'], $owned, $catalog);
                    }
                }
                $confirmOwned($ways[0]);

                continue;
            }

            $saved = $selections['groups'][$key] ?? [];
            $savedWay = collect($ways)->first(fn ($way) => $way['id'] === ($saved['way_id'] ?? null) && self::usable($way));
            $planned = [];
            if ($savedWay) {
                foreach ($saved['items'] ?? [] as $itemKey) {
                    // CMS may lower Choose N after a choice was saved. Do not
                    // retain unnecessary acquisitions beyond the current count.
                    if (count(array_unique([...$savedWay['owned'], ...array_keys($planned)])) >= $savedWay['required_count']) {
                        break;
                    }
                    if (isset($savedWay['items_by_key'][$itemKey], $catalog[$itemKey])
                        && (isset($owned[$itemKey]) || self::suggestible($catalog[$itemKey]))) {
                        $planned[$itemKey] = true;
                    }
                }
            }
            // A complete saved combination is final. An incomplete one must
            // not hide other accepted ways (for example a linked document plus
            // a custom photo-ID item with no further guided option).
            if ($savedWay && count(array_unique([...$savedWay['owned'], ...array_keys($planned)])) >= $savedWay['required_count']) {
                foreach (array_keys($planned) as $itemKey) {
                    $rows[] = $this->linked($itemKey, $catalog[$itemKey]['name'], $owned, $catalog);
                }
                $confirmOwned($savedWay);
                continue;
            }
            $activeWays = $ways;
            $suggestions = [];
            $remaining = null;
            $hasLinked = false;
            foreach ($activeWays as $way) {
                if (! self::usable($way)) {
                    continue;
                }
                $wayPlanned = $savedWay && $way['id'] === $savedWay['id'] ? $planned : [];
                $count = count(array_unique([...$way['owned'], ...array_keys($wayPlanned)]));
                $missing = max(0, $way['required_count'] - $count);
                $remaining = $remaining === null ? $missing : min($remaining, $missing);
                foreach ($way['items_by_key'] as $itemKey => $item) {
                    if (self::itemKey($item) === null) {
                        continue;
                    }
                    $hasLinked = true;
                    if (! $missing || isset($owned[$itemKey]) || isset($wayPlanned[$itemKey])) {
                        continue;
                    }
                    if (! isset($catalog[$itemKey]) || ! self::suggestible($catalog[$itemKey]) || $itemKey === $owner) {
                        continue;
                    }
                    $record = $catalog[$itemKey];
                    $suggestions[] = ['type' => $record['type'], 'id' => $record['id'], 'name' => $record['name'], 'way_id' => $way['id'], 'remaining_count' => $missing];
                }
            }
            foreach (array_keys($planned) as $itemKey) {
                $rows[] = $this->linked($itemKey, $catalog[$itemKey]['name'], $owned, $catalog);
            }
            if ($remaining === 0) {
                continue;
            }
            if (! $hasLinked) {
                $usable = collect($ways)->contains(fn ($way) => self::usable($way));
                $rows[] = $this->manual($label, 'Prepare this requirement manually. Extra qualifications and custom items cannot be verified from Inventory.', $usable ? 'task' : 'review');

                continue;
            }
            $rows[] = ['type' => 'requirement', 'id' => $group['id'], 'name' => $label, 'status' => 'missing', 'condition' => null];
            $pending[] = [
                'key' => $key, 'kind' => 'requirement', 'owner_key' => $owner, 'set_id' => $set['id'], 'group_id' => $group['id'],
                'name' => $label, 'remaining_count' => $remaining,
                'message' => trim(($remaining === 1 ? 'You still need at least one accepted option.' : 'You still need at least '.$remaining.' accepted options.')
                    .' '.(collect($ways)->first(fn ($way) => $way['confirmable'])['qualification_label'] ?? '')),
                'suggestions' => $suggestions,
            ];
        }

        return compact('rows', 'pending');
    }

    private function audience(array $set, ?int $age): ?bool
    {
        if ($set['applicant_type'] === 'all') {
            return true;
        }
        if ($set['applicant_type'] === 'custom') {
            return null;
        }
        if ($age === null) {
            return null;
        }

        return ($set['min_age'] === null || $age >= $set['min_age']) && ($set['max_age'] === null || $age <= $set['max_age']);
    }

    private function linked(string $key, string $name, array $owned, array $catalog): array
    {
        [$type, $id] = explode(':', $key);
        $row = ['type' => $type, 'id' => (int) $id, 'name' => $name, 'status' => isset($owned[$key]) ? 'satisfied' : 'missing', 'condition' => null];
        if (($catalog[$key]['guidance_mode'] ?? null) === 'informational_only') {
            $row['resolution'] = 'reference_only';
            $row['message'] = 'Obtain this through the issuing institution; review its information.';
        } elseif ($row['status'] === 'missing' && ($catalog[$key]['include_in_smart_sequencing'] ?? true) === false) {
            // Excluded from sequencing, so it is something to prepare rather than an acquisition step.
            return $this->manual($name, null, 'task');
        }

        return $row;
    }

    private function manual(string $name, ?string $condition, string $kind = 'review'): array
    {
        return ['type' => 'custom', 'id' => null, 'name' => $name, 'status' => 'manual', 'condition' => $condition, 'manual_kind' => $kind];
    }

    private function confirmation(string $key, string $owner, int $setId, int $groupId, string $label, array $way): array
    {
        $names = array_map(fn ($identity) => $way['items_by_key'][$identity]['name'], $way['owned']);

        return [
            'key' => $key, 'kind' => 'requirement', 'owner_key' => $owner, 'set_id' => $setId, 'group_id' => $groupId,
            'name' => $label, 'remaining_count' => 1,
            'message' => 'You listed '.implode(', ', $names).' in your Inventory. Confirm it meets this requirement and is still valid and accepted for this application.',
            'suggestions' => [],
            'confirmation' => ['qualification' => $way['qualification_label'] ?? null, 'owned_names' => array_values($names)],
        ];
    }
}
