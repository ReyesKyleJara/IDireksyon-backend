# Government ID sequencing API

`POST /api/government-ids/{governmentId}/sequence` is a public, read-only endpoint, like the
existing catalog/applicability endpoints. It calculates a plan without storing applicant facts,
manual selections, roadmaps, progress, or checklist state. Existing endpoints are unchanged.

**Only explicit Smart Sequencing prerequisites affect the roadmap.** Requirement references,
accepted IDs/Documents, OR alternatives, ways, quantities and Choose-N evidence never create
dependencies. Documents are leaves; alternative/OR prerequisite groups are not supported.

## Request

```json
{
  "age": 25,
  "application_type": "new",
  "owned_government_id_ids": [42],
  "owned_document_ids": [19],
  "profile_selections": {"43": 9, "42": 18}
}
```

All fields are optional. Omit unknown age/application type; null is also accepted, as in the
applicability API. Ownership defaults to empty lists. `profile_selections` is the preferred format
for explicit choices: each object key is the Government ID being resolved and its value is that ID's
Applicability Profile ID. It may include the root and recursive prerequisites. Omit an entry to use
automatic resolution for that ID. Selections are not applicant facts and do not create per-ID contexts.

`GovernmentIdSequenceRequest` extends the existing applicability validation and reuses `ApplicantContext`:

- Age: JSON integer 0–65535. Application type: `new`, `renewal`, `replacement`, or `custom`.
- Ownership: JSON lists of unique positive integer IDs, at most 500 per list; each record must exist.
- Profile selections: a JSON object (empty `{}` is allowed), at most 500 choices. Keys must be
  canonical positive Government ID integers encoded as strings, with no leading zero/sign/decimal
  aliases or integer overflow. Values must be positive JSON integers. Every Government ID and profile
  must exist, and each profile must belong to the Government ID named by its key.
- Every supplied choice is validated, including choices for owned IDs or branches not currently
  reached. Valid unvisited choices do not create dependencies or force recursion.
- Legacy `selected_profile_id` remains accepted for the root target. It must belong to that target.
  If a root map entry is also supplied, both values must agree; a different value or explicit legacy
  null conflicts and returns 422. Omit the legacy field when using the preferred map. A legacy root
  choice can be combined with child map entries. The combined map is limited to 500 choices.
- Numeric strings, booleans, fractional IDs, unsupported fields and duplicate ownership are rejected.
- Duplicate JSON object keys, including escaped equivalents, are rejected before decoder overwrites
  can silently choose a value. Malformed/ambiguous JSON returns 422 under `errors.request`.
  Map type/key/value/ownership errors use `errors.profile_selections` or its Government ID subkey;
  conflicting legacy root values use `errors.selected_profile_id`.
- Unknown target: HTTP 404. Invalid request: HTTP 422 with Laravel validation errors.

There is no authentication requirement or new persistence. Protected resident routes remain protected.
Ownership is supplied applicant context, not an authenticated inventory assertion or eligibility guarantee.

## Response

All calculated states use HTTP 200 and the existing `{"data": ...}` envelope. A valid request can
produce a plan that still needs facts or confirmation; this is distinct from invalid request data.

| `data.status` | Meaning |
|---|---|
| `ready` | Required applicability decisions are resolved. |
| `insufficient_context` | Supply the facts named in `missing_context`. |
| `manual_confirmation_required` | Unresolved/custom profiles require an explicit choice. |
| `multiple_matches` | Multiple structured profiles match; select one, never the first automatically. |
| `no_match` | An ID has profile-scoped prerequisites, but no usable profile matches supplied facts. |
| `configuration_error` | The prerequisite configuration needs maintainer review. |

Across recursive IDs the deterministic state precedence is: configuration error, insufficient
context, manual confirmation, multiple matches, no match, ready. Each unresolved ID also has its
own exact existing applicability state in `pending_applicability`.

`plan_is_complete` is true only for `ready`. For other non-error states, `plan` contains **provisional**
known work, including Everyone links and the target. Do not present it as a complete roadmap or treat
pending alternatives as cumulative requirements. Configuration errors return no plan or dependency data.

Response fields:

