# Government ID applicability API

`POST /api/government-ids/{governmentId}/applicability`

Public, read-only evaluation. Send `Content-Type: application/json` and
`Accept: application/json`. No login is required. This endpoint does not save
context, inventory, selections, or CMS content. Existing GET list, detail, and
`/content` responses are unchanged.

## Request

```json
{
  "age": 20,
  "application_type": "new",
  "owned_government_id_ids": [36, 50],
  "owned_document_ids": [7],
  "selected_profile_id": null
}
```

All fields are optional; `{}` is valid.

| Field | Validation and meaning |
| --- | --- |
| `age` | JSON integer, 0–65535, or null. Same range as the existing context/profile model. Omitted/null means unknown. |
| `application_type` | `new`, `renewal`, `replacement`, `custom`, or null. `custom` does not make unresolved profiles automatically match. |
| `owned_government_id_ids` | JSON list of up to 500 distinct positive integer IDs existing in `government_ids`; default `[]`. |
| `owned_document_ids` | JSON list of up to 500 distinct positive integer IDs existing in `documents`; default `[]`. |
| `selected_profile_id` | Positive JSON integer belonging to this Government ID, or null. An explicit **manual** choice, never a client-calculated automatic match. |

Numeric strings, fractions, booleans, duplicate ownership references, unknown
fields, and nonexistent references are rejected with Laravel's usual 422
`message`/`errors` response. An unknown target Government ID returns 404.
Owned lists are sorted by `ApplicantContext` after validation. They are
client-declared catalog references, not verified proof of ownership or inventory
updates. Current profile rules use age and application type; ownership is retained
in the shared context without adding sequencing behavior.

## Response

All successful responses use `{ "data": { ... } }` with these fields:

| Field | Meaning |
| --- | --- |
| `government_id` | Public `id` and `name` only. |
| `applicant_context` | Normalized age, application type, and owned lists; unknown facts stay null. |
| `applicability` | Resolution state and profile options, described below. |
| `requirements.profiles` | Existing requirement-set projections for every automatic match, or just the manually selected profile. |
| `requirements.pending_profiles` | Full requirement structures for undecided profiles, including custom profiles. Empty after explicit manual selection. |
| `application_guide.steps` | Everyone steps plus automatically matched profile steps, or Everyone plus the manually selected profile's steps. |
| `application_guide.pending_steps` | Potentially relevant guide content that could not be decided. Empty after manual selection. |

Requirement profiles have the **same structure** as entries in the existing GET
detail response's `requirement_sets`: groups, conditions, alternative ways,
required counts, qualifications, items, quantities, copies, and references remain
intact. Profile applicability does not resolve or discard conditional groups.
Those existing conditions remain visible for confirmation as needed.

A guide step contains `id`, `title`, `short_description`, `type`, `sort_order`,
`applicability_profile_id`, and `blocks`. A null `applicability_profile_id` means
Everyone **for this Government ID**. Blocks contain only `id`, `type`,
`section_title`, `content`, and `sort_order`.

Guide order is `sort_order`, then Everyone before profile steps at equal
positions, then profile ID and step ID as stable tie-breakers. Pending steps use
the same ordering. Everyone content is returned even when no profile is resolved.

Profile options contain `id`, `label`, `application_type`, `applicant_type`,
`min_age`, `max_age`, and `matching_mode` (`automatic` or `manual_only`). Labels
are display text and must not be parsed to determine applicability.

## Resolution states

`applicability` always includes:

- `status` and `selection_mode` (`automatic`, `manual`, or `none`).
- `has_single_automatic_match` and `has_multiple_automatic_matches`, based on
  context alone, even if a manual selection was supplied.
- `requires_manual_confirmation`, which concerns **profile resolution**.
- `missing_context`, an ordered list of needed fact names such as `age` and
  `application_type`; no user-facing question text.
- `available_profiles` (all manual-selectable options), `matched_profiles`
  (automatic matches only), `manual_profiles` (unresolved/custom options), and
  `pending_profiles` (all undecided options).
- `selected_profile`, either a profile object or null.

States are checked in this precedence order:

| Status | Meaning |
| --- | --- |
| `manual_selected` | Caller explicitly chose an owned profile. Its content is returned even if supplied context disagrees. Selection mode is `manual`; matching mode is unchanged. |
| `insufficient_context` | One or more undecided structured profiles need additional facts. Known matches and Everyone content are still returned. |
| `manual_confirmation_required` | At least one custom/unresolved profile remains. Labels and age limits are never used to infer its missing rules. |
| `multiple_matches` | More than one structured profile matches, with no other unresolved profiles. All matching content is returned; none is arbitrarily selected. |
| `matched` | Exactly one structured profile matches, with no undecided profiles. Selection mode is `automatic`. |
| `no_match` | No profile matches and no profile is undecided, including IDs with no profiles. Everyone content remains available; do not invent a match. |

