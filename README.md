# IDireksyon Backend

Laravel backend and researcher CMS for IDireksyon, a government ID guidance application for Santa Maria, Bulacan residents. The Flutter app lives in a separate repository.

## Current implementation

- Staff login, CMS access controls, admin accounts, and audit logs.
- Government ID and Document directories, with reusable issuing agencies.
- Structured ID eligibility fields for age, citizenship, and residency, plus additional notes.
- Applicant/application checklists with specific items, accepted-item choices, conditional requirements, submission details, and Government ID or Document references.
- Application Guide steps and rich-text information blocks per applicant/application scenario.
- Multiple fees, office records, weekly schedules, and ID-to-office links.
- Resident authentication, profile endpoints, and Government ID directory/detail APIs.
- CMS feedback through a shared toast and field-level validation.

Requirements store references needed for future sequencing. The resident requirements projection currently displays those records; it does not calculate readiness or evaluate dependencies.

## Still unfinished

- Resident checklist persistence and document/ID inventory integration.
- Automated eligibility and conditional-requirement evaluation.
- Readiness calculation and smart sequencing, including alternatives and cycle handling.
- Location-based office ranking/navigation and complete resident integration for the remaining CMS sections.

## Where the code lives

| Folder | Responsibility |
| --- | --- |
| app/Http/Controllers/Admin | CMS pages and write actions |
| app/Http/Controllers/Api | Resident authentication and ID API endpoints |
| app/Http/Requests/Admin | Validation for offices and structured checklists |
| app/Models | Records and their relationships |
| app/Services/GovernmentIdApplicationGuide.php | Guide validation, presentation, and persistence |
| app/Support | Rich-text handling and resident requirements projection |
| app/Observers | CMS audit events |
| resources/views/admin | Blade CMS screens and shared form partials |
| resources/js | Alpine editors and shared notifications |
| resources/css/app.css | CMS styles |
| routes/web.php | CMS routes |
| routes/api.php | Resident API routes |
| database/migrations | Database history; retain existing migrations |
| tests/Feature | Backend behavior and integration checks |
| docs | Design notes and integration documentation |

## Editing and saving

Government ID checklists save through their own endpoints. Fees, office selections, and Application Guide changes are applied to the main ID form and persist when the ID is saved. Creating an agency or office creates that directory record separately.

The ID Sources/Website section is currently removed from the create, edit, and view screens. Existing database values and API fields remain intact.

## Local development

The project uses PHP 8.2+, Laravel 12, Sanctum, Blade, Alpine, Tailwind, Vite, and Tiptap. Dependency versions are locked in composer.lock and package-lock.json.

Use your local .env for database and application configuration. Never commit it or database backups. Keep existing databases and migrations when continuing development; do not reset research data to prepare a code checkpoint.

For an already configured checkout:

~~~bash
php artisan serve
~~~

In a separate terminal, use the asset development server while editing CSS/JavaScript:

~~~bash
npm run dev
~~~

Or build assets after CSS/JavaScript changes:

~~~bash
npm run build
~~~

Default DatabaseSeeder intentionally leaves research content untouched. Legacy catalog seeders and explicit import/cleanup commands still exist; they are not part of normal CMS data entry.

## Checks before a checkpoint

~~~bash
php artisan test
npm run build
git diff --check
git status --short --branch
~~~

The October 2026 cleanup updated the older catalog tests to the current CMS contract. These edits still require a full test run; they are not evidence that the suite passes.

- AdminCatalogTest covers current ID/Document forms, validation, search, sorting, and preservation of saved source data.
- CatalogFoundationTest covers agency creation/reuse, access control, and removal of retired reference-directory routes. Office schedule and relationship coverage remains in the dedicated Office test suites.
- CatalogRulesTest covers reference deletion protection, checklist-specific instructions, resident reference output, and current fee behavior. Accepted-item counts, alternative ways, ownership protection, and atomic checklist saves remain covered by the dedicated GovernmentId checklist/requirement tests.
- AdminAccessTest, AuthenticationTest, and CmsSimplificationTest follow the current researcher dashboard and office permissions while retaining administration restrictions.

Older expectations for separate Level/Category/Barangay directories, Document-owned structured checklists, a reviewed-publication gate, and alternative fee groups do not describe the current implementation. They were removed from these tests rather than restored as features. Dependency references are stored, but dependency evaluation, cycle handling, and readiness remain unfinished; these tests do not certify them.

CatalogFoundationSeeder and its GovernmentIdSeeder wrapper still reference the older catalog model and need review before use. They are not called by the default DatabaseSeeder. Do not use those legacy seeders to prepare a checkpoint.

Run the suite and review failures before treating a checkpoint as verified. Review untracked files too: git diff --stat does not include their contents.

## Project references

- [Working rules](AGENTS.md)
- [Project context](IDIREKSYON_CONTEXT.md)
- [Resident requirements API integration](docs/resident-requirements-integration.md)

Older planning reports in docs may describe superseded designs. Use the active routes, models, and tests when checking current behavior.