- `government_id`: target `id` and `name`.
- `applicant_context`: normalized age, application type and sorted ownership lists.
- `plan`: ordered actionable nodes, supplied by the planner. Dependencies precede their target.
- `satisfied`: already-owned nodes, separate from actionable work; `order` is null.
- `dependencies`: `{target, prerequisite, applicability_profile}` edges. References contain
  `type`, `id`, `name`; profile is null for Everyone. This preserves relationships for a future roadmap.
- `applicability`: the target's existing applicability structure, or null when no decision was needed.
  It includes `selected_profile`, `matched_profiles`, `manual_profiles`, `pending_profiles`,
  `available_profiles`, matching flags and selection mode, using the existing applicability API fields.
- `pending_applicability`: unresolved IDs, each `{government_id, applicability}`. Use its owner ID to
  identify where a choice is needed. Includes `no_match`, even when there are no pending candidate edges.
- `pending_prerequisites`: undecided explicit links with target reference, prerequisite node and profile.
  These do not enter the actionable plan automatically. A pending owned prerequisite is marked satisfied,
  but applicability still needs confirmation.
- `missing_context`: sorted, deduplicated union of facts needed across the traversed IDs (`age`,
  `application_type`), not UI questions. Root-only facts also appear in `applicability.missing_context`.
- `error`: null normally; safe public `{code, message}` for configuration errors.

Every public node has `type` (`government_id` or `document`), `id`, `name`, `status`, `order`, `depth`,
and `is_target`. Status is `satisfied` when owned, otherwise `target` for the root or `missing` for a
prerequisite. An owned root has `is_target: true`, status `satisfied`, and no actionable work.
Depth is the first-discovered distance from the root, not a ranking. Order is one-based. The API never
reorders the planner's output. Shared nodes appear once; multiple dependency edges retain provenance.

Profile `label` is the backend-provided public summary. Profile IDs are selection keys, not display text.
Clients must not match profiles, infer applicability from labels, compare ownership IDs to calculate
status, traverse dependencies to compute order, or repair cycles themselves.

## Examples

The IDs below are illustrative. Prerequisites must actually be configured by a maintainer.
Examples after the first show relevant response excerpts.

With an explicit Student Permit prerequisite and nothing owned:

```json
{
  "data": {
    "government_id": {"id": 43, "name": "Non-Professional Driver's License"},
    "applicant_context": {"age": 25, "application_type": "new", "owned_government_id_ids": [], "owned_document_ids": []},
    "status": "ready",
    "plan_is_complete": true,
    "plan": [
      {"type": "government_id", "id": 42, "name": "Student Driver's Permit", "status": "missing", "order": 1, "depth": 1, "is_target": false},
      {"type": "government_id", "id": 43, "name": "Non-Professional Driver's License", "status": "target", "order": 2, "depth": 0, "is_target": true}
    ],
    "satisfied": [],
    "dependencies": [{
      "target": {"type": "government_id", "id": 43, "name": "Non-Professional Driver's License"},
      "prerequisite": {"type": "government_id", "id": 42, "name": "Student Driver's Permit"},
      "applicability_profile": null
    }],
    "applicability": null,
    "pending_applicability": [],
    "pending_prerequisites": [],
    "missing_context": [],
    "error": null
  }
}
```

Already owns Student Permit: request `{"owned_government_id_ids":[42]}`:

```json
{
  "status": "ready",
  "plan_is_complete": true,
  "satisfied": [{"type":"government_id","id":42,"name":"Student Driver's Permit","status":"satisfied","order":null,"depth":1,"is_target":false}],
  "plan": [{"type":"government_id","id":43,"name":"Non-Professional Driver's License","status":"target","order":1,"depth":0,"is_target":true}]
}
```

Owned IDs prune their prerequisite branch; owned Documents are satisfied leaves. With no explicit
prerequisites the response is `ready` with the target alone, even if the catalog has profiles or ordinary
Requirement references. An already-owned target instead appears in `satisfied`, with an empty `plan`.

Missing age for a profile-scoped prerequisite: request `{"application_type":"new"}`:

