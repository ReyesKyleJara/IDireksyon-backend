# IDireksyon: 41-document CMS audit and proposed encoding freeze

Audit date: **29 October 2026, Asia/Manila**. Decision: **NO — the CMS-to-resident system is not ready for bulk encoding of all 41 documents.**

This is an audit and development proposal, not approved implementation or verified government guidance. No application source, schema, environment, credentials, or existing catalog records were changed. No imports, seeders, local database reset, commits, or pushes were performed. The separate `IDireksyon_41_Document_CMS_Audit.md` checklist was not found in the two repositories or available attachment files; the supplied 41-item request is the matrix used here.

## 1. What was actually verified

| Verification | Result | What the result establishes |
| --- | --- | --- |
| Full Laravel/Pest suite | **491 passed; 6,033 assertions** | Existing backend behavior against isolated SQLite in-memory fixtures. Includes document CRUD, six-step Add/Edit, nested requirements, fee options, guides, offices, ownership checks, rollback, inventory, and prerequisites. |
| CMS JavaScript editor suite | **98 passed; 0 failed** | Existing guide, requirements, prerequisite, and fee editor logic. These are Node tests, not a real-browser encoding session. |
| Full Flutter widget/unit suite | **145 passed; 15 failed** | The resident regression suite is not green. Failures are detailed below; they are not proof of 15 production bugs. |
| Isolated signup rerun | **3 failed** | Signup failures reproduce outside the full suite. |
| Running CMS browser | Directory visible initially; opening Passport redirected to `/login` | Session expired. Authenticated document forms, save/reopen, and six representative workflows **NOT TESTED** in the live browser. |
| Running Flutter web browser | Documented `http://127.0.0.1:8000/app/` returned **404** | Flutter at this documented deployment path was unavailable. No device or alternate live Flutter session was verified. |
| Source/code investigation | Both repositories inspected | Form → request validation → models/migrations → API routes/projections → Flutter consumers traced. |
| Official source research | All 41 catalog entries mapped below | Some evidence is current and accessible; some is indexed official material, historical law, or incomplete local content. These distinctions remain explicit. |

Commands executed for the test evidence:

```sh
# Backend: database is temporary and in memory, not the configured research DB.
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= vendor/bin/pest --do-not-cache-result
node --test tests/Unit/*.test.mjs

# Frontend: existing dependencies; no package installation.
flutter test --no-pub --reporter compact
flutter test --no-pub --reporter expanded test/signup_screen_test.dart
```

Flutter required access to its existing SDK cache outside the workspace; that test execution was approved by the environment review. Test tools can produce generated build/cache/log files. No application code was edited. Full Flutter output is temporarily available at `/tmp/idireksyon-flutter-audit.log`; the signup rerun is at `/tmp/idireksyon-signup-audit.log`.

**Coverage boundary:** no document below has a completed live CMS → save → reopen → actual database-backed API → live Flutter browser test. Those checks are **NOT TESTED for all 41**, regardless of passing automated tests. This audit identifies enough confirmed failures to reject the encoding freeze; it does not certify that every undiscovered defect has been eliminated. SQLite tests also do not certify concurrent MySQL behavior.

Status meanings:

- **PASS:** the particular check ran and passed; never means the entire document is certified.
- **FAIL:** a confirmed code/schema/integration limitation prevents the requested behavior.
- **NEEDS CONTENT RESEARCH (NCR):** issuing-agency/current/local details remain incomplete, conflicting, inaccessible, or insufficiently specific.
- **NOT TESTED (NT):** the actual execution did not occur.
- **N/A:** a process genuinely does not apply; unknown information is not N/A.

## 2. Findings that apply across the catalog

### BLOCKER

**B1 — Documents have no resident detail delivery path.** `routes/api.php` exposes Government ID directory/detail, inventory, and roadmap APIs, but no Documents directory/detail endpoint. `ResidentInventoryController::show()` supplies document identity, behavior flags, prerequisites, and ownership, without document eligibility, checklists, fee rows, guides, processing, validity, sources, or offices. Flutter `ApiService` only loads Government ID detail. Inventory rows toggle ownership; roadmap document nodes have no document detail action. Result: researched content can save in Documents CMS and remain unavailable to the resident who needs it. Applies to all 41.

Minimal fix: a Documents read API and shared resident detail presentation, accessible from document search, linked requirements, inventory, and roadmap dependencies. Preserve `(record type, ID)` identity; a document ID must never open a Government ID with the same number.

**B2 — Request methods are not structured service paths.** A document has one top-level processing-time block and global fee/office relationships. A requirement set has an application type and applicant type, and owns its guide. There is no channel/provider/fulfillment identity connecting one method's requirements, fees, processing, portal, appointment policy, and offices. Fee options express price choices, not an entire alternative acquisition workflow. Text instructions can describe differences but cannot keep the selected method consistent across sections.

Minimal fix: one reusable transaction/request-path context, reused by Requirements, Fees, Guide, and Offices. Distinguish how a request starts (online/outlet/partner/hybrid) from how it is fulfilled (pickup/delivery/download/email/view-only). Do not treat all online starts as online-only completion. PSA Viewable Online, for example, includes an outlet identity/payment step. No generic paragraph workaround is sufficient for the requested audit criteria.

**B3 — The active six-step wizard cannot create/manage the necessary transaction scenarios.** The database/service layer supports `new`, `renewal`, `replacement`, and `custom` requirement sets. However, `checklists.blade.php` hides Add Checklist and applicant/application controls in wizard mode. Add Document creates a neutral `new/all` set and filters its editor to that set. Edit can select existing sets when there are several, but does not provide the normal controls to add/name the missing ones. The wizard Guide likewise offers existing scenario selection, not a complete transaction manager. Thus “supported in a model” does not mean “encodable through the current UI.”

Minimal fix: expose a compact transaction/request-path manager inside the existing Requirements step, with the selected context retained through Fees/Guide/Offices. Reuse existing custom labels for correction, certified copy, authentication, retrieval, etc.; do not delete or recode existing application types merely to improve labels.

**B4 — Resident Guide and Offices still contain misleading prototype content.** Flutter `id_details_screen.dart` builds a hardcoded “Passport Application Guide,” generic appointment/appearance steps, sample DFA offices, example Bulacan/Metro Manila addresses, and invented 25/45-minute travel times. These are not the saved record's structured data. Government ID API detail also does not return the CMS structured guide and office-service relationships needed by a complete renderer. A separate map feature does not fix these detail tabs.

Minimal fix: use real record/path data; remove prototype fallbacks; display a truthful empty state when no content exists. Render a portal/download route without fake map pins, office visits, appointments, or travel times. This fix should be shared with Documents, not copied into another prototype screen.

**B5 — Document-level research provenance is missing.** `Document` and its migrations have no record-level source collection, `last_verified_at`, or verifier relationship. The Document wizard does not accept these. Office-service pivots do have a source URL and verification columns, and guide blocks can carry official links, but neither establishes which source supports the document's fee/eligibility/channel facts. Government ID source fields exist, but their current wizard UI omits the source section too. Audit history identifies edits, not factual verification.

Minimal fix: structured source entries with title/issuer/URL, checked date, scope (record/path/office service), optional publication/effective date and page/section, and a separate review status. Expose sources and checked dates in the resident presentation. Never automatically mark imported or merely saved research as verified.

### IMPORTANT

**I1 — Unknown availability cannot be represented honestly.** `ApplicationAvailability` contains available, limited/conditional, temporarily unavailable, and discontinued. New Documents default to **available**, **guided**, and included in sequencing. Saving a name creates the record immediately, before research is complete. Existing inventory includes catalog rows without a research-completeness gate. Unknown issuance is different from discontinued or temporarily unavailable.

Minimal fix: add an explicit unverified/unknown state or equivalent explicit research state, independent from issuance availability, and make new incomplete entries visibly draft. Keep unverified facts out of actionable acquisition recommendations. Retain valid existing inventory identities; do not delete drafts or silently rewrite existing availability. This is a narrow accuracy control, not a new editorial bureaucracy.

**I2 — Optional/irrelevant sections lack a complete presentation policy.** Empty sections are allowed and CMS details hide many blanks. Informational mode preserves content and Flutter Government ID detail hides acquisition tabs. However, there is no consistent per-section “not applicable / not researched / hidden but retained” policy across Document CMS, Review, API, and Flutter. Review counts say “Not added yet” even when offices are correctly irrelevant. Structured processing cannot encode minutes/hours; validity cannot attach a clear contextual qualifier such as “document does not expire, but receiving institution may require a recent copy.”

