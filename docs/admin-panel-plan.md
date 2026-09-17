# IDireksyon CMS — current working plan

Maintain researched Government ID and Document information using the existing Blade, Tailwind and Alpine interface. Keep the current colors, layout, components and spacing. The CMS now captures reusable directory content and requirement rules. Resident API integration and the roadmap engine remain separate work.

## Access

| Area | Super Admin | Researcher |
| --- | --- | --- |
| Dashboard | View | No access |
| Government IDs | View / create / edit / safe delete | Same |
| Documents | View / create / edit / safe delete | Same |
| Requirements within ID/document forms | Maintain | Maintain |
| Levels, categories, agencies, barangays, offices and hours | Maintain | Select existing records inside content forms only |
| Admin Users | Create / edit / assign role / activate / deactivate / manually reset passwords | No access |
| Audit Logs | Read-only | No access |
| Change Password / Log Out | Own account | Own account |

Gates protect backend routes as well as navigation. Researchers land on Government IDs after sign-in. The only CMS roles are Super Admin and Researcher; any historical resident accounts remain stored but cannot sign into the CMS. Existing unassigned usernames are backfilled as `user_<account number>` and can be changed by a Super Admin.

## Login and recovery

- Username + password, with case-insensitive usernames stored in lowercase.
- No public registration, email password reset, verification or public recovery routes.
- Generic invalid-credentials message, five failed attempts per username/IP per minute, session regeneration and CSRF protection.
- Super Admin handles password resets through **Admin Users → Edit account → New password**.
- Passwords use Laravel hashing and are never returned in account forms or audit records.
- Accounts are deactivated rather than deleted. At least one active Super Admin must remain; another Super Admin must change your own access.

The local development account is `VeryVeryAdmin`. Its password is the one supplied for local setup. `LocalSuperAdminSeeder` explicitly creates/updates this account and is restricted to local/testing environments; it is not run by the general content seeder. Rerunning it resets that account's development password. Do not run it as routine data maintenance.

## Content screens

| Screen | Organization |
| --- | --- |
| Government IDs / Documents | Search plus Name, Level, Category, Agency, Research Status, Last Updated, Actions |
| Create / Edit | Existing fields grouped into Basic Information, Application Details, Sources & Research, Offices & Availability |
| Requirements | Within each ID/document: groups (all / any one / at least N), linked prerequisites, other checklist items, source notes |
| Fee breakdown | Within each ID/document: required, optional, or mutually exclusive alternatives; blank amount means unknown |
| Reference directories | Essential named columns; full details remain in Edit |

Government IDs and Documents remain separate tables. Reference tables, office hours and office/service relationships remain intact. Unknown research stays empty; research status and issuance availability stay separate. No processing-time or other speculative fields were added. Existing dependency checks continue to prevent unsafe deletion.

## Audit Logs

`content_change_logs` records new CMS model creates/edits/deletes, changed field names, who acted and when. It also records content-office link changes, requirement additions/removals and password-change events without storing password values, hashes or tokens. The screen has search and pagination, with no edit/delete routes. It is not a historical reconstruction, a full before/after snapshot or a database-wide SQL audit.

## Database changes in this cleanup

Migration `2026_09_15_050000_add_cms_usernames_and_audit_logs` adds unique usernames to existing users and the audit table. No directory tables were combined or reset. Earlier role/active-status fields are reused.

## Later decisions

Agency-specific access and applicant variants remain future increments. Do not infer availability or a usable sequencing rule merely from a CMS inventory entry.

## Directory and sequencing preparation — September 17

Same pages, sidebar, roles and styling. No per-ID forms or per-ID sequencing code.

| Storage | Purpose / CMS location |
| --- | --- |
| `government_ids`, `documents` | Still separate. `is_published` controls directory approval; `requirements_reviewed` records explicit rule review. Both default false for existing records. |
| `requirement_groups` | Owned by one ID or document. Every group is required; inside it, all / any one / at least N options must be satisfied. Managed under Requirements. |
| Existing `requirements` and pivots | Existing rows preserved. Optional group and obtain-first flag added. Referenced IDs/documents use stable relationships; names are display text. |
| `catalog_fees` | Owned by one ID or document. PHP amount may be unknown. Required, optional and alternative sets stay separate. Existing fee notes are preserved. |

Migration: `2026_09_17_010000_structure_catalog_rules_and_fees`. No content seeding, reset, or conversion of legacy free text into guessed rules.

### Maintainer steps

1. Save the normal ID/document details, steps, offices and official source.
2. Under Requirements, add named groups and assign each requirement to one. Use **obtain first** only for a researched linked prerequisite.
3. Add fee rows if researched. Use one set name for mutually exclusive alternatives; optional charges are not part of a mandatory total.
4. Verify research status and availability. Approve directory use separately from confirming the complete requirement rules, then save general changes.

Save general changes before saving the separate requirement/group/fee forms. Rule edits clear review. A group with attached requirements cannot be deleted until its options are moved or removed. Edits to legacy shared requirements create an application-specific copy so other entries keep their notes. Relationship routes are scoped to their owning entry and keep existing role authorization.

### Integration contract and limits

- `GovernmentId::forDirectory()` / `Document::forDirectory()` select explicitly approved, verified, sourced, non-inactive entries. A legacy/unavailable entry may be displayed for reference; it is not automatically eligible for a new application.
- `CatalogDefinition::rules($record)` returns AND groups containing ALL / ANY / at-least-N options with stable references and obtain-first flags. It refuses unpublished, unavailable, unreviewed or structurally incomplete entries.
- Conditional applicability is stored as notes and blocks automated rule approval for now. Do not turn conditions such as minor/adult or first-time/renewal into universal prerequisites.
- Existing ungrouped requirements stay visible in the CMS but cannot pass rule review. An empty requirement set is never inferred to mean none; it needs explicit sourced confirmation.
- `CatalogDefinition::feeChoices($record)` separates required/optional/alternative rows. It does not fabricate a total or turn an unknown amount into zero.
- These are backend content contracts, not public endpoints. Flutter still needs API integration. The future engine must check each dependency's availability/review, user-owned documents, alternative paths and cycles. It must distinguish document readiness from journey progress and never sum mutually exclusive fees.
- Step instructions remain the existing ordered JSON; office links/hours and source fields are reused. No application variants, resident data models or extra analytics added.

