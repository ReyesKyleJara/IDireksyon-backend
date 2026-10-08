# Passport import review — 6 October 2026

This is a source-reviewed correction of the supplied example, not certification that every case is covered. Several OCA pages denied direct access. Indexed official DFA publications and annexes hosted by DFA foreign posts supplied the accessible evidence. Overseas fees and appointment policies were not copied into domestic guidance.

## Corrections encoded

- Citizenship is Filipino; residence in the Philippines alone is insufficient. The blanket “clean record” wording was removed. Regular validity is generally ten years, or five below age 18, subject to lawful restrictions. [RA 11983, sections 4–6 and 12](https://elibrary.judiciary.gov.ph//thebookshelf//showdocs/2/97060).
- Adult new applications include appearance, appointment/form, birth evidence, an accepted ID, and case-specific supporting documents. Spouse-surname use permits the applicable marriage certificate/report. [DFA Annex A](https://beirutpe.dfa.gov.ph/images/2026/ANNEX_A__BASIC_REQUIREMENTS_FOR_ADULT_NEW_APPLICATIONS_-2.pdf).
- Late registration is encoded with alternate routes: an additional accepted ID or two supporting records. The additional ID must differ from the one used for basic identity; this cross-requirement distinction remains a future readiness-calculation concern. [DFA Annex A](https://genevapcg.dfa.gov.ph/images/pdf/AnnexA.pdf).
- Accepted-ID names and date qualifications follow [DFA Annex F](https://genevapcg.dfa.gov.ph/images/pdf/ANNEXF.pdf). National ID formats are one selectable identity. Omitted cards are not declared universally rejected; newer postal-card acceptance requires confirmation.
- The minor checklist adds the form and the child's own identity evidence. Parent/companion documents are separate. Family circumstances need the complete minor guidance, not a universal marriage-certificate-or-SPA choice. [Minor Annex D](https://aganapcg.dfa.gov.ph/images/2024/2024Forms/ANNEX_D__REQUIREMENTS_FOR_MINOR_NEW_APPLICATIONS.pdf), [DFA supporting cases](https://abudhabipe.dfa.gov.ph/index.php/consular-forms-download/82-consular-services/passport).
- Adult electronic-passport renewal does not universally require another primary ID. The newer annex covers police reports for both valid and expired lost passports and an exception to additional birth/ID evidence when a copy of the lost ePassport exists. [Renewal Annex B](https://lisbonpe.dfa.gov.ph/images/2025/NewPPTLaw/ANNEX_B__BASIC_REQUIREMENTS_FOR_ADULT_RENEWAL_APPLICATIONS_.pdf). Older pages conflict on expired-loss procedures; no universal 15-day promise is retained.
- Damaged passports require surrender and an explanation affidavit. [DFA renewal guidance](https://dfa.gov.ph/authentication-functions/100-passport-information/246-requirements-for-renewal-of-passport-2).
- Domestic base fees and the applicable loss/damage penalty use the [2025 Citizen's Charter](https://dfa.gov.ph/images/2025/transparency/DFA_Citizens_Charter_2025_1st_Edition_1.pdf). Payment-channel and courier fees are variable rather than unverified universal amounts. Regular and expedited fees are alternatives.
- Published processing periods come from the [older official domestic charter](https://dfa.gov.ph/images/2021/Transparency-Seal/Mar/31/Consolidated_DFA_Citizens_Charter___Interim_Provisions_2021_1st_Edition.pdf); they are labelled as published estimates, not a verified 2026 service guarantee.
- Malolos and NCR North addresses are supported by the [August 2025 DFA directory](https://dfa.gov.ph/images/2025/directory/Officers_Directory_in_the_Home_Office_as_of_22_August_2025docx.pdf). Neither distance ranking nor current hours/slots was established. Marilao/Fairview claims were not confirmed.

## Replacement scope

Use an explicit Passport ID. Preview makes no writes. With both --replace and --apply, a local JSON pre-change archive is written first; then corrections run in one database transaction. A failed database transaction retains the archive but rolls back the database changes.

Replacement updates the selected record's basic fields, sources, structured eligibility/validity/processing, fee rows, legacy summaries, office links, and the four covered scenarios. Old child rows in those scenarios are removed and rebuilt; scenario IDs are retained. Extra scenarios are explicitly left outside the reviewed scope. Shared document/ID records and other Passport records are not overwritten. Shared offices are reused; conflicting addresses abort, and existing shared schedules are not asserted verified. New offices remain drafts. No verification timestamp or staff identity is fabricated.

No automated restoration command is included: the archive preserves the original values and relationships for inspection or a deliberate restoration. Repeating replacement is not a merge; it deliberately replaces subsequent edits within its stated scope.

## Standalone CMS example

`--example` creates one record named Philippine Passport (ePassport) — CMS Example. It bypasses ID/document lookups and saves named custom items while retaining choices, conditions, qualifications and submission details. It is for checking the CMS layout and encoding, not testing inventory matching or recursive sequencing. Re-running preserves the example and its edits. Existing Passport records and shared ID/document/office directories are untouched. Locations appear as sourced text rather than shared office links; hours remain unspecified. The original supplied text is preserved verbatim in passport-supplied.txt, while the structured example uses the source-reviewed corrections above.