Minimal fix: distinguish content absence from N/A; allow optional display suppression without deleting values; support concise validity notes and processing minutes/hours plus timing basis/notes. Retain duration/range/same-day/varies and existing validity types. Do not build an expiry inference engine.

**I3 — Office-service scope is limited to three transaction statuses.** Document/office pivots store new/renewal/replacement availability and service notes, with source metadata. There is no explicit path scope for correction, certified copy, authentication, retrieval, a partner counter, or a specific online channel. An office's existence does not establish it performs every service.

Minimal fix: link verified office services to applicable request paths, with unknown availability allowed. Partner providers must be explicitly identified; online portals must not be entered as fake physical offices. Keep existing office directory and schedules.

**I4 — Prerequisites and application requirements are different systems.** Existing typed prerequisite edges have cycle checks and feed the planner. Checklist groups have AND/OR/choose-N rules and conditions. The planner traverses prerequisite edges, not those checklist alternatives. Prerequisite conditions remain descriptive; `PrerequisiteStatus` matches ownership without evaluating conditions. An optional/alternative input entered as several prerequisite rows can therefore become several mandatory dependencies. Ownership also does not prove matching person, business, property, vehicle, certificate variant, expiry, or a required number of copies.

Minimal fix for this batch: document the difference in the editor, make path-specific prerequisite applicability explicit, and prevent the resident from treating descriptive conditional/alternative edges as universally required. Use manual confirmation for conditions that are not evaluated. Label scores “Inventory match” or “Prerequisite ownership,” never official acceptance/completion. Do not build generalized eligibility automation. If structured alternative dependency planning is postponed, it must remain visibly unsupported rather than producing an overconfident route.

**I5 — A single Requirements Continue is not one transaction across all editors.** Browser JS saves the checklist first, then submits the parent wizard form which separately saves prerequisites. Each server operation has its own transaction. A prerequisite validation failure can leave checklist edits already saved. This follows the code path; the failure combination was not reproduced in a live browser. Other steps intentionally save independently.

Minimal fix: combine the Requirements-step operation atomically, or make the separate saved/draft state unmistakable and preserve all input on failure. Add an integration test for this exact combined failure. Do not promise whole-wizard rollback; saving per step is intentional.

**I6 — Team editing and failure recovery are not fully certified.** Existing nested ownership/rollback protections pass. No expected-version token is apparent in the inspected wizard/checklist save flows. Row locks serialize writes but do not by themselves warn a second editor that a stale form will replace the first editor's work. Session expiry, interrupted network, duplicate Continue, and browser Back need actual browser verification. Concurrent stale saves were not executed against local data.

Minimal fix: stale-edit detection with a reload/review path if the focused test confirms overwrite; protect drafts on validation/network/session errors. This is particularly relevant before several researchers encode together.

**I7 — Flutter regression tests are stale/incomplete.** Current failures: profile setup **4**, account details **5**, signup **3**, home journey banner **1**, logout **2**. Examples confirmed in source: setup tests assume hardcoded names/string payloads while current setup loads numeric inventory IDs and barangays and starts at About You; signup looks for “Sign Up” while the form uses “Create Account”; account tests use older labels; navigation tests require `BottomNavigationBar` while the shell uses `_ResidentBottomBar`; logout now has confirmation. Signup also has a `pumpAndSettle` timeout, which requires separate animation/settling diagnosis. None should be made green by removing the behavioral assertions.

Minimal fix: update fixtures, interactions, and assertions to the agreed behavior, then test missing/error/empty API responses and bounded animation waits. Classify remaining failures as actual defects or test defects and resolve them. Passing Government ID widget tests does not certify a nonexistent Documents screen.

**I8 — Research content remains incomplete for local services.** The municipal website returned a JavaScript shell to the research tool, and no complete current Santa Maria charter was recovered. National law and another LGU's charter cannot verify Santa Maria requirements, rates, counters, appointments, or processing times. The 24 barangays may differ too. Record this uncertainty rather than fabricating a universal local workflow.

**I9 — Setup documentation does not match the running delivery path.** Frontend README describes `/app/`; that path returned 404. The backend README/context/integration notes also contain outdated descriptions of inventory/sequencing and prior save behavior. This is an operational verification gap, not proof that Flutter cannot run on another port/device.

Minimal fix: one tested local launch and team verification procedure, correct current docs, and an actual available resident build for acceptance testing. No launcher, server, or environment was changed during this audit.

### OPTIONAL / safely postpone

- Fee calculators, tax/engineering computations, automated eligibility and profession questionnaires.
- Automatic selection of the cheapest/fastest channel or optimal alternative dependency chain.
- Resident storage of personal certificate numbers, actual documents, property/vehicle records, or file uploads. If these are not stored, keep the inventory-match limitations visible.
- Advanced map ranking, live queues, live appointment slots, courier tracking, and partner transactions inside IDireksyon.
- A wholesale new wizard, separate custom screen per document, icon/category redesign, AI classification, and unrelated authentication/profile redesign.

## 3. Six-step CMS capability matrix

| Existing step | Retain | Confirmed limitations | Audit result |
| --- | --- | --- | --- |
| 1. Basic Information | Name/agency/classification, description, optional eligibility, validity and processing, availability/audience/behavior | No document source review; default available; only one processing block; no useful validity qualifier field | **PARTIAL / FAIL for requested provenance and per-path detail** |
| 2. Requirements | Typed references, custom evidence, all/one/N, OR ways, descriptive conditions, copies/instructions, separate prerequisites | Wizard hides scenario creation; no channel scope; prerequisites do not inherit checklist OR rules; two-request save boundary | **Core rules PASS in existing tests; requested transaction workflow FAIL** |
| 3. Fees | Fixed/range/free/varies, optional charges, mutually exclusive fee options, descriptions/notes | No explicit channel/transaction/applicant/office scope; only PHP supported | **Core fees PASS; contextual association FAIL** |
| 4. Application Guide | Ordered rich-text steps/blocks, safe links, reordering and preservation | Tied to existing requirement set rather than a complete channel; resident structured renderer missing | **Editor tests PASS; resident delivery FAIL** |
| 5. Offices | Reusable directory, schedules, per-link notes/source/status | Only new/renewal/replacement statuses; cannot associate service with a path | **Core links PASS; path scope FAIL** |
| 6. Review | Saved counts and return-to-step links | Counts are not correctness review; no complete path/source/resident preview; empty versus N/A ambiguous | **Existing count behavior PASS; freeze acceptance FAIL** |

Fees in international currencies are outside the minimum domestic Santa Maria scope. If international delivery is mentioned, do not encode a USD charge as PHP; either omit the out-of-scope route with an official reference or approve explicit currency support separately.

## 4. Source register and freshness

All sources below were checked or searched on **2026-10-29**. This is the research-access date, not a fabricated CMS verification timestamp. “Indexed” means official-host search extracts were available but full content was not successfully inspected; exact production instructions based only on those extracts remain NCR. Portals were viewed/read only: no government accounts, appointments, applications, payments, or personal-data submissions were made.

