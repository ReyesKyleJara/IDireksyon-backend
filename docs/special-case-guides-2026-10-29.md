# Dedicated special-case application guides

29 October 2026 · Asia/Manila. Extension of the existing uncommitted CMS completion batch.

**Ready for your manual feature testing with synthetic content. Not approved for bulk encoding, commit, push, or deployment.** The actual isolated CMS → API → running Flutter flow was completed. The full Flutter suite still has unrelated failures, and office publication remains unavailable through the current office CMS.

## Storage and independence

Each item stores `research_metadata.special_cases` as an ordered JSON list. Each guide has a stable UUID, title, short description, action label, editable assistance-card title/description, introduction, Before You Begin, requirement groups, application steps/blocks, selected fee IDs, selected office IDs, Important Notes, sources, and its own draft/research/reviewed status.

No new migration or database table was introduced. This uses the JSON column already added by the completion batch. The real development database was not migrated, reset, or seeded.

Requirements reuse the existing checklist editor, validation, item types, conditions, alternatives, and display formatting. Steps reuse the existing guide editor, rich-text validation, links, and ordering. The JSON stores case-specific content rather than inserting it into normal requirement-set or guide-step tables. Existing fees and offices are referenced, not copied into new catalog records.

The read-only API projects these guides under `data.special_cases`, independently of `data.request_paths`. It resolves requirement names and selected fee/office content using the existing infrastructure. Draft/inactive offices are omitted from resident projections while their stored associations remain intact. The same filtering also applies to the completion batch's normal request-path projection.

Special-case requirements never become prerequisite edges. The RoadmapPlanner, Inventory matching, and Requirements Readiness calculations are unchanged. The resident assistance checklist does not fetch Inventory, calculate readiness, or mark completion. PSA Birth Certificate remains one catalog item. Case guides are opened voluntarily.

Legacy `request_channel.troubleshooting` notes remain intact and readable. They are not automatically converted, deleted, or folded into the new guides. Basic Info saves retain separately managed case content. Existing revision fingerprints include the parent JSON and protect case saves/actions from stale active forms.

## CMS editor

The six-step wizard remains. Application Guide now has a **Special cases / troubleshooting** section for Add/Edit/Delete and moving guides up/down. A dedicated editor opens within CMS with a Back link to the parent Application Guide; this is not a seventh wizard step.

All sections save together using **Save special case guide**. Requirements and steps reuse their existing editors. Optional sources, notes, introductory text, and fee/office associations are editable. Reviewed guides require dated sources. Case IDs and referenced entries are checked against their parent. A Basic Info save cannot replace a case list accidentally.

Actual browser testing found and fixed two editor integration problems: the embedded checklist must not announce a normal guide scenario, and validation recovery must replace the single case draft rather than append another scenario. Regression tests cover both, plus serialization of unchanged steps so editing a description does not erase them.

## Flutter presentation

Within the parent's Guide, the CMS-defined normal request choices appear under **Application Guide / How do I request it?**, followed by separate assistance cards. Clicking a card opens a complete guide screen with applicable sections only:

- Title, description, research status, and optional introduction
- Before You Begin
- Read-only case requirements
- Step-by-Step Application Guide
- Case-selected Fees and Where to Apply
- Important Notes and Official Sources
- Back to the parent catalog item

Normal channel requirements, fees, instructions, and offices do not flow into a special guide. Changing channels does not change the case list. Back navigation retains the parent screen. Informational Only records retain stored case data without offering acquisition guides. Existing short troubleshooting notes keep their previous presentation.

The approved delayed-registration assistance wording appears only in saved synthetic fixtures. There is no hardcoded Birth Certificate rule. The fixture Before You Begin distinguishes a missing copy, a local record without a PSA record, and a birth never registered; it does not send all three situations automatically into delayed registration. No real government procedure, fee, address, or processing timeline was encoded or asserted.

## Verification

| Check | Result |
| --- | --- |
| Full backend suite | **509 passed; 6,356 assertions** |
| Full CMS JavaScript suite | **103 passed; 0 failed** |
| Dedicated Flutter regression file | **5 passed** |
| Final full Flutter suite | **178 passed; 2 failed** |
| Flutter static analysis | **No issues found** |
| CMS Vite build | **Passed**; output-size warning above 500 kB remains |
| Flutter web build | **Passed**, against the isolated local API |

