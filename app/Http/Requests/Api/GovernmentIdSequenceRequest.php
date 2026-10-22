<?php

namespace App\Http\Requests\Api;

use App\Support\ProfileSelections;
use Illuminate\Validation\Validator;
use InvalidArgumentException;
use JsonException;
use stdClass;

class GovernmentIdSequenceRequest extends GovernmentIdApplicabilityRequest
{
    public function rules(): array
    {
        return [...parent::rules(),
            'profile_selections' => ['sometimes', 'array', 'max:500'],
            'profile_selections.*' => ['bail', function ($attribute, $value, $fail) {
                if (! is_int($value)) {
                    $fail("The {$attribute} field must be a JSON integer.");
                }
            }, 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $allowed = array_filter(array_keys($this->rules()), fn ($field) => ! str_contains($field, '.'));
            foreach (array_diff(array_keys($this->all()), $allowed) as $field) {
                $validator->errors()->add($field, 'This field is not supported by the sequencing request.');
            }
            $json = $this->unambiguousJson($validator);
            if (array_key_exists('profile_selections', $this->all())
                && (! $json instanceof stdClass || ! ($json->profile_selections ?? null) instanceof stdClass)) {
                $validator->errors()->add('profile_selections', 'Profile selections must be a JSON object keyed by Government ID.');
            }
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            try {
                $selections = new ProfileSelections($this->input('profile_selections', []));
            } catch (InvalidArgumentException $exception) {
                $validator->errors()->add('profile_selections', $exception->getMessage());

                return;
            }
            $root = $selections->forGovernmentId($this->route('governmentId')->id);
            if ($root !== null && array_key_exists('selected_profile_id', $this->all())
                && $this->input('selected_profile_id') !== $root) {
                $validator->errors()->add('selected_profile_id', 'The root profile selection must agree with profile_selections. Omit selected_profile_id when using the preferred map.');
            }
        }];
    }

    public function profileSelections(): ProfileSelections
    {
        return new ProfileSelections($this->validated('profile_selections', []));
    }

    /** PHP's JSON decoder overwrites duplicate keys, so inspect keys before using the decoded map. */
    private function unambiguousJson(Validator $validator): mixed
    {
        $raw = $this->getContent();
        if (! $this->isJson() || trim($raw) === '') {
            return null;
        }
        try {
            $json = json_decode($raw, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $validator->errors()->add('request', 'The request body must contain valid JSON.');

            return null;
        }
        // Preserve the earlier endpoint's empty [] request while requiring objects for maps.
        if (! $json instanceof stdClass && $json !== []) {
            $validator->errors()->add('request', 'The sequencing request body must be a JSON object.');

            return null;
        }
        $objects = [];
        $length = strlen($raw);
        for ($offset = 0; $offset < $length; $offset++) {
            if ($raw[$offset] === '{') {
                $objects[] = [];
            } elseif ($raw[$offset] === '}') {
                array_pop($objects);
            } elseif ($raw[$offset] === '"') {
                $start = $offset;
                for ($offset++; $offset < $length; $offset++) {
                    if ($raw[$offset] === '\\') {
                        $offset++;
                    } elseif ($raw[$offset] === '"') {
                        break;
                    }
                }
                $next = $offset + 1;
                while ($next < $length && ctype_space($raw[$next])) {
                    $next++;
                }
                if ($next < $length && $raw[$next] === ':') {
                    $key = json_decode(substr($raw, $start, $offset - $start + 1), true, 512, JSON_THROW_ON_ERROR);
                    $index = count($objects) - 1;
                    if (isset($objects[$index][$key])) {
                        $validator->errors()->add('request', 'Duplicate JSON object keys are not supported in sequencing requests.');

                        return null;
                    }
                    $objects[$index][$key] = true;
                }
            }
        }

        return $json;
    }
}
