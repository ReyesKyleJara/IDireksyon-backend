# National ID source review — 2026-10-11

Original supplied text: national-id-supplied.txt. Structured data: national-id-import.json.

- Supporting documents and basic registration flow were compared with PSA pages. PSA pages may return HTTP 403; search-index copies were available for supporting documents, registration, revised IRR, and advisories.
- Birth certificate AND a qualifying government-issued ID is one two-document route. Passport, UMID, and LTO documents are alternative routes. Secondary routes apply when no primary document is available. All checklist rows are individual records/items.
- Requirement names use custom items intentionally: this import does not create directory placeholders or claim inventory dependency links.
- Resident aliens: revised IRR specifies ACR/ACR ID and a sworn certification of more than 180 aggregated days during the year before application, plus any further PSA-required proof.
- Digital access uses https://national-id.gov.ph/ or eGovPH, demographic information, and facial verification. The supplied mandatory TRN workflow was not used. No instant-availability guarantee is encoded.
- PSA states registration, paper-format claiming, and demographic updating are free. Replacement amounts in the supplied text were not verified and are not seeded as fee rows.
- Lifetime/one-year validity and mandatory recapture at ages 5/18 were not independently confirmed in this review. They remain in the original archive, not structured rules.
- Listed local/mall centers, hours, no-appointment claims, and current delivery arrangements were not confirmed. Existing office links remain intact; no new offices or schedules are created.
- Existing nonempty fields and populated sections are preserved, including legacy research that may need manual correction. No last_verified_at/by is set. Structured new-application guides hide the matching legacy application_process on CMS View; replacement/renewal legacy text may still be visible.

## Sources
- https://philsys.gov.ph/the-national-id/
- https://philsys.gov.ph/supporting-documents/
- https://philsys.gov.ph/registration-process/
- https://philsys.gov.ph/wp-content/uploads/2024/09/RA11055-REVISED-IRR.pdf
- https://philsys.gov.ph/wp-content/uploads/2024/06/Press-Release-Digital-Natl-ID-Natl-ID-eVerify-Natl-ID-Check_CAHC-signed.pdf
- https://philsys.gov.ph/wp-content/uploads/2025/10/Press-Release_PSA-Warns-Against-Double-Registration-for-National-ID-Encourages-the-Public-to-Avail-Updating-Services.pdf
- https://philsys.gov.ph/2021/10/
