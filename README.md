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

## Unified server

Run `..\start-system.cmd` from this folder (or `.\start-system.cmd` from the parent folder) to build resident web and CMS assets and serve both with the API on port 8000. Resident web is `/app/`; CMS is `/admin`. See the frontend README for phone and Google Maps key configuration. Set `GOOGLE_MAPS_SERVER_API_KEY` in this backend?s `.env` for Places/Routes. No database reset or seed is run.

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

Default DatabaseSeeder intentionally leaves research content untouched. Enter documents through Documents & Certifications; Documents and Government IDs share their Add/Edit/View layouts and editors for eligibility, requirements, fees, validity, application guides, and linked offices. Document-owned research lives in separate document tables. Add saves all sections in one transaction; on Edit, checklists save through their modal while the other sections use Save Changes. View is read-only and shows only populated sections. Existing document entries are not automatically populated with new research. Research import/cleanup commands are not part of normal CMS data entry. The shared reference-data workflow below is separate.

## Checks before a checkpoint

~~~bash
php artisan test
npm run build
git diff --check
git status --short --branch
~~~

The October 2026 cleanup updated the older catalog tests to the current CMS contract. These edits still require a full test run; they are not evidence that the suite passes.

- DocumentManagementTest and DocumentStructuredContentTest cover the shared document forms, nested saves, ownership protection, rollback, and deletion guards.
- AdminCatalogTest covers current ID/Document forms, validation, search, sorting, and preservation of saved source data.
- CatalogFoundationTest covers agency creation/reuse, access control, and removal of retired reference-directory routes. Office schedule and relationship coverage remains in the dedicated Office test suites.
- CatalogRulesTest covers reference deletion protection, checklist-specific instructions, resident reference output, and current fee behavior. Accepted-item counts, alternative ways, ownership protection, and atomic checklist saves remain covered by the dedicated GovernmentId checklist/requirement tests.
- AdminAccessTest, AuthenticationTest, and CmsSimplificationTest follow the current researcher dashboard and office permissions while retaining administration restrictions.

Older expectations for separate Level/Category/Barangay directories, a reviewed-publication gate, and alternative fee groups do not describe the current implementation. They were removed from these tests rather than restored as features. Dependency references are stored, but dependency evaluation, cycle handling, and readiness remain unfinished; these tests do not certify them.

Obsolete catalog seeders and their old inventory dataset have been removed. Existing migrations and active import/cleanup commands are retained; do not run research imports or destructive cleanup commands as part of normal setup. Use the development reference-data workflow below for teammate setup.

Run the suite and review failures before treating a checkpoint as verified. Review untracked files too: git diff --stat does not include their contents.

## Project references

- [Working rules](AGENTS.md)
- [Project context](IDIREKSYON_CONTEXT.md)
- [Resident requirements API integration](docs/resident-requirements-integration.md)

Older planning reports in docs may describe superseded designs. Use the active routes, models, and tests when checking current behavior.

## Shared DEVELOPMENT reference snapshot

This snapshot is **DEVELOPMENT ONLY, UNVERIFIED, and NOT approved for production**. It intentionally preserves draft offices, incomplete reference records, and both Passport #36 and #50 with their original relationships. It is a shared CMS starting point, not verified government guidance.

`database/data/development-reference-data.json` contains the explicitly authorized 227-record development snapshot (`snapshot_state: exported`). Its JSON, relationships, and reviewed content fingerprint were validated after export. It remains unverified and is not approved for production. Review and manually commit the dataset with its importer/exporter implementation before teammates pull it.

### Teammates: after git pull

Once the real development snapshot is committed, use an already configured **local** checkout with its own local MySQL database (`APP_ENV=local`):

```bash
git pull
php artisan migrate
php artisan db:seed --class=ReferenceDataSeeder
```

The seeder reads only `development-reference-data.json`, displays the development warning, inserts missing rows, and skips identical rows. It preserves IDs, foreign keys, scenario relationships, JSON, and ordering. Conflicting IDs, existing directory names under another ID, missing dependencies, invalid metadata, or user verification references stop the import. All inserts run in one transaction; failures roll back the run. Existing records are never overwritten or deleted. Local creation/update timestamps do not cause conflicts.

Accounts stay separate. Users, passwords, authentication/session/token data, resident profiles/inventories, and audit logs are not selected. `last_verified_by` becomes null. Public reference text, verification dates, service statuses, and source links remain. Text fields still need privacy review because an allowlist cannot recognize every kind of personal information entered into CMS notes.

### Maintainer: preview, authorize, and export

Use only your existing local CMS database configuration. This command validates and reports counts and a content fingerprint without writing a file or changing records:

```bash
php artisan idireksyon:export-reference-data
```

The entire reference catalog is included to preserve cross-ID/document dependencies. There is no selective subset export or automatic content approval gate. Review the candidate content for personal information and confirm that drafts and incomplete entries are acceptable for shared development. The last read-only review found 227 records; the command rechecks live content before writing.

Only after explicit authorization, create the development snapshot using the reviewed fingerprint:

```bash
php artisan idireksyon:export-reference-data --development --write --approve-hash=REVIEWED_SHA256
```

`--development` acknowledges the development-only scope; `--approve-hash` authorizes this exact content for export, **not production publication**. A changed/missing fingerprint or missing development flag stops the write. An existing destination requires an additional interactive confirmation, defaulting to No; noninteractive replacement is refused. Writing uses a temporary file and atomic rename. An optional `--output=/absolute/path/development-candidate.json` changes the destination but retains the same guards.

Format version 2 includes fixed metadata: `purpose: development_only`, `verification_status: unverified`, `production_approved: false`, and an explicit warning. The exporter marks `snapshot_state: exported`; the importer rejects pending placeholders and legacy approved-format files. The fingerprint covers reference content only, excluding creation/update timestamps and local verifier account IDs. Changing the format does not change the content fingerprint.

### Production protection

Imports and exports are restricted to Laravel environments `local` and `testing`. Production, staging, and unknown environments are rejected before snapshot database access, even with `db:seed --force`. There is no production override or production approval workflow. Editing metadata to claim production approval is rejected. Any future production dataset needs a separate explicit approval process and implementation.

Do not run this workflow on Railway production or point a local checkout at a production database. The environment guard uses the configured Laravel environment; it does not independently authenticate whether a database server is production. No Railway connection or deployment is part of this workflow.

Legacy approved-format datasets are not a fallback and are rejected. The development export belongs in `development-reference-data.json`. Default `DatabaseSeeder` remains unchanged.

### Review and manually commit

The exporter never runs Git. Review the resulting JSON and stage only intended files:

```bash
git diff -- database/data/development-reference-data.json
git status --short
git add app/Services/ReferenceData.php app/Console/Commands/ExportReferenceData.php database/seeders/ReferenceDataSeeder.php database/data/development-reference-data.json tests/Feature/ReferenceDataTest.php README.md
git diff --cached
git commit -m "Share development-only CMS reference snapshot"
```

Do not stage `.env`, database backups, private content, or unrelated changes. Keep the local database intact. Run imports without concurrent CMS edits/imports. A later shared-content revision conflicts with a differing local row by design and needs separately reviewed reconciliation; do not delete records or change IDs to force it through.

### Isolated checks

Focused tests create SQLite in-memory databases and run ordinary migrations, without `migrate:fresh` or local MySQL access:

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= APP_ENV=testing vendor/bin/phpunit tests/Feature/ReferenceDataTest.php --do-not-cache-result
```
