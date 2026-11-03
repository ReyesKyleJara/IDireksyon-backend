# CMS completion batch — implementation and verification report

Date: 29 October 2026, Asia/Manila. Implementation reference: [41-document audit](41-document-cms-audit-2026-10-29.md).

**Decision: implementation is available for review; bulk encoding is NOT signed off.** Automated catalog checks pass. Six actual browser CMS → API → running Flutter workflows remain incomplete because browser control repeatedly timed out. A separate profile setup layout regression also remains. No commits or pushes were made.

## Implemented behavior

- Existing six-step Add/Edit wizard retained. Application Guide is the main transaction/request-channel editor. Requirements, Fees, and Offices show a compact channel selector and a link back to channel management. Review shows saved metadata, rather than another editor.
- Existing requirement sets identify scenarios. Structured channel metadata adds method/provider, portal, fulfillment, appointment, applicability, processing basis, references to saved fees/offices, and exceptional situations. Existing checklist groups and guide steps remain attached to the same sets.
- Transactions include first application, renewal, replacement, correction, copy, authentication, retrieval, and custom cases. Applicant types and existing age qualifiers are retained.
- Requirements use their existing editor. Fees and Offices remain reusable record-level entries; their channel associations are selected in Application Guide. Null scope means all saved entries; an empty selected list means none. Referenced entries cannot be silently removed.
- Research metadata records draft/needs-research/reviewed status, official source URLs, explicit checked dates, publication dates, scope/page references, validity/recency notes, and hidden resident sections. Reviewed status requires dated sources. New wizard records begin as drafts. This does not change the existing sequencing or availability rules or implement a publication queue.
- Separate special cases are stored outside normal guide steps.
- New Documents directory/detail APIs use explicit document identity. Government ID detail gains additive research/path data without removing legacy fields.
- Flutter opens document details from the document directory, inventory, linked requirements, and roadmap previews. Channel selection scopes requirements, fees, guide, offices, processing, fulfillment, and troubleshooting together. The legacy requirements/fee fallback is suppressed when a channel is selected.
- Resident guides/offices come from saved CMS content. Hardcoded Passport instructions and sample DFA offices were removed from the shared detail screen. Empty/hidden sections are omitted while stored content remains. Informational Only records retain factual information and sources without acquisition tabs.
- Catalog fingerprints detect stale nested submissions in active wizard/channel/checklist flows. Successful AJAX checklist saves refresh all form tokens. Validation failures retain submitted drafts; authentication/network errors retain checklist editor state and display recovery guidance. Tokens remain optional for older callers; this is not a claim that every legacy client enforces optimistic locking.
- Requirements AJAX saves validate prerequisites and checklist changes in one transaction before advancing. Existing record-level prerequisite semantics are unchanged; the CMS explains why conditional evidence belongs in checklists.
- Step links and main-form submissions preserve the selected path. A missing path becomes null instead of zero, matching the Guide editor's expected next URL and preventing the `path=0` false save-confirmation failure.

## Schema and data impact

Created `database/migrations/2026_10_29_000000_add_catalog_encoding_metadata.php`:

| Table | Added column | Type / default |
| --- | --- | --- |
| government_ids | research_metadata | nullable JSON; null for existing rows |
| documents | research_metadata | nullable JSON; null for existing rows |
| government_id_requirement_sets | request_channel | nullable JSON; null for existing rows |
| document_requirement_sets | request_channel | nullable JSON; null for existing rows |

`up()` only adds these columns. There are no new foreign keys, cascades, backfills, catalog replacements, or record deletions. Channel fee/office references are validated against existing parent relationships by the service. Example channel: `{"label":"Online retrieval","channel":"online","fulfillment":["download"],"appointment":"not_required","fee_ids":[],"office_ids":[]}`.

`down()` checks all four tables first. If any new column contains saved metadata it throws before dropping anything, preventing silent loss of encoded research. Otherwise it removes only the four added columns.

The migration ran in automated test databases and a disposable `/tmp/idireksyon-encoding-audit.sqlite` browser database only. It was NOT applied to the configured real catalog. Test fixtures are synthetic and are not verified government guidance. No real-catalog migration/reset/seeding/bulk encoding was performed. There is no before/after real-catalog snapshot, so independent catalog-integrity comparison remains unverified; automated preservation and ownership checks passed. The unrelated workspace file was not edited.

## Verification results

| Check | Audit baseline | Completion result |
| --- | --- | --- |
| Laravel/Pest full suite | 491 passed; 6,033 assertions | **503 passed; 6,284 assertions** |
| CMS JavaScript suite | 98 passed | **100 passed**, including token propagation and combined prerequisite payload |
| CMS Vite build | — | **Passed**; existing output-size warning above 500 kB |
| Flutter full suite | 145 passed; 15 failed | **174 passed; 1 failed** in the final full run |
| Flutter catalog-focused checks | — | **15 passed**, including six API contracts; broader catalog/requirements/directory layout run: **37 passed** |
| Flutter static analysis | — | **No issues found** |
| Flutter web compile | Documented `/app/` was 404 | **Passed**, temporary web build; compilation does not establish browser behavior |
| Browser Add/Edit/Save/Reopen | Authentication blocked | **Partial only**; details below |

