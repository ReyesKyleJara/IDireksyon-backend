# Resident roadmap goals

Resident goals retain a target and explicit application decisions. Generated plans,
ordering, Requirements, Guide content, names, ownership and satisfaction are never
stored in a goal. Age and birth date are not retained; age may be requested again.

All routes require an active resident's Sanctum authentication:

| Method | Path | Behavior |
| --- | --- | --- |
| GET | `/api/auth/roadmap-goals` | Current resident's goals, newest updated first |
| GET | `/api/auth/roadmap-goals/{governmentId}` | Current resident's goal for this target, or 404 |
| PUT | `/api/auth/roadmap-goals/{governmentId}` | Create or replace this resident's goal |
| DELETE | `/api/auth/roadmap-goals/{governmentId}` | Remove only this resident's goal |

PUT is idempotent: multiple goals are supported, with one goal per resident/target.
The URL identifies the target, not a global resident-goal ID. A resident can save
the same public target as another resident without accessing their record.

```json
{"application_type":"new","profile_selections":{"35":11,"37":10}}
```

The body permits only application_type (nullable) and profile_selections (a JSON
object with canonical positive Government ID keys and positive integer profile
values, at most 500). Omitted fields are replaced by null/empty decisions. Raw
user IDs, age, ownership, plan snapshots, and private profile fields are rejected.
Duplicate JSON keys are rejected using the existing sequence request validation.

GET/PUT data includes current `government_id: {id,name}`, `application_type`,
`profile_selections`, `discarded_profile_selections`, `is_completed`, and timestamps.
The current name is joined from the catalog. Completion is derived from the
resident's current recorded target ownership and does not imply government verification.

Every read/save checks that each profile still exists and belongs to its keyed
Government ID. Missing, deleted or reparented selections are discarded. A true
`discarded_profile_selections` tells the client to resume normal clarification.
Reading filters the response without silently rewriting the stored goal; the next
explicit/automatic save replaces old choices. No rule is inferred from labels.

Opening a goal refreshes resident inventory and posts its current ownership,
restored application type, optional session age, and valid selections to the
existing public `/api/government-ids/{governmentId}/sequence` endpoint. Current
CMS profile/prerequisite changes therefore affect the next plan. If a selected
profile disappears between reads, the client reloads sanitized selections before
retrying a changed selection map. New decisions on a saved goal are saved; a
failed save leaves the current session plan available with an unsaved warning.

Flutter uses Profile → My Roadmaps. The list deliberately avoids calculated step
counts and recalculates on Open. Saved goals remain present when completed and
can be removed explicitly. Session changes clear screen caches and invalidate
in-flight reads/saves. The existing login transition resets navigation, so a
logged-out temporary roadmap is not carried across login; sign in first to save.
Authentication itself is unchanged.

The resident-only table is excluded by ReferenceData's existing explicit table
allowlist. Never add it to development snapshots. User deletion cascades goals;
target deletion is restricted with a CMS deletion message. No CMS screen redesign
is involved. No real local migration or snapshot export is performed by this step.