The final Flutter failures are the known profile setup large-text overflow and the previously observed intermittent mascot pose/location test. The isolated mascot rerun passed all nine tests. Neither related application implementation was changed. Existing sequencing, Inventory, and readiness tests were not edited for this extension; they ran in the full suites. The new tests add cases without weakening their assertions.

Focused coverage includes Documents and Government IDs, independent persistence, source validation, unsafe URLs, invalid JSON, stale saves with retained input, wrong-parent references, selected fees/offices, draft-office hiding, empty sections, multiple cases, delete/reorder, long instructions, typed existing requirement references, step order, Informational Only, narrow screens/large text, and no Inventory/readiness calls from assistance.

A captured API fixture comes from an isolated backend CMS test and is consumed by Flutter tests. It includes an explicitly synthetic active office to test rendering of applicable office content. This is test-only model data, not an office published through the CMS.

### Actual browser verification completed

Only the existing disposable database `/tmp/idireksyon-encoding-audit.sqlite` was used for browser writes.

1. Created **Delayed Registration of Birth** under `AUDIT — PSA Birth Certificate` through the CMS.
2. Entered the approved assistance title, description, and action label; marked explanatory/test content clearly synthetic.
3. Added a case-only requirement and two application steps using the shared editors.
4. Added an undated source in Draft without claiming procedural verification.
5. Saved, reopened, edited, and saved again. The requirement, both steps, card wording, notes, and source persisted.
6. Attempted Reviewed status with the undated source. Validation rejected it and restored the complete draft. Correcting to Draft then saved successfully.
7. Created and linked a draft-only synthetic office through existing office UI, selected it in the case, and confirmed the selection survived reopening. The API correctly omitted its unpublished details.
8. Kept the normal copy guide and legacy troubleshooting note. The disposable normal paths were labeled In Person and Online for presentation testing; the case stayed separate.
9. Read `/api/documents/1`: two normal paths, one dedicated case, one case requirement group, two case steps, one saved office reference, and zero exposed unpublished offices.
10. Opened the running Flutter app, navigated ID Directory → Documents & Certifications → the fixture → Guide. Verified In Person/Online followed by the assistance card and approved button text.
11. Opened the dedicated guide and verified its case-only content, research warning, sources, and omission of unpublished offices. Returned to the parent, switched to Online, and opened the same unchanged case content again.

Final read-only disposable-database check: SQLite integrity `ok`, zero foreign-key violations, one document, zero Government IDs, two normal requirement sets, one normal requirement group, one normal database guide step, one draft office, and one JSON case containing two independent steps. Case content did not become normal checklist/step rows.

Manual desktop browser behavior above is verified. Manual narrow-viewport emulation produced inconsistent scaling/clipping and is **inconclusive**, not counted as passed. The temporary override was reset and its test tab closed. Automated 320-pixel/large-text checks passed. Browser Gov-ID compatibility, multiple-case deletion/reordering, external source-link opening, and simultaneous MySQL editing were not manually executed; relevant data/logic paths have automated coverage.

Screenshots are local temporary artifacts: `/tmp/idireksyon-special-case-cms.png`, `/tmp/idireksyon-special-case-resident-guide.png`, and `/tmp/idireksyon-special-case-resident-options.png`.

## Files created

Backend-relative paths:

| File | Purpose / important addition |
| --- | --- |
| app/Services/CatalogSpecialCases.php | Validate and project JSON-only case guides with owned references |
| app/Http/Controllers/Admin/CatalogSpecialCaseController.php | Typed parent binding, independent edit/save, delete and ordering actions |
| resources/views/admin/catalog/special-case.blade.php | Dedicated CMS form; reusable editors and one combined save |
| resources/views/admin/catalog/partials/special-cases.blade.php | Parent Guide management cards |
| resources/views/admin/catalog/partials/guide-editor.blade.php | Extracted shared application-step editor; normal/case-specific save instructions |
| tests/Feature/CatalogSpecialCaseTest.php | Six backend regression cases, including both catalog types |
| tests/Unit/special-case-editor.test.mjs | Embedded serialization, event isolation, and validation recovery |
| docs/special-case-guides-2026-10-29.md | This report |