```json
{
  "status": "insufficient_context",
  "plan_is_complete": false,
  "missing_context": ["age"],
  "applicability": {"status":"insufficient_context","selected_profile":null,"missing_context":["age"]}
}
```

Manual profile: the initial response includes backend-labelled options, for example:

```json
{
  "status": "manual_confirmation_required",
  "plan_is_complete": false,
  "applicability": {
    "selected_profile": null,
    "manual_profiles": [{"id":11,"label":"Students • First-Time Application","application_type":"new","applicant_type":"custom","min_age":null,"max_age":null,"matching_mode":"manual_only"}]
  }
}
```

For a request targeting Government ID 35, resubmit the same facts/ownership with
`"profile_selections":{"35":11}`. The planner rebuilds the plan;
the root applicability reports `selection_mode: "manual"`, `status: "manual_selected"`, and that
selected profile. The sequencing status becomes `ready` when no other ID needs resolution.
Overlapping matches use the same flow, with options in `matched_profiles`.

### Progressive recursive choices

Suppose target A (100) requires B (200). Selecting B's manual profile (21) introduces C (300),
which has overlapping profiles 31 and 32. These are illustrative IDs, not approved reference content.

First request to `/api/government-ids/100/sequence`:

```json
{"age":25,"application_type":"new"}
```

Relevant response excerpt:

```json
{
  "status":"manual_confirmation_required",
  "plan_is_complete":false,
  "pending_applicability":[{
    "government_id":{"id":200,"name":"B"},
    "applicability":{
      "status":"manual_confirmation_required",
      "manual_profiles":[{"id":21,"label":"Students","matching_mode":"manual_only"}],
      "missing_context":[]
    }
  }]
}
```

Select B using its backend-provided profile ID:

```json
{"age":25,"application_type":"new","profile_selections":{"200":21}}
```

The next response identifies C with `applicability.status: "multiple_matches"` and options in
`matched_profiles`. `plan_is_complete` stays false. Preserve B's choice and submit C's choice too:

```json
{"age":25,"application_type":"new","profile_selections":{"200":21,"300":31}}
```

Once all encountered decisions are resolved, the response is `ready`, `plan_is_complete` is true,
and `plan` gives the planner's dependency order, for example Document → C → B → A. No client recursion
or inferred matching is required. If another unresolved ID is introduced, it is returned in the same
pending structure. Missing facts use `insufficient_context` and the shared `missing_context` names.

Resubmit prior choices during progressive resolution: this endpoint stores none of them. Removing
an entry requests automatic resolution again for that ID. Explicit choices are manual overrides;
clear or reconsider them when applicant facts change. One shared ApplicantContext is still used
throughout the plan. Owned IDs satisfy/prune their entire branch, even if selections are retained.

## Privacy, errors and current limits

Maintainer `note` is private rationale. It is omitted from nodes, dependencies and pending data; no
HTML-stripping shortcut can make internal editorial notes appropriate public explanations. A future
public rationale would need a separate reviewed field. Internal prerequisite row IDs, generated scope
columns, timestamps and database column names are not part of this response.

A cyclic/invalid graph returns:

```json
{"data":{"status":"configuration_error","plan_is_complete":false,"plan":[],"satisfied":[],"dependencies":[],"error":{"code":"invalid_prerequisite_configuration","message":"The prerequisite plan is unavailable because its configuration needs review. Please try again later."}}}
```

The server logs the target ID and planner diagnostics, not submitted age, ownership or maintainer notes.
Public errors contain no graph internals, stack trace or SQL. The existing defensive graph audit is
catalog-wide: a corrupt unrelated configured edge can also require maintainer review before planning.

`profile_selections` resolves each encountered Government ID independently through the same shared
applicability evaluator. The legacy single field is root-only; it is never applied to a child.
`plan_is_complete` stays false while any encountered, unsatisfied branch still needs facts, a profile
choice, or configuration review. Input map ordering never determines roadmap order.

The same ApplicantContext/application type is reused at every ID. There is no automatic conversion
from renewal of a target to first-time application of a prerequisite. Different per-ID application
contexts are a future extension. No optional ranking, OR prerequisite groups, recommendations, roadmap
history or Flutter sequencing screen are introduced here. The development snapshot is not regenerated.