`selected_profile` stays null in unresolved/multiple/no-match states. Match-count
flags remain independent of status: a known match plus an undecided profile is
not a fully resolved selection. `requires_manual_confirmation` is true if any
profile is undecided or multiple profiles match. It becomes false after manual
selection; `missing_context` then becomes empty because further facts are not
needed for that explicit selection. This does not claim missing facts were learned.

Automatic evaluation uses `ApplicabilityEvaluator`. Guide evaluation uses
`GovernmentIdApplicationGuide`; request normalization uses `ApplicantContext`.
Clients should not implement parallel age/profile rules.

## Example responses

These are illustrative **response excerpts**; omitted fields follow the schema
above. IDs and profile labels are examples, not hardcoded constants.

### One automatic match

Request: `{ "age": 20, "application_type": "new" }`.

```json
{
  "data": {
    "government_id": { "id": 36, "name": "Passport" },
    "applicant_context": {
      "age": 20, "application_type": "new",
      "owned_government_id_ids": [], "owned_document_ids": []
    },
    "applicability": {
      "status": "matched", "selection_mode": "automatic",
      "has_single_automatic_match": true,
      "has_multiple_automatic_matches": false,
      "requires_manual_confirmation": false, "missing_context": [],
      "selected_profile": { "id": 10, "matching_mode": "automatic" },
      "matched_profiles": [{ "id": 10 }],
      "manual_profiles": [], "pending_profiles": []
    },
    "application_guide": {
      "steps": [
        { "id": 200, "title": "Book appointment", "sort_order": 0, "applicability_profile_id": null },
        { "id": 201, "title": "Prepare adult documents", "sort_order": 1, "applicability_profile_id": 10 }
      ],
      "pending_steps": []
    }
  }
}
```

### Incomplete context

Request: `{ "application_type": "new" }`, with adult and minor profiles.

```json
{
  "data": {
    "applicability": {
      "status": "insufficient_context", "selection_mode": "none",
      "has_single_automatic_match": false,
      "has_multiple_automatic_matches": false,
      "requires_manual_confirmation": true, "missing_context": ["age"],
      "selected_profile": null, "matched_profiles": [],
      "pending_profiles": [{ "id": 10 }, { "id": 11 }]
    },
    "requirements": { "profiles": [], "pending_profiles": [{ "id": 10 }, { "id": 11 }] },
    "application_guide": {
      "steps": [{ "id": 200, "applicability_profile_id": null }],
      "pending_steps": [{ "id": 201, "applicability_profile_id": 10 }]
    }
  }
}
```

### Unresolved profile and manual selection

Before selection, custom profile 12 appears in `manual_profiles` and
`pending_profiles` with `matching_mode: "manual_only"`. With otherwise complete
context, status is `manual_confirmation_required`. Its requirements and steps
remain in the pending arrays.

Request: `{ "selected_profile_id": 12 }`.

```json
{
  "data": {
    "applicability": {
      "status": "manual_selected", "selection_mode": "manual",
      "requires_manual_confirmation": false, "missing_context": [],
      "matched_profiles": [], "pending_profiles": [],
      "manual_profiles": [{ "id": 12, "label": "Students • First-Time Application", "matching_mode": "manual_only" }],
      "selected_profile": { "id": 12, "matching_mode": "manual_only" }
    },
    "requirements": { "profiles": [{ "id": 12 }], "pending_profiles": [] },
    "application_guide": {
      "steps": [{ "id": 200, "applicability_profile_id": null }, { "id": 212, "applicability_profile_id": 12 }],
      "pending_steps": []
    }
  }
}
```

### Multiple matches

Request: `{ "age": 20, "application_type": "new" }`, with overlapping adult
and all-applicant new-application profiles.

```json
{
  "data": {
    "applicability": {
      "status": "multiple_matches", "selection_mode": "none",
      "has_single_automatic_match": false,
      "has_multiple_automatic_matches": true,
      "requires_manual_confirmation": true, "missing_context": [],
      "selected_profile": null, "matched_profiles": [{ "id": 10 }, { "id": 13 }],
      "manual_profiles": [], "pending_profiles": []
    },
    "requirements": { "profiles": [{ "id": 10 }, { "id": 13 }], "pending_profiles": [] },
    "application_guide": {
      "steps": [{ "id": 200, "applicability_profile_id": null }, { "id": 201, "applicability_profile_id": 10 }, { "id": 213, "applicability_profile_id": 13 }],
      "pending_steps": []
    }
  }
}
```

## Flutter follow-up

Keep one context/selection in the ID-detail flow and send it to this endpoint.
Ask only for `missing_context` facts, offer manual options when required, and
preserve pending content instead of hiding it. On explicit selection, resend the
same context plus `selected_profile_id`. Use the returned requirements and guide
for both tabs. No Flutter implementation is included in this step.
