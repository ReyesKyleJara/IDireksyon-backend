# Database foundation implementation report

## Inspection checkpoint

Checked git status/diff, tracked and untracked changes, migrations and live migration status, models, seeders, controllers, requests, routes, CMS views, and tests. This was an unfinished working tree, not a blank project; no reset, clean, merge, commit, or push was performed.

Previous completed work: Government IDs and Documents had separate tables and shared directory/detail/create/edit views; requirements had ID/document/custom references and pivots; application steps were JSON; fee notes/source fields existed. Breeze provided login/self-profile functionality but there were no resident API routes, application variants, readiness engine, or roadmap services.

Unfinished work: migration 020000 introduced agencies, barangays, categories, offices and office pivots; its models/controller wiring was incomplete. Levels were constants, category hierarchy and office hours were absent, and reference CMS pages did not exist. Baseline testing found an incorrect office pivot key. A stray `bug` string was also present in the ID directory wrapper. These defects were corrected rather than replacing the shared CMS.

## Schema and models

- Applied existing unfinished `2026_09_15_020000_add_catalog_reference_tables.php` without rewriting previously applied migrations.
- Added/applied `2026_09_15_030000_complete_directory_foundation.php`: levels, category parent links, reference status/description fields, office contact/location/source fields, office_hours, directory level_id, record_type and availability_status.
- Models: Agency, Barangay, Category, Office, Level, OfficeHour; GovernmentId and Document now link to agency/category/level/offices. Office links use explicit matching pivot keys and timestamps.
- Kept legacy agency text and issuance_level for compatibility. The relationship selectors are primary; old text can be assigned progressively. Old content fields, JSON application steps, and requirements are preserved.
- Category cycles, invalid references, overlapping/reversed office intervals, out-of-range coordinates and mismatched barangay locations are rejected. Referenced records cannot be deleted through the reference CMS; use inactive status instead.

## Seeders and live results

`CatalogFoundationSeeder` reads `database/seeders/data/catalog-inventory.json`. It seeds references, adopts matching existing inventory once, and does not overwrite researched fields or repeat later edits. Duplicate alias matches are reported for manual review rather than merged. The old `GovernmentIdSeeder` and `DatabaseSeeder` now call it, replacing unconditional mock inserts. No demo credentials or user accounts are created.

Live database after import: 17 government IDs/credentials, 25 documents, 3 levels, 25 categories, 24 barangays, 13 agencies and 4 office research leads. The inventory manifest has 41 entries; an additional existing entry was preserved. Office hours and office-service pivots are empty deliberately, pending source confirmation.

## CMS changes

Preserved `resources/views/admin/catalog/` and its existing field/icon components. Added shared `admin/references/index` and `admin/references/form`, driven by ReferenceController. Reference sections support viewing/editing, name search, status filters, creation, deactivation/reactivation and guarded deletion. Office hours are managed inside office editing, including separate intervals. The catalog includes agency/category/level selectors, record types, research/availability states, research notes, and office checkboxes. Sidebar navigation groups directory references separately from government/location modules.

## CMS coverage

| Domain table / data | Coverage | Interface / limitation |
| --- | --- | --- |
| levels | Full foundation management | Reference directory/editor; guarded deletion |
| categories | Full foundation management | Parent selector, cycle validation, status and guarded deletion |
| barangays | Full foundation management | Reference directory/editor |
| agencies | Full foundation management | Reference directory/editor; linked-record protection |
| offices | Full foundation management | Location/contact/source/status editor |
| office_hours | Full foundation management | Nested office intervals, add/edit/remove; unknown schedules remain empty |
| office_government_id / office_document | Full relationship management | Catalog office checkboxes; pivot screens are unnecessary |
| government_ids / documents | Full current-field management | Shared CRUD, status/archive, search/filter, references and requirement managers |
| requirements / government_id_requirement / document_requirement | Partial | Add/view/remove via catalog; no complete rule/alternative/condition editor yet. Legacy standalone controller is unfinished and not in navigation |
| users | Partial self-service only | Breeze login/registration and own-profile editing; no admin user-management screen |
| application steps / fee notes / source fields | Current embedded fields manageable | Steps stay JSON; fee/source fields are not yet normalized multi-record rules |

Not created yet: user_profiles, user_goals, application_variants, requirement_groups/options, dedicated application_steps/fees/official_sources, user_documents/user_government_ids, roadmaps/roadmap_steps, content_change_logs. They therefore have no CMS modules yet. Future domain tables should receive a related workflow when implemented. Laravel infrastructure tables remain unexposed.

## Authentication and publication

No role system was added. Existing local `/admin` access remains unrestricted as previously agreed; Breeze authentication is not currently enforced on those routes. Before shared/online use, add centralized admin authentication/authorization. A registered ordinary user must not automatically become an admin. Future Super Admin coverage and account management remain explicit work.

Research status and issuance availability are separate. `verified`/new-issuance confirmation requires an official source and source-check date in the editor; these states do not automatically publish anything. There is no resident directory API yet. The future API must enforce publication and availability rules explicitly.

## Verification

Migrations and safe seeder ran successfully against local MySQL. 52 Laravel tests passed with 319 assertions. The test suite covers existing CMS functionality plus repeatable seeding, preservation of research, classification/office links, category cycles, reference CRUD/deletion, coordinate validation and office hours. Frontend build and Blade compilation passed. Pint is the repository formatter. No resident API tests exist because no resident API routes have been implemented. Visual browser review was not completed because Chrome computer access was not approved earlier.

## Research limits and next decisions

See `inventory-classification-review.md` for the full mapping and official references. No blanket office schedules, precise unverified coordinates, application procedures, costs, readiness scores, or prerequisite edges were invented. The paper includes third-party guidance and generic office suggestions; those were not treated as complete verified rules.

Before sequencing: settle application variants/applicant conditions; ALL/ANY groups; when an obtain-first dependency is justified; per-office exceptions; date/version/source review and publishing; unknown/legacy availability handling; and expected outcomes for test scenarios. Define readiness separately from eligibility and approval. Decide who can review/publish before building the future audit workflow.
