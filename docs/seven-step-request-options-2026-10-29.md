# Seven-step CMS wizard and Request Option UX

29 October 2026. This report supersedes the six-step layout described in the earlier completion and Special Case Guide reports. Earlier implementation and test history remain in those files.

Implementation is ready for manual review. It is not approval for bulk encoding, committing, pushing, or deployment.

## Implemented behavior

Both Government IDs and Documents use this order for Add and Edit:

1. Basic Information
2. Transactions & Request Channels
3. Requirements
4. Fees
5. Application Guide
6. Offices
7. Review

Step 2 is the only main request-option management location. Cards show a display name, transaction/provider context, Edit Option, a secondary Delete menu, and View Related Content. Add Request Option opens a dedicated form with transaction, method, provider/service, and Display Name. New names are suggested from the choices; saved/custom names are retained. More Details & Settings contains the existing applicant/age qualifiers, service URL, appointment, processing, fulfillment, fee/office associations, and short channel help.

An automatically created, unconfigured initial checklist is configured using its existing ID. Editing posts its existing path ID and does not create a replacement. There is no conversion/backfill of saved catalog data. Delete refuses an option containing saved requirement groups or application steps; deleting an empty option keeps reusable fees/offices and independent case guides.

Requirements, Fees, Application Guide, and Offices have one compact Currently editing selector and a Manage Request Options link to Step 2. They have no duplicate request-option creation/editing forms. Selection is scoped to the catalog type and record, retained in the editor session, carried in navigation/form URLs, validated against the parent, and recovered after errors. Reopening the same record in the same session remembers it. Separate browsers/sessions retain their own selection; no catalog column is used for an editor preference.

Requirements and normal guide steps load only the selected option. Fees/offices distinguish shared/all (`null`), selected IDs, and explicitly none (`[]`). Explicitly scoped saves retain reusable entries belonging to other options. Removing an association does not delete its underlying reusable entry. Editing an entry reused by several options updates the shared entry, as the context notice explains. Existing full-record save endpoints retain their compatibility behavior. Guide preview reference counts and processing information use the selected option. Review identifies its totals as covering all saved options.

Application Guide contains normal instructions plus Special Cases / Additional Help. Dedicated case guides retain their existing independent checklist, steps, fee/office links, notes, sources, revision protection, and resident presentation. Their management forms remain outside the normal guide form. Cases do not become normal request paths or Smart Sequencing requirements.

The option editor warns about unsaved changes and restores submitted input after validation errors. Fees/Offices warn before leaving changed forms. The existing Guide confirmation logic continues to compare the successful redirect with the expected next-step URL; generated navigation does not use `path=0`.

## Repository and database boundary

The process listening on port 8000 is PHP's local server, serving `/Users/kai/IDireksyon-backend/public` from this repository. Its base version is branch `main`, commit `e0455d0`, plus the existing uncommitted CMS/Special Case work and these changes. Its inspected local configuration uses MySQL database `idireksyon`; configuration was inspected without printing credentials or querying catalog records.

Port 8000 was not used for any record mutation, migration, reset, seeding, or encoding. No real-catalog before/after database snapshot was taken, so an independent real-data integrity comparison is not claimed. Data-preservation checks use synthetic test records.

Automated backend writes use `phpunit.xml`'s SQLite `:memory:` database and `RefreshDatabase`. No new migration was created or applied to the real database. The prior pending metadata migration remains part of the earlier uncommitted batch. The unrelated workspace file, earlier uncommitted work, and Flutter source files were preserved. No libraries were installed. No commits or pushes were made.

## Verification

Results below are from the final runs. Logs are local temporary artifacts, not catalog data.

| Check | Previous Special Case batch | This reorganization |
| --- | --- | --- |
| Full backend suite | 509 passed; 6,356 assertions | 519 passed; 6,658 assertions |
| CMS JavaScript suite | 103 passed | 108 passed; 0 failed |
| Full Flutter suite | 178 passed; 2 failed | 179 passed; 1 failed |
| Catalog, case-guide, and fee Flutter tests | Separate prior results | 25 passed; 0 failed |
| Request-option-focused backend tests | New coverage | 10 passed; 280 assertions |
| Vite production build | Passed, size warning | Passed, same warning for JS chunk above 500 kB |
| Whitespace/error check | — | `git diff --check` passed |
| Live browser Add/Edit/Save/Reopen | Earlier work had partial browser coverage | Not performed for this reorganization |

The remaining full-Flutter failure is `test/profile_setup_test.dart` → “small screens with large text scroll without overflow,” with a RenderFlex overflow. This is the previously documented profile-layout failure. Profile implementation/tests were not changed in this reorganization. The previously intermittent mascot test passed in this full run.

Backend checks cover both record types: seven-step Add/Edit navigation; initial-path reuse; new options; repeated edits retaining IDs; wrong-parent references; populated-option deletion protection; empty-option deletion; independent checklists and procedures; shared/selected/none fee and office scopes; scoped saves preserving other options; stale-save rejection; error-input recovery; Back/Continue/Review/reopening context; existing catalog compatibility; Special Case Guide persistence; API projection; and prior regressions.

Six synthetic representative workflows still pass through CMS HTTP save/reopen and the Documents API: PSA Birth Certificate/copy, NBI Clearance/renewal, Business Permit/new, DFA Apostille/authentication, PhilHealth MDR/retrieval, and Seafarer’s Record Book/replacement in Informational Only mode. Flutter tests consume the saved contract fixtures for these six, verify resident rendering, and verify channel switching changes applicable fees/guide/offices without unrelated legacy content. Dedicated case tests verify independence and no Inventory/readiness calls. These are automated checks, not six manual browser workflows against a running app.