Frontend-relative paths:

| File | Purpose / important addition |
| --- | --- |
| test/special_case_guide_test.dart | Five dedicated-guide rendering/navigation/safety/responsive tests |
| test/fixtures/catalog/special-case.json | API contract captured from an isolated backend CMS test |

## Files modified

Backend-relative paths:

| File | What changed and why |
| --- | --- |
| routes/web.php | Authenticated typed-parent case routes under existing CMS/revision middleware |
| app/Services/CatalogEncoding.php | Add independent API cases; protect referenced fee/office removal; omit unpublished offices |
| app/Support/ResidentRequirements.php | Reuse in-memory display models for JSON groups; batch linked-name reads without persistence |
| app/Http/Controllers/Admin/Concerns/EditsCatalogWizard.php | Retain cases during wizard Basic Info saves |
| app/Http/Controllers/Admin/Concerns/PreparesCatalogEntry.php | Retain cases during ordinary catalog-form validation/saves |
| resources/views/admin/government_ids/wizard/page.blade.php | Include separate case management and extracted shared step editor |
| resources/views/admin/catalog/partials/channels.blade.php | Distinguish quick channel notes from full case guides |
| resources/views/admin/catalog/partials/encoding-summary.blade.php | Read-only saved case summary in parent Details/Review |
| resources/views/admin/government_ids/partials/checklists.blade.php | Configure embedded case mode |
| resources/views/admin/government_ids/partials/requirements-preview.blade.php | Accurate case-save instructions |
| resources/views/admin/government_ids/partials/application-guide-step-editor.blade.php | Case-scoped reference wording and save instructions |
| resources/js/government-id-checklists.js | Embedded draft serialization and event isolation; existing normal flow retained |
| resources/js/government-id-guide.js | Opt-in full single-guide serialization and safe validation restoration |
| tests/Feature/CatalogEncodingTest.php | Synthetic active-office fixtures for resident visibility rules |
| tests/Feature/CatalogEditWizardTest.php | Explicit channel selection after the previously approved one-option UI; preservation assertions retained |

Frontend-relative paths:

| File | What changed and why |
| --- | --- |
| lib/features/ids/id_details_screen.dart | Assistance cards, independent guide screen, shared content rendering, parent Back navigation |
| lib/features/ids/requirements_tab.dart | Reusable assistance-only display without Inventory/readiness calls; formatting/braces only in existing logic |
| lib/features/ids/fees_tab.dart | Embedded rendering option for the existing fee component |

Vite regenerated `public/build/manifest.json`, `public/build/assets/app-BQKd03ug.css`, and `public/build/assets/app-BA4-iKrv.js`. Flutter generated its normal caches and the temporary web build. No authored files were deleted. No new library was installed. The unrelated untracked workspace file and previous batch changes were preserved.

## Known limitations and manual review

- The current office CMS accepts Draft/Inactive offices and has no activation/publication flow. Such office references can be prepared and saved, but resident **Where to Apply** stays hidden until an office has an allowed active state. This task does not add office publication or mark any real office verified.
- The full Flutter suite is not green: profile large-text overflow and intermittent mascot failure remain outside this feature.
- Manual narrow-browser verification remains inconclusive. No claim of mobile-browser sign-off is made.
- Government instructions for delayed registration still need verified official/local research. All entered browser content is a synthetic draft.

Open the isolated parent CMS: <http://127.0.0.1:8877/admin/documents/1/edit/guide?path=2>. Its Special cases / troubleshooting card opens the saved guide. The saved case editor and running resident review tab are left open. Resident base URL: <http://127.0.0.1:8878/#/home>; if reopening from Home, use ID Directory → Documents & Certifications → AUDIT — PSA Birth Certificate → Guide.

No commit, push, bulk encoding, real-catalog migration, or production deployment was performed. Stop here for personal review.