| ID | Official source / authorized channel | Evidence and limits |
| --- | --- | --- |
| S01 | [PSA E-Certificate advisory, 16 March 2026](https://psa.gov.ph/content/public-advisory-105) | Directly accessible. Covers digital versions of all seven PSA records in this catalog; email PDF delivery. |
| S02 | [PSA Viewable Online advisory, 26 September 2025](https://psa.gov.ph/content/public-advisory-86) | Official publication; access-code service, outlet payment/identity step, limited viewing window, separate DocPrint, representative restriction. Not the same service as an emailed e-certificate. |
| S03 | [PSA Serbilis FAQ](https://psaserbilis.com.ph/GetFaQs) | Indexed authorized-channel detail; fees vary by product/fulfillment/copies. Full portal shell is not a completed transaction. |
| S04 | [PSAHelpline ordering FAQ](https://psahelpline.ph/faq) | Directly accessible provider instructions. Relationship/requester and authorized-receiver policies are channel-specific; do not copy them to every PSA outlet. |
| S05 | [PSA marriage request form/privacy notice](https://psa.gov.ph/system/files/ApplicationForms/Marriage%20Application%20Form.pdf?vcode=14) | Indexed official notice: restricted release to owner/authorized people, identity and authorization. |
| S06 | [PSA 2025 Bicol charter](https://rsso05.psa.gov.ph/system/files/documents/Citizen%20Charter%202025%201st%20Edition.pdf) | Indexed: CENOMAR versus Advisory on Marriages outcomes. Local office timings are not Santa Maria promises. |
| S07 | [PSA 2025 Aurora charter](https://rsso03.psa.gov.ph/sites/default/files/Citizen%27s%20Charter/Citizen-s-Charter-Aurora.pdf) | Indexed: a matching death record produces Advisory on Deaths instead of CENODEATH. |
| S08 | [PSA civil registration facts](https://psa.gov.ph/civilregistration/facts) | General registration/correction context; contains historical material. Not sufficient for a complete current correction guide. |
| S09 | [NBI official clearance portal](https://clearance.nbi.gov.ph/) | Indexed current portal with ordinary application, online renewal/delivery, and first-time-jobseeker service. |
| S10 | [NBI first-time-jobseeker charter](https://nbi.gov.ph/citizens-charter/nbi-clearance-first-time-job-seekers/) | Indexed official checklist/fee-exemption service; confirm current branch instructions before encoding exact rules. |
| S11 | [PNP NPCS current registration terms](https://pnpclearance.ph/register) | Directly accessible. Current page states one accepted ID, six-month clearance validity, and conditional nonappearance renewal. Older official instructions say two IDs; do not mix versions. |
| S12 | [PNP official application guide](https://www.pnpclearance.ph/landing/AppPonsive/doc/how_to_apply_v2.pdf) | Indexed official guide distinguishes appearance/nonappearance renewal and downloadable result. |
| S13 | [COMELEC Minute Resolution 24, 6 February 2024](https://www.comelec.gov.ph/?r=References%2FComelecResolutions%2FOtherMatters%2FMinuteReso24) | Suspends voter-certification fees from 12 February 2024. Older P75 pages conflict; latest local implementation still needs confirmation. |
| S14 | [COMELEC certification service](https://www.comelec.gov.ph/?r=AboutCOMELEC%2FCitizensCharter%2FFrontlineServices%2FVotersGeneralPublic) | Historical charter: identity, representative and claim stages. Not a current fee authority. |
| S15 | [CSC first-time-jobseeker implementing guidelines](https://www.csc.gov.ph/csc-gov-t-agencies-sign-implementing-guidelines-for-first-time-jobseekers-law) | Eligibility certification and Oath of Undertaking; scope is not every government charge. |
| S16 | [DOLE first-time-jobseeker rules](https://dole.gov.ph/dole-issues-rules-on-free-documentary-requirements-for-first-time-jobseekers/) | Barangay certification, residence criterion, one-time benefit and one-year availment period; lab/other exclusions need preservation. |
| S17 | [CSC 8 March 2026 exam results/CoE advisory](https://csc.gov.ph/csc-releases-results-of-8-march-2026-civil-service-exam) | Free letterhead and paid special-paper options; eligibility must already exist. |
| S18 | [CSC 2026 detailed examination advisory](https://csc.gov.ph/phocadownload/userupload/erpo/advisories/2026/ExamAdvisory_2026_04_Release%20of%2008%20March%202026%20CSE-PPT%20results%20orig%20sgd.pdf) | Indexed personal claiming, ID, conduit-office and separate security-paper rules. Cohort-specific dates are not universal availability. |
| S19 | [PRC 2025 Citizen's Charter](https://www.prc.gov.ph/sites/default/files/PRC_CC_03312025.pdf) | Indexed sections: Passing/Board Rating, Good Standing, authentication. Direct fetch returned 502; exact current requirements/fees need full-content verification. |
| S20 | [PRC official LERIS instructions](https://prc.gov.ph/sites/default/files/LERIS-Online-Step-by-Step-Procedure-v2.pdf) | Indexed certification selection/quantity/appointment workflow. Historical guide; profession and office availability must be rechecked. |
| S21 | [TESDA assessment/certification procedures](https://www.tesda.gov.ph/About/TESDA/46) | Directly accessible. Assessment, claiming, renewal/revalidation, lost/damaged replacement, representative SPA, and CAV are distinct. |
| S22 | [TESDA certification FAQ](https://www.tesda.gov.ph/About/TESDA/127) | NC versus COC distinction and qualification-dependent renewal context. |
| S23 | [DFA documentary requirements](https://www.apostille.gov.ph/documentary-requirements/) | Indexed current text includes digital PSA/CHED paths and an NBI eClearance change dated 23 October 2026. Direct fetch returned 403; this recent change must be confirmed before publishing detailed instructions. |
| S24 | [DFA fees](https://www.apostille.gov.ph/schedule-of-fees-tab/) and [DFA FAQ](https://www.apostille.gov.ph/faqs/) | Indexed physical regular/expedited and e-Apostille distinctions, underlying-document costs, digital delivery. Some FAQ text is older; do not combine inconsistent dates/country rules. |
| S25 | [DTI 2025 Citizen's Charter](https://dtiwebfiles.s3-ap-southeast-1.amazonaws.com/Transparency%20Seal/Citizen%27s%20Charter%202025%20%287th%20Edition%29_v2.pdf) | Indexed new/renewal, individual/sole-proprietor and non-Philippine-national distinctions. |
| S26 | [DTI BNRS official fee schedule](https://bnrs.dti.gov.ph/pdf/registration/business_law.pdf) | Indexed scope-based fees, DST, late renewal, certification/change services. Live FAQ direct fetch was 403. |
| S27 | [SEC eSPARC](https://esparc.sec.gov.ph/application) and [OneSEC](https://esparc.sec.gov.ph/application/select-one-sec) | Current portal describes company-type paths, eSECURE/eSAP, payment and digitally signed incorporation certificate. A partnership registration is not an incorporation certificate. |
| S28 | [Santa Maria municipal website](https://santamariabulacan.ph/) | Accessible shell only; complete current local citizen services/charter not recovered. |
| S29 | [Santa Maria Ordinance 668, 2024](https://santamariabulacan.ph/documents/ordinances/2024/ord-668-2024.pdf) | Indexed official assessor duties include tax declarations/certified copies. Does not establish current application checklist, fee or turnaround. |
| S30 | [Local Government Code, RA 7160](https://elibrary.judiciary.gov.ph/thebookshelf/showdocs/2/53542) | Legal basis for barangay business clearance, community tax and local assessment. Historical statute is not a current local service charter or fee quotation. |
| S31 | [BFP charter](https://www.bfp.gov.ph/wp-content/uploads/2020/12/3-BFP-Citizens-Charter-Handbook-for-Printing.pdf) | Indexed new/renewal/occupancy FSIC contexts. Historical rates require current local verification. |
| S32 | [BFP online FSIS announcement via PIA, 20 January 2025](https://pia.gov.ph/news/bfps-fire-safety-inspection-certificate-up-online/) | Official government announcement for Antique; evidence of channel variation, not proof of Santa Maria rollout/rates. |
| S33 | [DOH Food Establishments sanitation IRR](https://elibrary.judiciary.gov.ph/thebookshelf/showdocs/10/39219) | Establishment sanitary permit and worker health certificate are separate concepts. Local checklist/testing/fees remain NCR. |
| S34 | [National Building Code](https://elibrary.judiciary.gov.ph/thebookshelf/showdocs/11/53320) and [streamlining guidelines](https://elibrary.judiciary.gov.ph/thebookshelf/showdocs/10/89926) | Legal/project-specific building/occupancy context. Not a substitute for Santa Maria OBO requirements. |
| S35 | [DHSUD locational-clearance circular, 2024](https://dhsud.gov.ph/wp-content/uploads/Laws_Issuances/05_Department_Circulars/2024/Department%20Circular%20No.%202024-004_.pdf) | Indexed DHSUD jurisdiction-specific process; must not be presented as the municipal zoning process for every project. |
| S36 | [BIR ORUS registration updates, RMC 48-2023](https://bir-cdn.bir.gov.ph/local/pdf/RMC%20No.%2048-2023%20%281%29.pdf) | Indexed registration/COR generation and updates; current forms/checklists must supplement it. |
| S37 | [BIR revised registration forms, RMC 97-2025](https://bir-cdn.bir.gov.ph/BIR/pdf/RMC%20No.%2097-2025.pdf) | Indexed October 2025 form revisions, including registration and information update/correction forms. |
| S38 | [BIR eTCS circular, RMC 1-2025](https://bir-cdn.bir.gov.ph/BIR/pdf/RMC%20No.%201-2025.pdf) and [BIR tax-clearance forms](https://www.bir.gov.ph/Tax-Clearance-Application-Form) | General-purpose clearance differs from bidding/other clearances; DVR and eTCS context. Region/RDO implementation and current fees need confirmation. |
| S39 | [LRA official FAQ](https://lra.gov.ph/frequently-asked-questions/) and [LRA eSerbisyo instructions](https://lra.gov.ph/launching-of-lra-eserbisyo-portal/) | Registry versus online delivery; page-count and digitization differences. Full current fees/coverage should be rechecked at encoding. |
| S40 | [PhilHealth online services](https://www.philhealth.gov.ph/services/) | Directly accessible current Member Portal view/print MDR service. |
| S41 | [PhilHealth Member Portal instructions](https://www.philhealth.gov.ph/news/2021/member_portal.pdf) | Indexed download/print and membership-correction distinction; older regional email instructions need current validation. |
| S42 | [Pag-IBIG official MDF, v11 September 2024](https://www.pagibigfund.gov.ph/document/pdf/dlforms/providentrelated/PFF039_MembersDataForm_V11.pdf) | Indexed blank form and submission instructions. A blank PDF is not proof of a populated member-record download. |
| S43 | [Pag-IBIG agency reply: Copy of MDF, June 2026](https://www.foi.gov.ph/agencies/hdmf/copy-of-mdf/) | Indexed issuing-agency reply distinguishes personal record requests from public FOI. Full fetch 503; exact branch request rules remain NCR. Main Pag-IBIG site presented a browser check. |
| S44 | [LTO 2025 Citizen's Charter](https://lto.gov.ph/wp-content/uploads/2025/09/LTO-CC-2025-External-v2.pdf) | Indexed separate initial registration, annual vehicle-registration renewal, record revisions, and duplicate CR/CRE sections. Do not reuse 2023 fee tables as verified 2026 prices. |
| S45 | [MARINA eGovPH advisory, 11 May 2026](https://marina.gov.ph/2026/05/11/breaking-marina-releases-advisory-on-exclusive-online-application-of-sid-srb-through-egovph-app/) | Directly accessible. From 13 May 2026 appointments for SID/SRB issuance/renewal/replacement use eGovPH. Not a claim that no personal appearance is required. |
| S46 | [MARINA SIRB transition advisory, 23 April 2025](https://marina.gov.ph/wp-content/uploads/2025/05/MA-2025-10.pdf) | Indexed distinguishes SRB and SID from older SIRB. |
| S47 | [DSWD 2024 AICS charter](https://aics.dswd.gov.ph/wp-content/uploads/2024/02/PMB-2024_1st-Edition.pdf) | Indexed recognition of barangay residency/indigency evidence. Does not verify Santa Maria barangay issuance procedures. |

Fees are deliberately not reproduced wholesale here. The audit needs to establish which fee structures the CMS must support. Exact payable totals remain a content task where current, channel-specific primary evidence is incomplete. A checked URL is not a successful application or a guarantee of continuous portal availability.

## 5. All 41 documents: content, scenarios, capability, and disposition

**Common results for every row:** resident detailed presentation **FAIL (B1)**; actual live save/reopen/API/Flutter workflow **NT**. Source checked date **2026-10-29** for every row. Every row also needs B5 source provenance. The record-specific CMS findings and fixes below are additional to these common results. “Guided” and “Informational” are proposed capstone presentation decisions, not official classifications; approval is still required.

### Civil Registry

**1. PSA Birth Certificate — FAIL; some details NCR.** Sources: S01–S05, S08. A copy of an existing civil birth record, not registration of a new birth. Owner/eligible relatives/authorized requester; channel rules differ. Paths: CRS outlet, authorized online request with delivery/pickup, e-certificate, Viewable Online/DocPrint. No-record, delayed registration, annotation/correction, new/unconverted record and representation need distinct instructions. ID/authorization evidence is conditional; costs and fulfillment differ by channel. No ordinary renewal; repeat copy and correction are distinct. **CMS:** generic requirements can fit; channel timing/fees/guide cannot remain associated. **Fix:** B2/B3, I2; separate access-window duration from document validity. **Proposed mode:** Guided copy retrieval; refer corrective-registration cases to the proper LCR process pending complete research.

**2. PSA Marriage Certificate — FAIL; corrections NCR.** Sources: S01–S05, S08. Copy of a registered marriage event; available channels include physical and electronic fulfillment. Entitled requester/representative identity and authority matter. Repeat copies, recently registered records, missing record and annotated/corrected copies must not be presented as one new application. Fees/turnaround are method-specific; no blanket renewal requirement. **CMS:** basic evidence and notes fit; separate method/context does not. **Fix:** B2/B3, I2; retain actual marriage certificate identity separately from Advisory on Marriages. **Proposed mode:** Guided copy retrieval.

**3. PSA Death Certificate — FAIL; special cases NCR.** Sources: S01–S05, S08. Copy of an existing death registration; requester relationship/authority differs from a living person's self-request. Physical copy, provider delivery/pickup and digital paths need their own fulfillment details. Recently registered/unavailable records and corrections need escalation rather than a promise of immediate release. Fees/timing vary; renewal is N/A, repeat-copy request applies. **CMS:** conditions support descriptive evidence, but channels and requester-specific path cannot scope the other sections. **Fix:** B2/B3, I2. **Proposed mode:** Guided retrieval.

**4. PSA Certificate of No Marriage Record (CENOMAR) — FAIL.** Sources: S01–S06. Official index-search certification; no-record result differs from an Advisory on Marriages. Requester authority and identity are required; outlet, delivery/pickup and digital services differ. Repeat request is a new search/certification, not marriage registration or a normal renewal. Receiving agencies may require recent issuance; do not invent a universal expiry period. **CMS:** can name accepted IDs and conditional authorization, but cannot cleanly bind search outcome, channel costs and delivery. **Fix:** B2/B3, I2, with explicit possible-outcome notes/link. **Proposed mode:** Guided request.

**5. PSA Advisory on Marriages — FAIL.** Sources: S01, S06. Officially identified as the result when the relevant marriage record is found, not a substitute label for the marriage certificate. Scope entitled requester, index-search/copy request, authorized methods, and recent/corrected records. Verify channel pricing and manual-verification turnaround for this product rather than copying a marriage-copy fee. Renewal is N/A; repeat/current search is relevant. **CMS:** distinct catalog identity is possible, but related search/outcome explanation and channel context are missing. **Fix:** B2/B3, I2; explain the CENOMAR/Advisory relationship without creating a fake independent first-time process. **Proposed mode:** Guided request with outcome notice.

**6. PSA Certificate of No Death (CENODEATH) — FAIL.** Sources: S01–S04, S07. Confirms no death record in the searched registry, not a medical certificate and not proof that a portal can issue every applicant's desired result. Entitled requester, identity, repeat search, representative restrictions by channel, and digital/physical fulfillment must remain separate. Fees differ from ordinary death-copy fees. **CMS:** basic requirement alternatives fit; no path/fulfillment/cost association. **Fix:** B2/B3, I2; scope result and access-window explanations. **Proposed mode:** Guided request; detailed current local/manual-verification content remains NCR.

**7. PSA Advisory on Deaths — FAIL; channel details NCR.** Sources: S01, S07. Official product/outcome is confirmed; a recorded death can result in this advisory instead of CENODEATH. Identify the entitled requester and distinguish it from the actual Death Certificate. Research exact provider/output naming, prices and manual-verification timing before encoding a complete path. Repeat search/copy, record discrepancy and authority are relevant; ordinary renewal N/A. **CMS:** catalog/reference is possible; complete channel-sensitive outcome presentation is not. **Fix:** B2/B3 and explicit possible-outcome notice using existing text/link blocks. **Proposed mode:** Guided only for verified retrieval paths; otherwise reference content pending research.

### Clearances and Personal Certifications

**8. NBI Clearance — FAIL; exact current checklist/charges NCR.** Sources: S09/S10. Clearance applicants use the official application/appointment workflow; eligible existing holders have an online-renewal/delivery path. First-time-jobseeker exemption is another conditional path. Identity evidence, barangay certification/undertaking where applicable, branch appearance, review/HIT handling and changes to personal data must not be conflated. Charges may include service/delivery differences. **CMS:** references/conditional evidence fit; separate renewal and exemption paths cannot be created properly through the current wizard or scope other sections. **Fix:** B2/B3/I3. **Proposed mode:** Guided. Do not publish a fixed HIT release promise without current evidence.

**9. National Police Clearance — FAIL; conflict NCR.** Sources: S11/S12. Official NPCS supports new applications/appointments and conditional renewal without appearance, with uploaded identity evidence and downloadable clearance. Current registration terms state one ID; an older official guide says two. Existing identity and upload/appearance stages differ; fees and exemptions need the current transaction rules. **CMS:** AND/OR requirements fit, but uploads and nonappearance renewal cannot scope guide/office/fee sections. **Fix:** B2/B3/I3; record the conflicting checklist versions and resolve before publication. **Proposed mode:** Guided. Do not turn the audit into a personal-data upload feature.

**10. Barangay Clearance — FAIL + NCR.** Sources: S16, S28, S30. Personal barangay clearance is separate from barangay business clearance. Local resident/request-purpose, regular versus first-time-jobseeker exemption, repeat copy and representation need verification with the actual barangay. No complete current Santa Maria barangay checklist, fee, office schedule or turnaround was recovered. **CMS:** generic conditions/evidence fit; jurisdiction/path-specific service does not. **Fix:** B2/B3/I3/I8; do not apply one barangay's charges to all 24. **Proposed mode:** Guided after local validation; unknown availability until then.

**11. Voter's Certification — FAIL + NCR.** Sources: S13/S14. Certification of a voter-registration record, not a new voter ID or voter registration. Registered voter/personal or authorized claim, local Election Officer versus central service and purpose-specific format need current verification. Historical P75 instructions conflict with the 2024 fee suspension; do not publish P75 as universal. Repeat certification applies; normal renewal N/A. **CMS:** identity/representative requirements fit; office/path and versioned fee policy do not. **Fix:** B2/B3/I3/B5; confirm Santa Maria OEO instructions. **Proposed mode:** Guided retrieval.

**12. Barangay Certificate of Residency — FAIL + NCR.** Sources: S28/S47. Local certification of residence, not a Cedula. Resident/authorized-requester eligibility, acceptable residence evidence, request purpose, current address verification, fees and claiming depend on issuing barangay. Renewal cannot be assumed; another recent certificate may be requested. **CMS:** typed/custom evidence and address qualification fit; local service scope and content review need work. **Fix:** B2/I3/I8, plus expiry/recency notes rather than invented fixed validity. **Proposed mode:** Guided after actual barangay confirmation.

**13. Barangay Certificate of Indigency — FAIL + NCR.** Sources: S28/S47. Barangay evidence of indigency is distinct from a municipal social-worker certification or approval for assistance. Resident/request-purpose and local assessment/authority need verification. Do not auto-determine poverty/benefit eligibility or invent fees, income thresholds, processing or renewal. **CMS:** custom evidence/conditions can describe local requirements; provenance and office/path scope are missing. **Fix:** I3/I8, B5; retain manual eligibility/assessment notes. **Proposed mode:** Guided for obtaining the certificate once verified, not guidance promising benefits.

**14. Barangay First-Time Jobseeker Certification — FAIL; local details NCR.** Sources: S15/S16/S28. Special statutory evidence, separate from an ordinary barangay clearance. Applicant must meet the first-time-jobseeker rules; barangay certification and undertaking are relevant, with one-time benefit and time-limited availment. Do not describe it as an annually renewable free-clearance entitlement. Local filing/claiming requirements and availability still need verification. **CMS:** linked certificate/custom undertaking and conditional requirements fit; dependent services' waived versus nonwaived costs lack contextual scope. **Fix:** B2/B3/I4 and clear human-readable restrictions. **Proposed mode:** Guided.

**15. CSC Certificate/Certification of Eligibility — FAIL; detailed issuance variants NCR.** Sources: S17/S18. Eligibility must already be granted/earned. Letterhead issuance, special/security-paper certification, authenticated copy, unavailable/lost original, correction and conduit-office claiming are different services. Exam/cohort-specific release dates must not become permanent availability dates. Identity evidence and claiming instructions apply; free and paid variants differ. **CMS:** a specialized audience and descriptive evidence fit; variant-specific fees/guide cannot be associated, and wizard cannot add the variants. **Fix:** B2/B3. **Proposed mode:** Informational for earning eligibility; Guided retrieval only if the narrower verified copy-request scope is explicitly chosen. No profession questionnaire required.

**16. PRC Certificate of Passing — FAIL + NCR.** Sources: S19/S20. Certification of passing an applicable licensure examination, not the professional ID or registration certificate. Eligible examinee, LERIS certification type, exam details, quantity, office/appointment and representative rules need current verification. Initial/repeat certification and authentication differ; no automatic annual renewal. **CMS:** reference/audience and custom evidence fit, but scoped services and resident content do not. **Fix:** B2/B3/I3; fully inspect current PRC requirements and fee schedule before encoding. **Proposed mode:** Informational for this capstone, with official retrieval link.

**17. PRC Certificate of Rating — FAIL + NCR.** Sources: S19/S20. Official charter calls this certification of Board Rating and covers passed or failed results; not interchangeable with Certificate of Passing. Identify eligible examinee, exam/quantity and request/claim method; separate authentication. Exact current fee, release and office policy remain NCR. **CMS:** name/reference can be accurate; its distinct certification path lacks full context. **Fix:** B2/B3 and preserve separate record identity. **Proposed mode:** Informational with verified issuing-service reference; no exam-taking roadmap.

**18. PRC Certificate of Good Standing — FAIL + NCR.** Sources: S19/S20. PRC-issued COGS is not automatically an accredited professional organization's separate good-standing certificate. Licensed professional/registration status, profession, purpose, appointment/claim and representative rules need current verification. Repeat/current certification is not PIC renewal. Fees/timing remain NCR until full current charter is inspected. **CMS:** audience/description are possible; service variants and correct resident explanation are incomplete. **Fix:** B2/B3/B5, explicit issuer naming. **Proposed mode:** Informational.

**19. TESDA National Certificate (NC) — FAIL; qualification-specific content NCR.** Sources: S21/S22. NC represents competence across the qualification's units; not a training attendance certificate. Assessment and first issuance, renewal/revalidation under applicable Training Regulations, lost/damaged replacement, changes and CAV are distinct. Accredited assessment center and issuing provincial/district office roles differ; assessment fees vary by qualification. Required forms/photo/evidence, results/claim documents and representative SPA belong to their relevant stage. **CMS:** alternatives and audience fit; generic “TESDA NC” cannot safely imply one assessment fee or universal guide. **Fix:** B2/B3/I3, qualified reference notes. **Proposed mode:** Informational at this catalog scope.

**20. TESDA Certificate of Competency (COC) — FAIL; qualification-specific content NCR.** Sources: S21/S22. Covers a unit or cluster rather than all competencies of an NC. Assessment, issuance, renewal/revalidation, replacement and CAV must retain that distinction. Center/qualification, proof of competence and claiming authority matter; fees and timelines cannot be inferred from another qualification. **CMS:** separate reference identity is supported; scoped workflow is not. **Fix:** same reusable B2/B3 solution, without merging NC and COC. **Proposed mode:** Informational with issuing-service references.

**21. DFA Apostille — FAIL + NCR for latest detailed rules.** Sources: S23/S24. Authentication service applied to a qualifying underlying document; document type and destination determine the applicable process. Physical submission and digital PSA/CHED services differ; digital result is not a physical courier copy. The indexed requirements include changes effective 23 October 2026, requiring confirmation. Document/certification prerequisites, representatives, appointment, separate underlying-document fees and processing classes matter. **CMS:** can link an input Document and express alternatives, but global prerequisite rows cannot safely make every possible input compulsory. **Fix:** B2/B3/I3/I4; contextual accepted inputs and fulfillment. **Proposed mode:** Guided for verified paths only.

### Business and Local Permits

**22. DTI Business Name Registration Certificate — FAIL; detailed exceptions NCR.** Sources: S25/S26. Sole-proprietor business-name registration is not authority to operate. New registration, timely/late renewal, change/correction, certified copy and download/retrieval differ. Applicant nationality/authority and representative cases matter. BNRS supports online issuance; fee options depend on territorial scope, with separate charges/late policies. **CMS:** fee options can express scope choices, but cannot associate a full transaction/channel/processing policy. **Fix:** B2/B3/I2; retain variable/fee-option editor, no calculator necessary. **Proposed mode:** Guided for verified domestic paths; exclude broader business advice.

**23. SEC Certificate of Incorporation — FAIL; type-specific details NCR.** Source: S27. Result of corporate incorporation for applicable company types; a partnership certificate is a different output. eSPARC regular and OneSEC/ZERO paths use eSECURE/eSAP and digital issuance. Incorporators/signatories, purpose/company type, authentication and assessed payment vary. Amendment, a certified copy and continuing corporate compliance are not ordinary certificate renewal. **CMS:** audience/custom evidence fit; a generic individual-applicant form and one price/time block cannot represent all corporate paths. **Fix:** B2/B3; do not collect incorporator questionnaires. **Proposed mode:** Informational for this capstone; official service reference and inventory/requirement use retained.

**24. Business Permit (Mayor's Permit) — FAIL + NCR.** Sources: S28/S30, S31/S33 as related-service context. Santa Maria-specific new/renewal and applicable amendment/retirement procedures require its current BPLO charter. Sole proprietor versus corporation and business activity/location can change supporting evidence, inspections, assessment and offices. DTI/SEC, barangay clearance, fire/health/zoning inputs must be verified by case; do not turn them into a universal circular dependency. Exact fees, portal, appointment and turnaround are NCR. **Fix:** B2/B3/I3/I4/I8; assessed-fee notes, local path scope. **Proposed mode:** Guided only for documented local paths; otherwise informational/pending research.

**25. Barangay Business Clearance — FAIL + NCR.** Sources: S28/S30. Business-location clearance differs from personal Barangay Clearance and the municipal permit. Relevant business owner/entity/authorized representative, new/renewal, purpose/location and local collection arrangements need current barangay/BPLO evidence. Fees and processing vary; do not assume a separate barangay visit if locally integrated. **CMS:** references/evidence fit; office/path scope does not. **Fix:** B2/B3/I3/I8; preserve separate document identity without universal prerequisite claims. **Proposed mode:** Guided when verified.

**26. Fire Safety Inspection Certificate — FAIL + NCR.** Sources: S31/S32. BFP safety certification has business new/renewal and occupancy contexts; not the same as Fire Safety Evaluation Clearance. Owner/entity/representative, premises inspection, supporting assessment documents and resulting release differ by service. Online FSIS availability is documented elsewhere, but Santa Maria coverage and current rates are unverified; older percentage schedules must not be copied. **CMS:** can describe inspection and varied fee, but not scope an online/hybrid path to the appropriate office. **Fix:** B2/B3/I3/I8. **Proposed mode:** Guided only after local/current verification; otherwise reference.

**27. Sanitary Permit — FAIL + NCR.** Sources: S28/S33. Establishment-level permit, distinct from a worker's health certificate. Business/premises type, inspection and current local public-health requirements govern new/renewal and changed premises. Verify local worker-health evidence, laboratory/inspection costs, municipal collection and release. National IRR does not supply a current Santa Maria price/timetable. **CMS:** conditional evidence and variable fee notes fit; per-service local rules do not remain scoped. **Fix:** B2/B3/I3/I8. **Proposed mode:** Guided for verified municipal service; no medical eligibility automation.

**28. Building Permit — FAIL + NCR.** Sources: S28/S34. Project authorization involving owner/authorized applicant and professional plans/clearances, not a personal identity document. New works, alterations and revised plans need appropriate project context; duplicate copy is not a new construction authorization. Assessments, office jurisdiction and technical review differ. Current Santa Maria OBO checklist, electronic availability, fees and timeline are NCR. **CMS:** custom evidence/linked records can describe inputs; cannot certify every engineering case or universal fee. **Fix:** B2/B3/I3/I8; retain official technical-service referral. **Proposed mode:** Informational, not an automated construction-compliance roadmap.

**29. Certificate of Occupancy — FAIL + NCR.** Sources: S28/S34. Completion/use authorization distinct from Building Permit. Completion evidence, inspections and project-specific authorizations matter; partial occupancy/change of use require explicit current instructions if included. Owner/representative and issuing OBO service must be verified. No assumed annual renewal; a certified copy is distinct. Exact local fees/processing and request channels remain NCR. **CMS:** prerequisite references fit, but full project/service context is missing. **Fix:** B2/B3/I3/I8. **Proposed mode:** Informational with official office reference.

**30. Locational Clearance — FAIL + NCR.** Sources: S28/S35. Zoning/location suitability service; municipal versus DHSUD jurisdiction cannot be conflated. Owner/developer/authorized request, project/use/location and amendment/variance circumstances matter. Exact Santa Maria zoning charter, documentary alternatives, rates and timing were not recovered. **CMS:** free-text conditions can explain scope, but service/office/transaction context is missing. **Fix:** B2/B3/I3/I8; source the actual applicable jurisdiction. **Proposed mode:** Informational at present; guide only a verified simple local request if approved.

**31. Health Certificate for Workers — FAIL + NCR.** Sources: S28/S33. Worker-level health evidence/clearance, separate from an establishment sanitary permit or a generic medical certificate. Occupation/food-handling category, tests, training where applicable, validity, new/renewal and authorized local providers require municipal confirmation. Do not borrow another city's lab package or infer exemption of all lab costs from first-time-jobseeker status. **CMS:** custom evidence and audience fit; category/path-specific requirements and prices need scope. **Fix:** B2/B3/I3/I8. **Proposed mode:** Guided for verified municipal acquisition; no clinical advice or automated medical classification.

### Tax, Property, and Other Records

**32. BIR Certificate of Registration (Form 2303) — FAIL + NCR.** Sources: S36/S37. Tax-registration output for the applicable taxpayer/entity, not the TIN card or registration application form. Initial business registration, branch/facility, updates/correction, replacement and generated COR are different transactions. ORUS and office paths vary by taxpayer type. Current forms/checklists, permitted representatives, fee and timing must be verified; do not copy old annual-registration charges. **CMS:** linked evidence and descriptions fit; taxpayer/service/online generation scope does not. **Fix:** B2/B3/I3/B5. **Proposed mode:** Informational with verified retrieval/registration references, unless a narrow guided path is approved.

**33. BIR Tax Clearance Certificate (General Purpose) — FAIL + NCR.** Source: S38. Current circular uses Tax Clearance for General Purposes (TCGP); distinct from bidding clearance, TCVC and transfer-related certificates. Existing taxpayer, permitted purpose, relevant RDO/regional office, DVR, authorized signatory and eTCS support matter. Verify current fees, prerequisite issuance and processing per jurisdiction. Repeat clearance is not general taxpayer registration or an assumed annual renewal. **CMS:** typed prerequisites fit, but path and purpose scope are missing. **Fix:** B2/B3/I3/I4; keep exact product name/allowed uses. **Proposed mode:** Informational at this capstone scope.

**34. Community Tax Certificate (Cedula) — FAIL + NCR.** Sources: S28/S30. Certificate follows community-tax payment; individual/juridical and applicable exemption rules differ. Municipal collection and deputized barangay collection are not automatically equivalent available channels. Annual payment, reissue/copy, income/property-based assessment and penalties must not become one fixed acquisition fee. Exact local service checklist/current charges/timing remain NCR. **CMS:** a “varies” fee with sourced explanation is sufficient; no tax calculator is needed. **Fix:** B2/B3/I3/I8 and distinguish annual tax obligation from arbitrary document expiry. **Proposed mode:** Guided local service once verified.

**35. Real Property Tax Declaration — FAIL + NCR.** Sources: S29/S30. Assessor's record, not proof of land title and not a tax-clearance certificate. New assessment/transfer/reassessment/correction and certified-copy retrieval are materially different. Property owner/authorized requester, relevant property, evidence and jurisdiction matter. The official local ordinance confirms assessor functions but not service checklists/fees/timing. **CMS:** catalog record and references fit; variant request contexts and meaningful validity qualification do not. **Fix:** B2/B3/I2/I3/I8; label whether guiding issuance or copy retrieval. **Proposed mode:** Informational for assessment; Guided copy retrieval only after verification.

**36. Real Property Tax Clearance — FAIL + NCR.** Sources: S28/S30. Certification of relevant property-tax payment/compliance; distinct from BIR general-purpose clearance and Tax Declaration. Property/period, owner/representative, arrears/payment and treasurer service must be verified. Repeat/current-period clearance is relevant; exact local channels, fee, claim and timetable remain NCR. **CMS:** custom evidence fits; ownership of some Tax Declaration is not proof it matches this property. **Fix:** B2/B3/I3/I4/I8, manual property-match notice. **Proposed mode:** Guided verified retrieval, not automatic tax-compliance certification.

**37. Certified True Copy of Land Title — FAIL; exact current charges/coverage NCR.** Source: S39. Registry-certified copy, not replacement of the owner's duplicate title. Registry/local or other-office request and eSerbisyo delivery differ. Title/Registry details, identity, page count and electronic versus manual record handling affect request, price and time. Lost owner's duplicate is a different procedure and must not be mislabeled as this request. **CMS:** notes and variable/option fees partly fit; fulfillment and processing cannot stay bound to the selected method. **Fix:** B2/B3/I2/I3; no automatic title/property verification. **Proposed mode:** Guided copy request.

**38. PhilHealth Member Data Record (MDR) — FAIL.** Sources: S40/S41. Member record retrieval/view/print, not a new PhilHealth ID. Existing member/account access is a prerequisite for the online portal; office-assisted copy and profile correction must be distinguished from download. No mandatory physical visit/appointment for the verified portal path; do not invent a printing fee or delivery time. New membership is a separate process. **CMS:** free/empty office and custom steps can save, but no structured retrieval path and no resident Document detail. **Fix:** B2/B3/I2; show an official portal action and truthfully scoped help. **Proposed mode:** Guided retrieval.

**39. Pag-IBIG Member's Data Form (MDF) — FAIL + NCR.** Sources: S42/S43. A blank downloadable form and a populated/system-generated member record are different things. Membership registration, existing-member copy request and member-information changes need separate instructions. The official blank PDF does not establish online retrieval of a populated MDF. Agency response points to personal transaction channels; complete current branch requirements/fee/timing were not inspectable. **CMS:** custom evidence can fit, but retrieval/channel identity is absent. **Fix:** B2/B3/I2; do not promise a Virtual Pag-IBIG populated-MDF download without evidence. **Proposed mode:** Guided verified copy retrieval; unknown methods remain NCR.

**40. LTO Motor Vehicle Certificate of Registration (CR) — FAIL + NCR.** Source: S44. Vehicle-registration record, distinct from driver's license and Official Receipt; CR/CRE cases exist. Initial/dealer-linked registration, vehicle-registration renewal, record revision, ownership transfer and lost/damaged duplicate are separate transactions. Vehicle/owner/authorized entity, originating office and supporting records affect requirements; current fees and release rules need full charter verification. **CMS:** custom evidence and references fit, but personal inventory possession cannot verify the correct vehicle/title/encumbrance. **Fix:** B2/B3/I3/I4. **Proposed mode:** Informational, preserving requirement/inventory identity; no vehicle-compliance engine.

**41. Seafarer's Record Book (SRB) — FAIL for resident information; acquisition guidance N/A under proposed mode.** Sources: S45/S46. MARINA SRB is distinct from SID and historical SIRB. Specialized seafarer eligibility/training/service evidence, issuance, renewal and replacement must not be turned into a general-public checklist. Current appointment entry uses eGovPH from 13 May 2026; that does not imply fully remote issuance. Exact current fees, center rules and evidence remain NCR if detailed guidance is later wanted. **CMS:** `informational_only`, specialized audience, linked requirements and inventory are implemented; full Document facts/reference screen is missing. **Fix:** B1/B5 and retain hidden acquisition data without deleting it. **Proposed mode:** Informational Only; exclude as an acquisition target while keeping searchable/reference/owned-item matching.

## 6. Representative end-to-end stress records

These are the required acceptance fixtures, **not claims of completed manual encoding**. All six live workflows are currently NT. Use a dedicated disposable CMS test environment; create/edit through browser UI only. Do not use SQL, seeding, direct API writes or Artisan to prepopulate live trial records. Automated tests may use isolated fixtures.

| Record | Required variations in the same test | Why the current structure fails |
| --- | --- | --- |
| PSA Birth Certificate | Outlet copy; authorized provider delivery; emailed e-certificate; Viewable Online with outlet step; representative versus owner; no-record/annotation note | Must preserve provider, authority, fee/time, result format and exceptions per path. |
| NBI Clearance | Ordinary new/appearance; eligible renewal/delivery; first-time-jobseeker exemption; data-change/review notice | Different evidence, charge/waiver and visit requirements cannot be globally blended. |
| Business Permit | Verified Santa Maria new/renewal paths; individual versus corporation evidence; assessed fees; service-specific offices | Must avoid universal local rates, incorrect dependencies and fake completed inspections. |
| DFA Apostille | At least two eligible underlying-document types; applicable digital versus physical service; representative case | An OR choice of input documents must not become several mandatory prerequisite acquisitions. |
| PhilHealth MDR | Existing-member portal view/print; office assistance; correction referral | Portal retrieval must work with no forced office, appointment or made-up processing duration. |
| SRB | Informational record, searchable dependency and owned inventory; switch mode and back | Must hide acquisition UI/targeting while preserving data and exact matching; must not open a same-number Government ID. |

For each: browser encode → Save → leave/reopen → edit one value → Save → reopen again → inspect read-only API → open actual resident record through normal navigation → compare every section and context → verify no unrelated/default content. Capture expected/actual evidence and tested build/record identifiers. Use test-only labels, no personal information. If removing a fixture would delete irreversible data, use a disposable environment rather than deleting real catalog entries.

## 7. Minimum final CMS structure proposed for approval

**Keep six wizard steps.** Walang kailangang seventh wizard step. The missing piece is a shared context, not more unrelated forms.

### Step 1: Basic Information

Retain identity/agency/classification, description, eligibility notes, validity, availability/audience, guidance mode and sequencing inclusion. Add concise source/research-review information here. Treat research state and service availability separately. Add a validity qualifier so a permanent record is not confused with a receiving agency's recency requirement.

For Informational Only records, keep an official reference, audience, factual description and optional verified availability/validity. Do not force requirements, prices, guide steps or a physical office. Changing mode hides those sections in presentation and retains saved values.

### Step 2: Transactions and Requirements

Add a compact manager above the existing requirements editor:

1. **Transaction**: stable code plus human label, e.g. request/repeat copy, renewal, replacement, correction, authentication, retrieval, other. Keep current stored types and compatibility; use additive labels/mapping.
2. **Request path**: belongs to a transaction; name, channel (outlet/online/partner/hybrid), provider, safe official portal URL where relevant, fulfillment method(s), appointment policy (required/not required/conditional/unknown), and short applicability/exception notes.
3. **Requirements**: reuse current sets/groups/ways/items and typed references, now explicitly associated with the selected path/applicant context. Support a path with no additional documentary requirements; unknown is not a fake custom requirement.
4. **Prerequisites**: scoped to the applicable path, separate from evidence to bring. Keep custom manual actions and cycle protection. Display a clear explanation of whether alternatives/conditions are manual or actually evaluated. Avoid automatically creating graph edges from every accepted item.

Do not create all combinations of transaction × channel × applicant. Encode only verified meaningful paths. Reuse a shared requirement set only when it is actually the same; an override must be explicit so editing one path cannot silently change another. A generic existing record should map to a clearly named default/legacy path without inventing facts.

### Step 3: Fees and Processing

Retain existing fee types, options, optional extras, descriptions and notes. Make scope explicit: all applicable paths or selected path, with condition/office/applicant notes where necessary. Separate mutually exclusive service options from cumulative extras. Distinguish per-copy/per-page/unit charges in display and assessed/unknown costs from free. Do not automatically total options that cannot be combined.

Store processing/fulfillment estimates at the path level, with duration/range/varies, minutes/hours/working/calendar days, and a short timing basis such as “after complete application,” “after payment,” or “delivery after processing.” Keep old record summary fields for compatibility. No automatic calculator is required for local assessments.

### Step 4: Application Guide

Reuse rich-text steps, content blocks, reorder and safe links. Show the same selected transaction/path, its applicable checklist/fees/processing and source references. Make start/claim/download actions truthful. Keep special-case notices in structured, titled blocks within the path rather than mixing every case into one paragraph. Negative-result and corrective-process links may remain simple explanatory links; a generalized workflow engine is unnecessary.

### Step 5: Offices and Service Channels

Retain reusable physical offices/schedules, but associate the verified service with the path. An online path may link no office; a hybrid path may name the actual required counter. Store portal/provider details on the path, not as fake latitude/longitude-bearing offices. Record service-specific availability, source and checked date. Do not infer all services from agency membership or an office's existence.

### Step 6: Review

Retain saved-count overview, add a compact per-path preview of transaction, requirements, costs, timing, guide, fulfillment, sources and offices/N/A. Show draft/unverified/unknown clearly. An optional section can be hidden without clearing its saved content. Warn about incomplete applicable content, missing authority or contradictory paths; do not demand filler for N/A sections. A valid save is not proof of verified government instructions.

### Resident presentation

Use one shared detail renderer for Government IDs and Documents, with a typed identity and matching new Documents API. Keep existing API contracts additive. Search should surface document reference records as well as guided records; informational records remain excluded from acquisition targeting. Open linked documents from Requirements/Inventory/roadmap using their proper type.

For guided records, transaction/path selection is visible and consistent across Requirements, Costs/Processing, Guide and Offices. Changing channel must change the associated sections together. Show portal actions and fulfillment details; hide N/A map/offices/fees/steps. Render official sources and checked dates. Never use Passport/DFA sample content as fallback. Ownership indicators are distinct from verified suitability or actual completion.

For informational records, display factual reference content and notice, with a working official link and sources. No acquisition/readiness tabs. Exact owned-reference matching remains available.

### Data impact of the future proposal — not executed

Expect additive persistence for transactions/request paths, scoped associations, research sources/review state, presentation flags/notes and, if confirmed necessary, edit versions. Reuse existing checklists, fees, guides and offices rather than duplicating their systems. Existing IDs, typed references, ordering, child IDs, legacy summaries and saved research must survive. Unknown facts stay unknown. Backfill may attach existing content to a neutral default path; it must not fabricate verification, portal, availability or prerequisites.

Exact migrations/columns/foreign keys/defaults/cascade/rollback examples must be presented for approval before implementation, according to AGENTS.md. No migration was created or run in this audit. Never require deleting existing research to roll back a deployment; separate non-destructive app rollback from any later deliberate data migration.

## 8. One controlled implementation batch

**Yes — these shared fixes can be planned and delivered as one controlled batch, with internal checkpoints and one coordinated acceptance pass.** This is not a promise to make one giant unreviewable patch or apply migrations without approval.

Suggested order within that approved batch:

1. Agree transaction/path behavior with the six examples above and the 41-row matrix; freeze terminology and mode choices.
2. Design additive data mapping and backward compatibility; separately explain exact migrations before creating them.
3. Add path/scenario management, provenance, scoped requirements/prerequisites, fees/processing, guide/offices and Review to the existing six steps.
4. Complete typed Documents API and shared resident display; replace misleading prototype content.
5. Resolve regression failures, failure recovery and stale-edit behavior; update current setup/encoding docs.
6. Execute six representative browser round trips and all document-specific acceptance cases; freeze only after evidence passes.

Anticipated areas (not an authorization to edit them): Document/GovernmentId models and scoped relationships; the two wizard controllers; shared catalog validation and checklist/guide/prerequisite services; existing shared Blade/Alpine editors; API routes/projections; Flutter detail/model/service/navigation files; focused backend/JS/Flutter tests; additive migrations and current docs. Exact file changes should be listed in the implementation proposal before editing. No per-document custom implementation is recommended.

## 9. Exact release gates before inviting encoders

### Automated behavior gates

- Existing backend **491-test baseline** and CMS JS **98-test baseline** remain green; preserve their behavioral assertions.
- Full Flutter suite becomes green after explaining/resolving all **15 failures**; retain auth/account/setup/logout/navigation behavior coverage.
- Documents index/detail returns the actual saved description, eligibility, validity notes, sources, behavior, scoped requirements/fees/processing/guide/offices and stable order; no Government ID substitution on equal numeric IDs.
- Transaction/path create/edit/rename/reorder and repeated Save preserve identity. A changed path does not alter unrelated paths; guide references cannot adopt another record's data.
- AND/OR/choose-N, conditional evidence, linked ID/Document, custom evidence, copies and representative conditions round-trip with unchanged semantics.
- Conditional/alternative prerequisites do not silently become universal mandatory acquisitions. Cycles remain rejected; manual uncertainty remains visible.
- Fees: free versus unknown, fixed zero, range, assessed/varies, fee options, per-copy notes, optional extras and path scope. Costs from different alternatives are never added together.
- Processing: minutes/hours and working/calendar units; processing versus delivery; unknown/N/A; per-path values. Document validity versus digital access window versus receiving-agency recency are separate.
- Online-only saves without fake offices; hybrid retains required outlet steps; office-service scope and source metadata survive reopening.
- Informational SRB remains searchable/referenceable/ownable; not an acquisition target; no acquisition tabs; toggling guided/informational does not destroy data.
- Hidden/N/A sections retain content, stay hidden across API/rendering, and restore when reenabled. Draft/unknown is not rendered as verified/available guidance.
- Source checked date is not auto-filled by ordinary content editing; URLs are safe; user-entered text/rich text is safely rendered.
- Validation failure, expired login (419), forbidden/deleted record, network failure and duplicate Continue preserve drafts and do not claim success. Test combined checklist/prerequisite failure explicitly.
- Two-editor stale save test, against an isolated MySQL test database if needed, either rejects stale overwrite or documents an approved safe workflow. SQLite locks are not evidence for this.
- At least one realistic long record tests nested payload sizes, accepted-option lists, fees, guide blocks and server form/input limits; errors must be explicit and non-destructive.

### Browser acceptance gates

Run all six records in section 6 through **actual** browser UI → save → reopen/edit → read API → actual running Flutter. Test 320px narrow layout and increased text size, keyboard interaction, portal link behavior and empty/error/retry states. No fabricated guide, office, address, timing or readiness claims may appear. Test a second transaction/channel and switch back to prove context is preserved.

### Content gates for all 41

Each record must have a reviewed official name, scope, proposed guidance mode, availability basis and source/checked date. Verify every encoded path's requester authority, requirements/alternatives, fee basis, timing/fulfillment, office/provider and special cases. Unknown facts remain NCR; such paths cannot be approved as complete actionable guidance. Never translate another LGU's charter into Santa Maria facts. No need to fully research hidden acquisition guides for intentionally Informational Only records; their factual reference content still needs sources.

Specific content assertions by catalog number:

| Documents | Required assertion |
| --- | --- |
| 1–3 | Copy retrieval differs from event registration/correction; physical/electronic/view-only paths and release authority remain distinct. |
| 4–5 | CENOMAR and Advisory on Marriages are separate possible outcomes; neither is relabeled as a marriage certificate. |
| 6–7 | CENODEATH and Advisory on Deaths are distinct outcomes; actual issuance/channel evidence is recorded. |
| 8 | Ordinary/new, renewal/delivery, FTJ waiver and review/data-change cases stay separate. |
| 9 | Resolve current versus older ID-count instructions; nonappearance renewal does not require a fake office visit. |
| 10, 12–14 | Verify actual barangay service; personal clearance, residence, indigency and FTJ status remain separate. |
| 11 | Resolve historical fee versus waiver; certification is not registration/ID issuance. |
| 15 | Letterhead versus security-paper/authentication and earned eligibility remain distinct. |
| 16–18 | Passing, rating and PRC good standing remain separate; no professional-association certificate substitution. |
| 19–20 | NC versus COC and qualification/assessment versus retrieval remain distinct. |
| 21 | Applicable underlying document/destination determines the path; digital result/physical authentication and costs are not conflated. |
| 22–23 | Sole-proprietor name registration differs from corporate incorporation and authority to operate. |
| 24–27, 30–31 | Local new/renewal/service jurisdiction, inspections, assessed charges, worker versus premises certificates are explicit. |
| 28–29 | Permit to build differs from occupancy authorization; no personal-ID-style universal guide. |
| 32–33 | COR differs from general-purpose tax clearance and from bidding/transfer products. |
| 34–36 | Cedula, assessor declaration and property-tax clearance remain separate, with local/property-specific qualifiers. |
| 37 | CTC request is not replacement of a lost owner's duplicate title; manual/digital handling and channel fees/times stay scoped. |
| 38–39 | Existing member retrieval differs from new membership or blank-form download; no unverified online download promise. |
| 40 | Initial MV registration, renewal, revision and duplicate CR are separate; OR/CRE are not silently treated as CR. |
| 41 | SRB differs from SID/SIRB; informational mode preserves reference/inventory use and current official channel reference. |

## 10. Requested final decisions

**A. Ready for all 41? NO.** Core storage/editor behavior is promising and passes its existing tests, but channels/scenarios, source provenance and resident Document delivery are missing; misleading prototype content remains; live acceptance has not run.

**B. Exactly what must be fixed first?** B1–B5: typed Document detail delivery; structured transaction/request paths; accessible scenario management; real guide/office rendering; sources/review metadata. Include I1–I9 accuracy, applicability, save/recovery/team-edit and regression/operational fixes as the same approved acceptance scope. Resolve NCR content before marking any affected path complete.

**C. One controlled batch? YES,** with the internal sequence and gates above, preserving existing content and API compatibility. Exact schema and file plan still require approval before implementation.

**D. What can wait?** Calculators, automatic eligibility/optimal ranking, advanced live maps/queues/tracking, personal document upload/storage, and unrelated UI/auth redesign. Detailed acquisition guides can wait for records explicitly approved as Informational Only. Unknown content may wait in draft; it may not be presented as verified actionable guidance.

**E. What must pass?** Existing regressions plus the explicit automated, six-record live end-to-end, concurrency/recovery, and all-41 content assertions in section 9. **A structure freeze is not approved until the previously NT browser round trips pass.**

Stop here for approval of the consolidated CMS development plan. No feature implementation is included in this audit.