Browser control had reported missing Computer Use permission. As authorized, implementation continued from the supplied screenshot, Blade templates, JavaScript, controllers, and automated HTTP/widget tests. Chrome retries were not continued. This report does not claim browser tests passed or provide an unverified after screenshot. Actual card spacing, keyboard/desktop interaction, browser-native unsaved-change dialogs, and a complete manual Add → Save → Reopen → Edit → Review → API → Flutter workflow remain for manual verification. Simultaneous editing against MySQL was not manually tested.

Temporary logs:

- `/tmp/idireksyon-seven-step-backend.log`
- `/tmp/idireksyon-seven-step-option-tests.log`
- `/tmp/idireksyon-seven-step-js.log`
- `/tmp/idireksyon-seven-step-build.log`
- `/tmp/idireksyon-seven-step-flutter.log`
- `/tmp/idireksyon-seven-step-flutter-focused.log`

## Files created for this UX batch

Paths are relative to the backend repository. Several earlier-batch files remain untracked; the list below distinguishes new UX files from modifications to those existing working-tree files.

| File | Purpose and important code |
| --- | --- |
| `resources/js/request-option-editor.js` | Display-name suggestions, preserved custom names/channel codes, form snapshots, unsaved-change guards for the option form and Fees/Offices |
| `resources/views/admin/catalog/request-option.blade.php` | Dedicated Add/Edit page with the correct create/edit-wizard return context |
| `resources/views/admin/catalog/partials/request-option-form.blade.php` | Four initial fields, collapsed advanced metadata, reusable associations, errors, Cancel/Save |
| `tests/Feature/RequestOptionWizardTest.php` | Ten parameterized HTTP/database cases covering both catalog types, selection, identities, associations, and safety |
| `tests/Unit/request-option-editor.test.mjs` | Five tests for suggestions, name preservation, existing method codes, dirty-state/cancel guards, and blocked-submit recovery |
| `docs/seven-step-request-options-2026-10-29.md` | This implementation and verification report |

## Files modified in this UX batch

| File | What changed, why, and connection |
| --- | --- |
| `app/Http/Controllers/Admin/GovernmentIdCreationWizardController.php` | Seven-step order, Basic → Channels transition, remembered selection, scoped content loading/saving through the shared concern |
| `app/Http/Controllers/Admin/DocumentCreationWizardController.php` | Same transitions and behavior using the shared Government ID step definition and views |
| `app/Http/Controllers/Admin/Concerns/EditsCatalogWizard.php` | Record-scoped session selection and fee/office save helpers that retain other options' reusable records |
| `app/Http/Controllers/Admin/CatalogChannelController.php` | Owned Add/Edit/Delete endpoints, advanced-field validation, existing-ID updates, safe deletion, return to Step 2 |
| `routes/web.php` | Request-option editor and delete routes for both parent types under existing CMS access/revision middleware |
| `resources/js/app.js` | Register the new Alpine editors/guards |
| `resources/views/admin/catalog/partials/channels.blade.php` | Replace the long inline editor with cards and links to related content in the correct wizard steps |
| `resources/views/admin/catalog/partials/channel-context.blade.php` | Compact selector, Step 2 management link, shared versus option-specific explanation |
| `resources/views/admin/government_ids/wizard/page.blade.php` | Shared seven-step layout, Step 2-only option management, filtered content, preserved context, independent case forms |
| `resources/views/admin/government_ids/wizard/review.blade.php` | Request-option summary, explicit record totals, selected-option return links |
| `resources/views/admin/catalog/partials/guide-editor.blade.php` | Stable application-steps anchor for the related-content link |
| `resources/views/admin/catalog/partials/special-cases.blade.php` | Label Special Cases / Additional Help; independent functionality retained |
| `tests/Feature/GovernmentIdCreationWizardTest.php` | Seven-step order/transitions and single-option checklist expectations, existing preservation checks retained |
| `tests/Feature/DocumentCreationWizardTest.php` | Equivalent Document expectations and contextual review links |
| `tests/Feature/CatalogEditWizardTest.php` | Step 2 transition and explicit selection for legacy scenarios; existing save/ID/compatibility assertions retained |
| `tests/Feature/CatalogEncodingTest.php` | Step 2 channel redirects/management, compact selectors, seven-step traversal, six CMS/API workflows retained |
| `tests/Feature/GovernmentIdApplicationGuideManagementTest.php` | UI fixtures create an existing Step 2 option before opening its normal guide editor |

No authored files were deleted. Vite regenerated its normal ignored `public/build/manifest.json` and hashed assets. There are no schema changes for this reorganization, no API-format changes, and no RoadmapPlanner, Inventory, Readiness, authentication, or Flutter implementation changes in this batch.

## Manual review and remaining issues

For read-only visual review: open `http://127.0.0.1:8000/admin/government-ids` (or `/admin/documents`), choose Edit on an existing entry, and open Step 2 — Transactions & Request Channels. Inspect Edit Option and More Details & Settings without saving to the real catalog. Use the other steps to check the compact context selector and Step 5 for separate Special Case Guides.

Use the existing disposable environment at port 8877 for any eventual manual test writes, after confirming its isolated database configuration. This batch did not write manual fixtures there or alter the real database to make testing possible.

Remaining items are manual browser acceptance, the known unrelated Flutter profile overflow, and end-to-end manual testing of one complete document in the isolated environment. No bulk encoding readiness sign-off is claimed. Stop here for the user's manual approval.
