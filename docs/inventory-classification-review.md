# Inventory classification review — 15 September 2026

The thesis appendix (PDF page 51) is the scope/inventory source. Its individual ID sections are research references, not an instruction to publish every detail. This review organizes that inventory; it is not a complete government application guide.

## Category decisions

Level, category, record type, research status, and issuance availability are separate fields. The three initial levels are Barangay, Municipal and National. Categories use the five requested families and their proposed subcategories; a Civic Reports leaf was added for the blotter record. There are 25 category rows (5 parent groups + 20 leaves). These are IDireksyon editorial categories, not an official government taxonomy. No “primary ID accepted everywhere” classification is implied.

- Community Tax Certificate is a tax record; its primary level is municipal, with a note that deputized barangay collection must be locally verified. It is not exclusively a barangay service. [DILG-hosted Local Government Code, Book II](https://region5.dilg.gov.ph/camarinessur/wp-content/uploads/2018/05/LGC-Book-II.pdf).
- Barangay Business Clearance and municipal Business/Mayor’s Permit are separate. [Board of Investments business-permit overview](https://boi.gov.ph/how-to-setup-business/setting-up/business-process-business-permit/).
- Marriage License, local negative marriage-record certification, and PSA CENOMAR are distinct. The local negative certification remains a research placeholder; no Santa Maria service availability is asserted. [PSA CENOMAR](https://psa.gov.ph/cenomar).
- Real Property Tax Clearance and a certified Tax Declaration copy are separate records. Some local classifications have official sources from other LGUs; their procedures were not copied into Santa Maria entries.
- Voter’s ID and Voter’s Certification remain separate. A current COMELEC issuance source is still needed; new voter-ID availability is unknown in the CMS.
- The broad quarterly ITR/BIR grouping was narrowed to BIR Form 1701Q as the thesis’s concrete quarterly return. It is not a universal resident document and no tax eligibility is inferred. Other BIR filings need explicit scope decisions. [BIR form instructions](https://bir-cdn.bir.gov.ph/local/pdf/1701Q%20Guide%20Jan%202018_copy.pdf).
- Student-Driver’s Permit is stored in Documents as type `permit`, in Driving Credentials. Driver’s licenses remain under Government IDs as type `license`; sharing a category does not make the permit a full license. [LTO student permit charter](https://lto.gov.ph/wp-content/uploads/2023/09/1-CC2024-SP.pdf).
- SSS Digitized ID and UMID are legacy inventory entries. MySSS Card was added to resolve the SSS-related credential grouping. Existing-card validity is different from new issuance. [SSS MySSS Card](https://www.sss.gov.ph/mysss-card/), [SSS launch announcement](https://www.sss.gov.ph/news-and-updates/sss-launches-emv-equipped-dual-function-mysss-card/).
- GSIS Digital ID replaces the generic GSIS ID label; old physical-card guidance needs review against the transition. [GSIS press release hosted by PIA](https://pia.gov.ph/press-release/gsis-accelerates-digital-transformation-through-the-gsis-digital-id/).
- OWWA ID is normalized to OWWA OFW e-Card. [OWWA e-Card portal](https://ecard.owwa.gov.ph/).
- Do not apply the historical Postal ID suspension indiscriminately; newer official guidance exists. Local application availability remains unknown. [PHLPost announcement](https://phlpost.gov.ph/cpt-press-releases/phlpost-simplifies-postal-id-application-for-all/).
- The provincial government lists exactly the supplied 24 barangays, with the canonical name Tabing Bakod. Santo Tomas is preserved in its research note as the thesis alias, not a 25th barangay. [Bulacan provincial directory](https://bulacan.gov.ph/cities-and-municipalities/santa-maria/).

## Deliberately incomplete

All newly inserted inventory is `needs_research`; no entry is marked available for new issuance. Two legacy entries are additionally flagged as legacy. Source URLs support classification or provide a research lead; source-checked dates are left empty because complete application content has not been verified. Requirements, steps, fees, processing times, and validity were not invented or copied wholesale from the thesis. Existing researched content is preserved.

Four named thesis offices were inserted as `needs_research` leads: PSA Bulacan Provincial Office, SSS Santa Maria Branch, GSIS Bulacan Branch Office, and PRC Regional Office III. No coordinates, opening hours or service pivots were populated. Broad mall booths, temporary passport sites, and vaguely named service centers remain unencoded pending exact identification. Address claims are kept as research notes where relevant, not navigation-ready coordinates.

## Seeded inventory mapping

Each source below supports classification or is a research lead; it does not establish complete Santa Maria application information.

| Entry | Directory | Level | Category | Type | Issuance | Source |
| --- | --- | --- | --- | --- | --- | --- |
| Community Tax Certificate (Cedula) | Documents | municipal | tax-records | tax_record | unknown | [Source](https://region5.dilg.gov.ph/camarinessur/wp-content/uploads/2018/05/LGC-Book-II.pdf) |
| Barangay First-Time Jobseeker Certification | Documents | barangay | employment-assistance | certificate | unknown | [Source](https://csc.gov.ph/csc-gov-t-agencies-sign-implementing-guidelines-for-first-time-jobseekers-law) |
| Barangay Business Clearance | Documents | barangay | business-clearances | clearance | unknown | [Source](https://boi.gov.ph/how-to-setup-business/setting-up/business-process-business-permit/) |
| Business Permit (Mayor's Permit) | Documents | municipal | business-permits | permit | unknown | [Source](https://boi.gov.ph/how-to-setup-business/setting-up/business-process-business-permit/) |
| Barangay Clearance | Documents | barangay | local-clearances | clearance | unknown | [Source](https://dole.gov.ph/dole-issues-rules-on-free-documentary-requirements-for-first-time-jobseekers/) |
| Barangay Certificate of Residency | Documents | barangay | residency | certificate | unknown | [Source](https://diffunquirino.gov.ph/services/municipal-social-welfare-development-office/issuance-of-certifications/) |
| Barangay Certificate of Indigency | Documents | barangay | employment-assistance | certificate | unknown | [Source](https://diffunquirino.gov.ph/services/municipal-social-welfare-development-office/issuance-of-certifications/) |
| Barangay Certificate of Good Moral Character | Documents | barangay | local-clearances | certificate | unknown | [Source](https://calambacity.gov.ph/Users/Home/ViewServicesPage?createservicesId=31) |
| Barangay Blotter Report | Documents | barangay | civic-reports | report | unknown | [Source](https://ncr.dswd.gov.ph/download/ARRS-CITIZENS-CHARTER-1.pdf) |
| Senior Citizen ID (OSCA) | Government IDs | municipal | sectoral-welfare | id | unknown | [Source](https://www.ncsc.gov.ph/ncsid) |
| Persons with Disability (PWD) ID | Government IDs | municipal | sectoral-welfare | id | unknown | [Source](https://ncda.gov.ph/disability-laws/administrative-orders/ncda-administrative-order-no-001-series-of-2021/) |
| Solo Parent Identification Card (SPIC) | Government IDs | municipal | sectoral-welfare | id | unknown | [Source](https://www.dswd.gov.ph/dswd-urges-solo-moms-dads-to-apply-update-solo-parent-ids-to-avail-of-services/) |
| Local Birth Certificate Copy (LCR) | Documents | municipal | civil-registry | civil_record | unknown | [Source](https://marilao.gov.ph/wp-content/uploads/2026/03/MCR-Citizens-Charter-2026.pdf) |
| Local Marriage Certificate Copy (LCR) | Documents | municipal | civil-registry | civil_record | unknown | [Source](https://marilao.gov.ph/wp-content/uploads/2026/03/MCR-Citizens-Charter-2026.pdf) |
| Local Death Certificate Copy (LCR) | Documents | municipal | civil-registry | civil_record | unknown | [Source](https://marilao.gov.ph/wp-content/uploads/2026/03/MCR-Citizens-Charter-2026.pdf) |
| Marriage License | Documents | municipal | marriage-licensing | license | unknown | [Source](https://quezoncity.gov.ph/departments/city-civil-registry-department/) |
| Local Certification of No Marriage Record (LCR) | Documents | municipal | civil-registry | certificate | unknown | [Source](https://zamboangacity.gov.ph/office-of-the-city-civil-registrar/) |
| PSA Certificate of No Marriage Record (CENOMAR) | Documents | national | civil-registry | certificate | unknown | [Source](https://psa.gov.ph/cenomar) |
| Real Property Tax Clearance | Documents | municipal | property-records | clearance | unknown | [Source](https://malinao-aklan.gov.ph/downloads/) |
| Tax Declaration (Certified Copy) | Documents | municipal | property-records | tax_record | unknown | [Source](https://santamariabulacan.ph/documents/ordinances/2024/ord-668-2024.pdf) |
| National Police Clearance | Documents | national | background-clearances | clearance | unknown | [Source](https://ncrpo.pnp.gov.ph/wp-content/uploads/2024/12/NPCS-CITIZENS-CHARTER-HANDBOOK-CY-2024_1.pdf) |
| NBI Clearance | Documents | national | background-clearances | clearance | unknown | Official source pending |
| PSA Birth Certificate | Documents | national | civil-registry | civil_record | unknown | [Source](https://psa.gov.ph/civil-registration/citizens-charter) |
| PSA Marriage Certificate | Documents | national | civil-registry | civil_record | unknown | [Source](https://psa.gov.ph/civil-registration/citizens-charter) |
| PSA Death Certificate | Documents | national | civil-registry | civil_record | unknown | [Source](https://psa.gov.ph/civil-registration/citizens-charter) |
| Quarterly Income Tax Return (BIR Form 1701Q) | Documents | national | tax-records | tax_record | unknown | [Source](https://bir-cdn.bir.gov.ph/local/pdf/1701Q%20Guide%20Jan%202018_copy.pdf) |
| Voter's ID | Government IDs | national | general-identity | id | unknown | Official source pending |
| Voter's Certification | Documents | national | electoral | certificate | unknown | Official source pending |
| National ID (PhilSys) | Government IDs | national | foundational-identity | id | unknown | [Source](https://philsys.gov.ph/) |
| Philippine Passport | Government IDs | national | general-identity | credential | unknown | [Source](https://consular.dfa.gov.ph/adult-new/) |
| Postal ID | Government IDs | national | general-identity | id | unknown | [Source](https://phlpost.gov.ph/cpt-press-releases/phlpost-simplifies-postal-id-application-for-all/) |
| Unified Multi-Purpose ID (UMID) | Government IDs | national | social-insurance | id | legacy | [Source](https://www.sss.gov.ph/mysss-card/) |
| SSS Digitized ID (legacy) | Government IDs | national | social-insurance | id | legacy | [Source](https://www.sss.gov.ph/mysss-card/) |
| MySSS Card | Government IDs | national | social-insurance | id | unknown | [Source](https://www.sss.gov.ph/news-and-updates/sss-launches-emv-equipped-dual-function-mysss-card/) |
| GSIS Digital ID | Government IDs | national | social-insurance | id | unknown | [Source](https://pia.gov.ph/press-release/gsis-accelerates-digital-transformation-through-the-gsis-digital-id/) |
| Professional Identification Card (PRC ID) | Government IDs | national | professional | credential | unknown | [Source](https://www.prc.gov.ph/) |
| Student-Driver's Permit | Documents | national | driving | permit | unknown | [Source](https://lto.gov.ph/wp-content/uploads/2023/09/1-CC2024-SP.pdf) |
| Non-Professional Driver's License | Government IDs | national | driving | license | unknown | Official source pending |
| Professional Driver's License | Government IDs | national | driving | license | unknown | Official source pending |
| Tax Identification Number (TIN) Card | Government IDs | national | tax-identity | id | unknown | Official source pending |
| OWWA OFW e-Card | Government IDs | national | overseas-worker | id | unknown | [Source](https://ecard.owwa.gov.ph/) |
