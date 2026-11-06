# Final CMS UI cleanup — 29 October 2026

Focused presentation cleanup for Government IDs and Documents. No new CMS features, database/schema/API changes, real-database writes, commits, pushes, or encoding.

## Result

- Steps 3–6 have one compact **Editing Request Method:** row and a subtle **Manage Request Methods** link. A single configured method uses a text label. Multiple configured methods use a selector; a selected legacy scenario remains explicitly marked when shown.
- With no configured method, the context contains only **Create a request method in Step 2 first.** and the subtle management link. Repetitive setup banners, association explanations, the redundant Requirements heading, and the Guide's incomplete-requirements banner were removed.
- Step 2 retains cards and opens forms only through Add/Edit. Method labels include provider context and relevant applicant qualifiers. Unconfigured scenarios are labeled **Not configured** rather than presented as complete methods.
- Government ID acquisition no longer requires choosing first-time application. New method forms carry the existing `new` transaction implicitly and suggest names such as **In Person** or **Online**. Existing saved transaction meanings, custom display names, applicant conditions, and IDs remain intact; existing non-new processes are retained in the form without offering new transaction choices.
- Documents retain the transaction selector, including certified copies and retrieval. Their method labels retain transaction distinctions. The Government ID default is not applied to Document forms.
- Navigation, selected IDs, content scoping, saves, revision protection, and independent Special Case Guides retain their existing behavior. No sequencing, eligibility, Inventory, Readiness, or Flutter source changes.

## Verification

| Check | Result |
| --- | --- |
| Full backend suite, SQLite `:memory:` | 529 passed; 7,416 assertions (previously 519 tests) |
| JavaScript suite | 109 passed; 0 failed (previously 108) |
| Vite build | Passed; existing JS chunk-size warning remains |
| `git diff --check` | Passed |
| Browser interaction | Not performed; previously unavailable Computer Use permission, no repeated retries |
| Flutter | Not rerun: cleanup affects CMS presentation only; Flutter code/API contracts unchanged |

Ten added backend cases cover both catalog types and Add/Edit pages, no/one/multiple configured methods, unconfigured legacy selection, applicant conditions, document transaction distinctions, implicit Government ID form values, unchanged saved records, and unchanged API output after page views. Existing method-switching and Save/Back/Continue/Reopen tests remain in the passing suite. JavaScript coverage adds Government ID method-only suggestions while preserving Document transaction names and saved names.

The backend tests explicitly verify their SQLite in-memory configuration. No real-catalog integrity snapshot is claimed; preservation assertions use synthetic isolated records. Actual browser spacing, dropdown interaction, and unsaved-change dialogs still need manual review.

## Files created

| Path | Purpose / important code |
| --- | --- |
| `app/Support/RequestMethodLabel.php` | CMS-only configured-state check and human-friendly labels; no persistence or resident API use |
| `tests/Feature/CatalogWizardUiCleanupTest.php` | Ten parameterized UI, record-preservation, and API-stability cases |
| `docs/cms-ui-cleanup-2026-10-29.md` | This report |

## Files modified

| Path | Change and connection |
| --- | --- |
| `resources/views/admin/catalog/partials/channel-context.blade.php` | Compact context shared by Steps 3–6; uses the label helper and existing selected-path URLs |
| `resources/views/admin/catalog/partials/channels.blade.php` | Clean Step 2 card labels, explicit unconfigured state, Request Method terminology; existing links/actions retained |
| `resources/views/admin/catalog/request-option.blade.php` | Add/Edit Request Method page titles |
| `resources/views/admin/catalog/partials/request-option-form.blade.php` | Implicit Government ID transaction; Document transaction selector retained; existing values and advanced qualifiers preserved |
| `resources/views/admin/government_ids/wizard/page.blade.php` | Shared wizard removes redundant heading and banners; selected content/editors retained |
| `resources/js/request-option-editor.js` | Government ID method-only suggestions; saved names and Document behavior retained |
| `tests/Feature/RequestOptionWizardTest.php` | New terminology and type-specific form expectations; existing identity/navigation/association checks retained |
| `tests/Feature/CatalogEncodingTest.php` | Short legacy setup message and renamed management controls; CMS/API checks retained |
| `tests/Feature/CatalogEditWizardTest.php` | Short legacy setup message; preservation and saved-content assertions retained |
| `tests/Feature/GovernmentIdCreationWizardTest.php` | Management-link terminology and expected removal of the Guide banner |
| `tests/Feature/DocumentCreationWizardTest.php` | Expected removal of the Guide banner; Document save checks retained |
| `tests/Unit/request-option-editor.test.mjs` | ID-versus-Document suggestion regression test |

No authored files deleted. Vite regenerated its ignored build manifest and hashed assets. Existing uncommitted CMS/Special Case work and the unrelated workspace file were preserved.

Cleanup complete. Stop for manual review; no further development or encoding authorized by this report.