Remaining full-suite failure: `profile_setup_test.dart` / “small screens with large text scroll without overflow”. Profile setup overflows at large text on 320×640. An earlier full run also failed `auth_mascot_peek_test.dart` / “New turns change pose and location while avoiding content”; that passed both in isolation and in the final full run, so it remains a nondeterminism to track. The profile failure is outside this catalog implementation. Authentication/profile application code was not changed to make tests pass. Stale fixtures and navigation assertions were updated while preserving save, retry, selection, validation, and logout checks.

### Six automated CMS → API → Flutter contracts

The six backend cases submit through CMS HTTP endpoints, save requirements and guide, reopen all six wizard steps, and check the typed Documents API. An explicit opt-in capture writes those exact isolated API responses to frontend test fixtures; Flutter renders them using the real detail model/screen. These are automated integration contracts, not live browser acceptance tests.

| Synthetic record | Distinct context exercised | Automated result |
| --- | --- | --- |
| PSA Birth Certificate | Outlet certified copy / pickup | CMS/API + Flutter render passed |
| NBI Clearance | Online renewal appointment / pickup | CMS/API + Flutter render passed |
| Business Permit | Hybrid local counter; scoped existing fee and office | CMS/API + Flutter render passed |
| DFA Apostille | Authentication appointment / processing basis | CMS/API + Flutter render passed |
| PhilHealth MDR | Portal retrieval / online viewing / no physical office | CMS/API + Flutter render passed |
| SRB | Informational Only; acquisition data retained but not presented | CMS/API + Flutter render passed |

Additional assertions cover foreign-parent references, referenced-fee deletion prevention, stale nested edits, same numeric document/ID identifiers, atomic prerequisite failure, section hiding without data deletion, unsafe URLs, reviewed-source validation, guarded migration rollback, channel switching, and selected-path save redirects.

### Actual browser verification boundary

The disposable browser CMS fixture `AUDIT — PSA Birth Certificate` was created through the UI. Basic content/source entry, a transaction/channel, a special case, a checklist requirement, and a fee were saved. A saved record was reopened after session expiry. The expired checklist session displayed a recovery message and retained its draft. Guide saving exposed the false save-confirmation / `path=0` issue; code and HTTP regression checks now cover its correction.

After the reorganization, browser-control calls repeatedly timed out, including browser inventory and reopening the Guide. Therefore the reorganized editor, successful Guide confirmation, six complete browser workflows, actual running Flutter channel switching, manual narrow-screen/link checks, and simultaneous MySQL collaboration are **NOT verified**. No final screenshot was captured. A successful web build and widget tests are not substituted for these checks.

## Files created

Backend-relative paths:

| File | Purpose / important addition |
| --- | --- |
| database/migrations/2026_10_29_000000_add_catalog_encoding_metadata.php | Four additive JSON columns and loss-preventing rollback guard |
| app/Services/CatalogEncoding.php | Metadata validation, ownership/scoping, preservation guard, resident projection |
| app/Services/CatalogRevision.php | Fresh nested-content fingerprint |
| app/Http/Middleware/CheckCatalogRevision.php | Parent lock, stale-save rejection, JSON revision refresh |
| app/Http/Controllers/Admin/CatalogChannelController.php | Authorized existing-set channel save; returns to Guide |
| app/Http/Controllers/Api/DocumentController.php | Typed public document directory/detail |
| resources/views/admin/catalog/partials/research-fields.blade.php | Source/status/visibility form |
| resources/views/admin/catalog/partials/channels.blade.php | Structured channel/special-case editor in Guide |
| resources/views/admin/catalog/partials/channel-context.blade.php | Compact context on connected steps |
| resources/views/admin/catalog/partials/encoding-summary.blade.php | Saved metadata in Details/Review |
| tests/Feature/CatalogEncodingTest.php | Six workflows and preservation/reliability/navigation coverage |
| docs/cms-completion-batch-2026-10-29.md | This review report |

Frontend-relative paths:

| File | Purpose / important addition |
| --- | --- |
| test/catalog_encoding_test.dart | Typed API, six captured contracts, channel isolation, visibility, responsive rendering |
| test/fixtures/catalog/copy.json | Captured outlet-copy API contract |
| test/fixtures/catalog/renewal.json | Captured renewal API contract |
| test/fixtures/catalog/new.json | Captured local-counter/fee/office API contract |
| test/fixtures/catalog/authentication.json | Captured authentication API contract |
| test/fixtures/catalog/retrieval.json | Captured portal-retrieval API contract |
| test/fixtures/catalog/replacement.json | Captured Informational Only API contract |

## Files modified

Backend-relative paths:

