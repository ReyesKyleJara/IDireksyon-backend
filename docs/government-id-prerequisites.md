# Explicit Government ID prerequisites

The [public sequencing API](government-id-sequence-api.md) exposes this foundation without a Flutter
sequencing screen. Requirements and Application Guide continue using their existing contracts. Requirement references, groups, ways, quantities,
Choose-N and OR alternatives never create prerequisite edges.

## Maintenance and persistence

Open an existing Government ID → Smart Sequencing Prerequisites → Manage prerequisites.
Select one existing ID or Document, Everyone or an existing Applicability Profile, a position and
an optional rationale. New entries default to Everyone, visibly, and require an explicit target.
Manual profiles remain available with their Needs rule setup warning.

`government_id_prerequisites` uses fixed IDs and foreign keys. Exactly one prerequisite foreign key
is required. The generated `profile_scope_id` normalizes Everyone to zero solely for unique indexes;
it is neither an applicant fact nor a serialized reference-data field. MySQL uses a CHECK and SQLite
equivalent insert/update triggers for the exclusive target and direct-self constraints.

All foreign keys restrict deletion, including the owner: remove/reassign explicit links first.
CMS screens report incoming usage; profile listings count prerequisites. Existing guide deletion
protection remains. Saving through the model validates ownership, existence, duplicates and the
entire explicit ID graph. The transaction locks Government ID rows in ascending order to serialize
graph additions/edits. Imports take the same lock and validate the merged local/snapshot graph.
Bulk query-builder writes bypass model validation and are not a supported maintenance interface.

Cycles are rejected conservatively across all profiles, even if individual profiles might be
mutually exclusive. No conditional-cycle assumptions or automatic edge removal are made.

## Planning

`PrerequisitePlanner::plan(GovernmentId, ApplicantContext, ?int selectedProfileId, ?ProfileSelections): PrerequisitePlan`
is read-only. It delegates profile resolution to `GovernmentIdApplicability`, whose matching rules
all belong to `ApplicabilityEvaluator`. It never traverses the Requirements projection.

- Everyone links always participate. A selected/resolved profile adds its explicit links.
- Incomplete facts, multiple matches and manual profiles retain potentially applicable links in
  `pendingApplicability`. No profile is inferred from its label.
- The typed, immutable `ProfileSelections` map supplies each Government ID's explicit profile choice.
  All supplied choices are validated for existence and profile ownership before traversal. The legacy
  single argument is root-only and must agree with a supplied root map entry. Traversal otherwise uses
  normal automatic matching, returning the next unresolved ID as needed. Shared facts are not copied.
- The same facts, including application type, are used at each ID. The planner never assumes that
  a prerequisite needs a different application type. A future per-ID context interaction may be
  necessary where renewal of the target requires first-time application for another ID.
- Owned IDs/documents are satisfied nodes with no order; owned IDs prune their entire branch.
  Documents are leaves. Ownership is provided by ApplicantContext, not inferred from requirements.
- Traversal follows each owner's `sort_order`, then prerequisite row ID for stable ties. Depth is
  the first-discovered distance from the target. DFS postorder produces a deterministic, deduplicated
  dependency-before-target list, with one-based order. Shared descendants appear once; edges retain
  all profile/relationship provenance.
- `ready` means the plan is resolved. `manual_confirmation_required` means `orderedMissing` is
  provisional, and unresolved links remain in the result. `configuration_error` returns structured
  errors and no executable order. A defensive graph audit reports cycles even if legacy/bulk writes
  bypassed maintenance validation. No prerequisites yields the target alone (or no missing nodes if owned).

Nodes distinguish Government IDs/Documents, target, satisfied/missing, depth and order. Edges carry
the persisted prerequisite ID, profile, note and position. Pending resolution carries missing facts,
available profiles, matches and pending profile information. These are backend DTOs; the API uses a
separate public allowlist that omits maintainer notes and internal link IDs. `applicabilityResolutions`
retains each traversed ID's original applicability decision. `resolutionStatus()` distinguishes missing
facts, multiple matches and no match without changing the foundation's original three-state `status`.
The API marks non-ready output provisional and requires a matching/manual profile where scoped links
exist, even if the foundation's known-work list contains only the target.

## Reference data and deployment

The strict development v2 allowlist now includes the prerequisite table after its dependencies.
Old snapshots without it import as an empty prerequisite configuration. Fixed IDs, profile links,
notes and positions round-trip; internal generated values, timestamps, accounts and personal data
remain excluded. No snapshot regeneration is required to import the current snapshot. A deliberate
new export is needed once approved prerequisite content should be shared with teammates.

The migration only creates an empty table. It does not copy requirement references or modify any
existing content. Its rollback drops this table and its explicit links; it does not change existing
Requirements, Guide or catalog records. Do not roll back after configuring links without preserving them.

Future work: approve real prerequisite content, then build Flutter sequencing
UI. Alternative/OR prerequisite groups require a separate design if real content demonstrates that need.
No ranking, scores, optional recommendations or AI inference are implemented here.
