# Resident Requirements integration

This change connects the existing structured CMS requirements to the existing Flutter Requirements tab. It does not evaluate readiness, conditions, ownership, or dependencies.

## Current flow

Previously, the Flutter directory and detail screen used static sample IDs and requirements. ApiService only had a health request. Laravel detail returned a readable requirements string, without structured checklists.

Now the directory loads GET /api/government-ids, passes the selected numeric ID to IdDetailsScreen, and detail loads GET /api/government-ids/{id}. Requirement sets are added only to detail. Every previous API field and the data wrapper remain unchanged.

## API contract

The following is the complete shape of the new field inside data, with illustrative Passport-style values. These IDs and values are examples, NOT a captured response from your database. The local API was unreachable during implementation.

```json
{
  "requirement_sets": [
    {
      "id": 10,
      "application_type": "new",
      "application_type_label": "First-Time Application",
      "applicant_type": "adult",
      "applicant_type_label": "Adult",
      "display_label": "Adult • First-Time Application",
      "min_age": 18,
      "max_age": null,
      "groups": [
        {
          "id": 20,
          "title": "Proof of identity",
          "rule": "all",
          "condition_type": "always",
          "condition_label": null,
          "ways": [
            {
              "id": 30,
              "required_count": 1,
              "qualification_type": "unexpired",
              "qualification_scope": "every",
              "qualification_custom": null,
              "qualification_label": "Each selected item must: Be valid / unexpired",
              "items": [
                {
                  "id": 40,
                  "type": "government_id",
                  "government_id_id": 5,
                  "document_id": null,
                  "name": "National ID",
                  "quantity": null,
                  "submission_format": "original_photocopy",
                  "submission_label": "Original + Photocopy",
                  "copies": 1,
                  "instructions": "Bring a clear copy."
                },
                {
                  "id": 41,
                  "type": "government_id",
                  "government_id_id": 8,
                  "document_id": null,
                  "name": "Driver’s License",
                  "quantity": null,
                  "submission_format": "original_photocopy",
                  "submission_label": "Original + Photocopy",
                  "copies": 1,
                  "instructions": null
                }
              ]
            }
          ]
        }
      ]
    }
  ]
}
```

- Application codes remain new, renewal, replacement, custom. No new first_time code.
- All scenarios are returned, including those whose groups are empty. Scenario age boundaries can be null or zero.
- groups are independent requirements. ways within one requirement are alternatives (OR).
- Each way keeps its own required_count and its own items. It never means every accepted option is required unless count equals the number of options.
- Old groups without ways are represented as a single way with id=null. For all, count equals item count; for choose_one, count=1. No database write occurs.
- Existing government_id_id and document_id fields identify referenced records. The resolved name travels in the same response. Custom entries have neither reference ID.
- qualification_scope distinguishes every from at_least_one. qualification_label includes that scope; custom qualification text remains available.
- condition_label describes the condition, or is null for always. Conditions are displayed, never evaluated or hidden based on resident data.
- submission_format and submission_label describe format. copies and quantity stay separate. instructions remain human-readable.
- Arrays follow existing sort_order/id relationships. No CMS audit metadata, recursive referenced models, or guide steps are added.
- Referenced names are eager-loaded; the query-count regression checks that adding more groups/items does not add queries per item.

## API to Flutter mapping

| API value | Resident display |
|---|---|
| display_label | Scenario heading / selector entry |
| groups.title | Requirement heading; unnamed single-item requirements use the item name |
| ways.required_count + items | Required, Choose N accepted items, or Provide all listed items |
| Multiple ways | Option 1 / OR / Option 2 |
| items.name | Accepted item name; no additional lookup |
| qualification_label | Additional item condition including every/at least one scope |
| condition_label | Only if applicable + explanation |
| submission_label + copies | Submission details; Original + 1 photocopy when appropriate |
| quantity | Quantity, distinct from submission copies |
| instructions | Additional instructions |

## Display behavior

- One scenario: show it immediately, no selector.
- Multiple scenarios: one Application and applicant selector updates requirements on the same screen.
- More than four accepted options: expandable list within the requirement card.
- Structured sets exist: show those sets, never also show the old requirements summary.
- Empty selected scenario: explain requirements for that application are not available yet; do not mix in legacy text from other scenarios.
- No structured sets: show nonblank legacy requirements text.
- Neither source: Requirements are not available yet.
- Loading: spinner. Request error: short message with Retry.
- Directory search uses names/issuing agencies from loaded records.
- Real records do not display invented sample readiness percentages.
- Home/Roadmap prototype links currently pass a name only. They show a message to open the ID through ID Directory rather than guessing a database ID. Those features were not edited.
- Cost, Offices, and Guide tabs are still existing prototype content and are not integrated by this change.