| File(s) | Change / connection |
| --- | --- |
| app/Models/Document.php; app/Models/GovernmentId.php | Fillable/cast research metadata used by CMS and API |
| app/Models/GovernmentIdRequirementSet.php | Scenario codes and channel JSON cast shared with document sets |
| app/Support/ResidentRequirements.php | Existing checklist projection supports documents |
| app/Http/Controllers/Admin/Concerns/PreparesCatalogEntry.php | Research validation; preserve scoped fee IDs and office links; minute/hour processing units |
| app/Http/Controllers/Admin/DocumentCreationWizardController.php; GovernmentIdCreationWizardController.php in the same directory | Draft default, all scenarios accessible, selected-path context and redirects |
| app/Services/GovernmentIdChecklists.php | Atomic prerequisite/checklist save and channel labels |
| app/Http/Controllers/Admin/DocumentChecklistController.php; GovernmentIdChecklistController.php in the same directory | Use combined checklist save service |
| app/Http/Controllers/Api/GovernmentIdController.php | Additive research/path projection |
| routes/web.php; routes/api.php | Channel save/revision middleware and Documents endpoints |
| resources/views/admin/government_ids/wizard/page.blade.php | Guide-only full management, context selectors, revision/path fields, scoped guide data |
| resources/views/admin/government_ids/wizard/basic.blade.php | Include research editor |
| resources/views/admin/government_ids/wizard/review.blade.php | Include saved metadata summary |
| resources/views/admin/government_ids/wizard/app-preview.blade.php | Accurate description of CMS-backed resident rendering |
| resources/views/admin/catalog/show.blade.php | Display saved research/channel summary |
| resources/views/admin/government_ids/partials/processing-time-field.blade.php | Minute/hour units |
| resources/views/admin/government_ids/partials/checklists.blade.php | Supply catalog revision to JS |
| resources/js/government-id-checklists.js | Send/refresh revision and combine prerequisite payload |
| tests/Feature/GovernmentIdCreationWizardTest.php | Updated management placement and saved-guide expectations |
| tests/Feature/GovernmentIdApplicationGuideManagementTest.php; GovernmentIdApplicationGuideStorageTest.php; GovernmentIdChecklistManagementTest.php; GovernmentIdRequirementGroupManagementTest.php; GovernmentIdRequirementSetTest.php; GovernmentIdRequirementWaysTest.php in the same directory | Keep legacy-field preservation assertions while acknowledging additive contextual API data |
| tests/Unit/requirements-editor.test.mjs | Revision propagation and atomic-request payload checks |

Frontend-relative paths:

| File | Change / connection |
| --- | --- |
| lib/models/government_id.dart | Preserve raw typed payload and research/paths; scoped copies suppress legacy fallbacks |
| lib/services/api_service.dart | Documents directory/detail requests |
| lib/features/ids/id_details_screen.dart | Shared CMS-backed channel-aware detail, rich text, safe official links, real offices, conditional sections |
| lib/features/ids/ids_screen.dart | Document directory navigation and correct document labels |
| lib/features/ids/requirements_tab.dart | Typed linked-record detail actions |
| lib/features/profile/manage_inventory_screen.dart | Typed record information action alongside ownership |
| lib/features/roadmap/roadmap_plan_panel.dart | Document Preview Guide without planner changes |
| test/requirements_tab_test.dart | Current saved-detail/empty/error behavior and pinned title |
| test/informational_guidance_test.dart | Retained data/mode behavior without invented guide/office tabs |
| test/roadmap_plan_test.dart | Include typed document preview |
| test/home_journey_banner_test.dart | Preserve journey navigation checks with current shell |
| test/signup_screen_test.dart | Current signup validation/navigation and bounded animation pumping |
| test/profile_setup_test.dart | Numeric inventory/barangay fixtures and actual setup flow; remaining responsive failure retained |
| test/account_details_test.dart | Current field labels, persisted profile/password validation and navigation |
| test/logout_test.dart | Current confirmation, session clearing, and bounded login animation wait |

Vite regenerated `public/build/manifest.json` and its referenced CSS/JS assets. Flutter generated SDK/build caches and the temporary web output. No authored files were intentionally deleted. No libraries were installed. No Smart Sequencing/Readiness algorithm, authentication implementation, or unrelated CMS screen redesign was performed.

## Remaining acceptance gates

1. Restore browser control and complete all six actual CMS Add/Edit/Save/Reopen → API → running Flutter workflows, including the reorganized Guide, a second channel, and switching back.
2. Verify real database migration/deployment and preservation against a backup/snapshot. MySQL locking/concurrent-editor behavior is not certified by SQLite tests.
3. Resolve or separately accept the existing profile large-text overflow; track the nondeterministic mascot test.
4. Review the completed changes before any commit/push. Do not begin bulk encoding yet. After review and the missing checks, test three actual researched documents through CMS before delegating the rest to authorized encoders.

The structural additions address the approved encoding model, but this report does not certify all 41 documents' factual content or promise that no additional issue can be found during encoding. Local/agency facts still need explicit official verification.