## Files created

Backend:
- app/Support/ResidentRequirements.php — read-only, explicit API projection and legacy-group compatibility.
- tests/Feature/GovernmentIdRequirementsApiTest.php — API contract, references, alternatives, counts, conditions, qualifications, submission details, ordering and query-count tests.
- docs/resident-requirements-integration.md — this integration contract and verification guide.

Frontend:
- lib/models/government_id.dart — typed parsing for IDs, scenarios, requirements, ways and items; submission/rule display text.
- lib/features/ids/requirements_tab.dart — compact scenario-aware requirements renderer.
- test/government_id_model_test.dart — parsing, references, counts, nulls, zero ages and submission formatting.
- test/government_id_api_test.dart — endpoint paths, errors, UTF8 and request behavior.
- test/requirements_tab_test.dart — resident rendering, switching, expansion, fallback, retry, navigation and narrow-screen behavior.

## Files modified

Backend:
- app/Http/Controllers/Api/GovernmentIdController.php — eager-load relationships and append requirement_sets to detail only.
- tests/Feature/GovernmentIdApplicationGuideManagementTest.php
- tests/Feature/GovernmentIdApplicationGuideStorageTest.php
- tests/Feature/GovernmentIdChecklistManagementTest.php
- tests/Feature/GovernmentIdRequirementGroupManagementTest.php
- tests/Feature/GovernmentIdRequirementSetTest.php
- tests/Feature/GovernmentIdRequirementWaysTest.php

The six existing tests still compare every legacy field and the full directory response. They exclude the newly intentional requirement_sets field from old full-detail equality assertions; dedicated API tests verify that field.

Frontend:
- lib/services/api_service.dart — directory/detail GETs, data-wrapper parsing, timeout/error handling, optional injected HTTP client, API_BASE_URL build override.
- lib/features/ids/ids_screen.dart — API-backed directory using actual IDs, existing card layout, loading/error/search behavior.
- lib/features/ids/id_details_screen.dart — detail loading by ID, real name/agency/validity, requirements tab integration; no fabricated readiness badge.
- test/ids_layout_test.dart — injected directory fixtures preserve existing dark/light and narrow-screen checks without network calls.

No files deleted. No migrations, schema changes, library installs, CMS redesign, authentication changes or Git operations.

## Verification status

PHP and Flutter tests have NOT been executed by the assistant, following the user's no-terminal rule. Source files were inspected and delimiter balance checked; those checks are not a compiler or runtime test. A read-only HTTP request to localhost:8000 failed to connect, so no actual Passport response or device render was verified.

## Commands for the user

From IDireksyon-backend:

```bash
php artisan test --filter='GovernmentIdRequirementsApiTest|GovernmentIdChecklistManagementTest|GovernmentIdRequirementGroupManagementTest|GovernmentIdRequirementSetManagementTest|GovernmentIdRequirementSetTest|GovernmentIdRequirementWaysTest|GovernmentIdApplicationGuideManagementTest|GovernmentIdApplicationGuideStorageTest'
php artisan serve --host=0.0.0.0 --port=8000
```

With that server running, use another terminal to inspect the actual Passport record shown in the screenshot (ID 34):

```bash
curl -sS -H 'Accept: application/json' http://127.0.0.1:8000/api/government-ids/34
```

If that record was removed, get /api/government-ids and use the numeric ID of the desired Passport record. No import/migration is needed.

From IDireksyon-frontend:

```bash
flutter test test/government_id_model_test.dart test/government_id_api_test.dart test/requirements_tab_test.dart test/ids_layout_test.dart
flutter analyze lib/models/government_id.dart lib/services/api_service.dart lib/features/ids test/government_id_model_test.dart test/government_id_api_test.dart test/requirements_tab_test.dart test/ids_layout_test.dart
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api
```

10.0.2.2 is the existing Android emulator setting. For iOS simulator on the same Mac use http://127.0.0.1:8000/api. For a physical device, use the Mac's LAN address reachable from the device. Restart the app when changing dart-define.

In the app: open ID Directory → select the actual Passport record → Requirements. Compare each scenario and alternative with CMS. Expand the long accepted-ID list; check conditions, copies and qualifications. A CMS example imported as custom items will correctly retain those custom references; this integration does not convert them into ID/document links.
