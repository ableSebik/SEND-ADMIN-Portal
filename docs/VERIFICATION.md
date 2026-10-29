# Verification evidence

Latest roles/vehicles/users update: **44 tests passed, 456 assertions**, with a subsequent targeted role/vehicle check. Drivers have only Trips in the sidebar; all roles read the fleet, only Super Admin/Admin write vehicles, drivers select any available vehicle without licence verification, and user accounts are edited through usernames with four roles. Isolated Playwright/Edge browser checks passed without page errors; desktop/mobile screenshots were inspected with view_image. See [current access rules](ACCESS.md). Screenshots: [driver sidebar](ui/driver-trips-only.png), [vehicle form](ui/vehicle-reduced-form.png), [user list](ui/users-click-to-edit.png), [four roles](ui/user-four-role-form.png), [mobile](ui/user-four-role-mobile.png).

Latest access update: Super Administrator/superuser can access all modules and records, open trips for a selected driver, and complete pending trips. Independent approval and recipient acknowledgement remain enforced. **37 tests passed, 327 assertions**; isolated browser checks passed. See [access rules](ACCESS.md). This supersedes the earlier Super Administrator trip-creation restriction.

## 27 October 2026 update

Final automated run: **32 tests passed, 208 assertions**. Blade template compilation and route caching passed.

MFA has been removed and passwords now require eight characters across initial setup, user creation, reset, change and CLI provisioning. Driver Logs use two phases: driver-only opening (fixed authenticated driver, Department/Project, From, Destination, departure and Mileage Before) creates an amber Pending trip; the original driver or a Super Administrator completes Mileage After and an editable prefilled return time to mark it green Completed. Old trip approval/action endpoints cannot bypass these rules. Existing trip records are retained and pending legacy approval requests are marked Superseded.

Browser verification used Playwright with installed Edge against an isolated QA SQLite database, leaving operational data untouched. Verified eight-character sign-in without MFA, readonly driver, no Department input or closing fields on opening, amber Pending, editable current return time, calculated distance, green Completed, non-driver creation denial, desktop and 390-pixel mobile layouts, and no browser page errors.

Screenshots: [opening](ui/trip-opening-desktop.png), [pending register](ui/trip-pending-desktop.png), [closing](ui/trip-closing-desktop.png), [completed record](ui/trip-completed-desktop.png), [mobile opening](ui/trip-opening-mobile.png). Screenshots were inspected with view_image; checked field labels, readonly identity, phase separation, status colors, button visibility and mobile overflow.

Logo variants: app/public/images/logo.png is displayed in full on sign-in and initial setup, and app/public/images/logo-mark.png is used in the sidebar. Both retain transparency and original proportions, with no cropping or added white background. Browser checks verified desktop/mobile full-logo rendering without overflow and the rendered sidebar mark component. Screenshots: [desktop full logo](ui/logo-full-desktop.png), [mobile full logo](ui/logo-full-mobile.png), [sidebar mark](ui/logo-mark-sidebar.png).

## Historical initial-build evidence (superseded where noted above)

## Automated checks

The final development test run passed **20 tests / 81 assertions**. Tests use an isolated in-memory database, not the local operational database. PHP source was formatted with Laravel Pint. Blade template compilation and route caching were checked.

Coverage includes unauthenticated access, login throttling, privileged MFA enrollment and encryption, invalid MFA challenges, disabled accounts, independent vendor/bank decisions, rejected bank changes, encrypted approved banking, unauthorized record/download/export access, IT privilege escalation, permitted and rejected uploads, duplicate registrations, asset assignment/acknowledgement, trip conflicts and invalid mileage, stale versions, proposed record edits, and authorized page rendering.

These are development checks. Production MySQL concurrency, load testing, backup restoration, SMTP delivery, SSO and external security review are not certified by this run.

## Browser checks

Browser plugin not available; Playwright used installed Microsoft Edge in headless mode. The flow was first-run setup → initial administrator → authenticator enrollment → dashboard → vendor creation → search → self-approval restriction → mobile navigation. No browser page errors were observed. Mobile document width did not exceed the viewport.

- Desktop: 1536 × 1024 viewport; full-page screenshot includes footer below the viewport.
- Mobile: 390 × 844 viewport; full-page screenshot verifies stacked content and collapsed navigation.
- [Design concept](ui/dashboard-concept.png)
- [Desktop render](ui/dashboard-desktop.png)
- [Mobile render](ui/dashboard-mobile.png)

The concept and both final render screenshots were inspected with `view_image`.

## Fidelity ledger

| Comparison | Evidence / outcome |
|---|---|
| Layout | Preserved fixed 240 px navy rail, white workspace, header, four summary tiles, two-column activity/attention region and bottom quick links |
| Palette | Navy identity, teal actions/icons, white surfaces and pale table headers retained |
| Typography | Clear heading/body/control hierarchy; implementation uses local Segoe UI rather than downloading a web font |
| Component geometry | Bordered panels, restrained rounded corners, icon tiles, tables and quick links match the concept's component family |
| Data and copy | Live record counts replace concept labels that implied active/available filters; current date replaces the concept's illustrative date; real audit rows replace the empty activity state |
| Navigation | Vehicles and Driver Logs are separate functional registers; Notifications is included; a nonfunctional Settings link is omitted |
| Account controls | Security and Sign out are visible working controls; decorative account dropdown is omitted |
| Mobile | Sidebar collapses behind a working toggle; summaries form two columns; panels stack; tables scroll within their own region |

An initial escaped Blade end-section directive caused a stray action label and unclosed buffer. It was fixed and the risky test warning disappeared. The copy check found no remaining raw Blade directives in the verified dashboard. Above-the-fold departures are the deliberate current-date, live-label, navigation and account-control differences listed above.

The implementation was visually verified against the concept for structure, palette, typography hierarchy, component treatment and responsive behavior. The ledger records intentional differences rather than claiming pixel identity.

Disposable browser-QA accounts and records are removed through scoped cleanup; the user's application starts with local first-run setup and no shared default credentials.

## 28 October: navigation, audit, trip notes and vehicle deletion

Implemented a persistent desktop sidebar collapse control and mobile drawer with close/Escape/outside-click controls. The header now has a username/chevron menu containing Account and Sign out. Account provides the password change form. Trips uses a route/trail and location-pin symbol.

Audit Trail supports Account, Vehicle, Trip, Asset and User categories, optional inclusive From/To dates in Africa/Accra, Reset and pagination preserving filters. Account returns only Signed in and Signed out events. Existing events were categorized during the additive migration.

Trip details support timestamped additional notes from the assigned driver or Super Admin without overwriting opening details. Operational history is hidden on trips. Trip document uploads omit expiry and always store a null expiry; drivers can upload/download documents on their own trips.

Super Admin alone can delete a vehicle after entering their current password and a server-generated word. The session challenge expires after ten minutes; stale versions and unfinished trips prevent deletion. Deleted vehicles leave the active list and selectors, while completed trips can still resolve vehicle names. Vehicle registration uniqueness includes retained records and returns validation errors for duplicates.

### QA report

Environment: disposable SQLite database at loopback port 8001, Playwright using installed Microsoft Edge, desktop 1440×1000 and mobile 390×844. Browser plugin not available. Production/user records were not used for destructive QA.

Flow: sign in → collapse/expand sidebar → username menu → Account/password form → Audit category/date filters → add trip note and inspect document fields → password/word vehicle deletion → mobile drawer open/close → username menu sign out.

| Check | Result |
|---|---|
| Page identity and meaningful content | Pass |
| Blank page / framework error overlay | None in completed QA |
| Browser console and page errors | None |
| Sidebar desktop collapse and mobile open/close | Pass |
| Account menu, password form and sign out | Pass |
| Account/vehicle audit and date filters | Pass |
| Trip note persists; history and expiry absent | Pass |
| Super Admin password/word deletion | Pass |
| Regression suite | 49 passed, 524 assertions |

Commands: `php artisan migrate --force`, `php artisan test`, targeted Pint formatting, temporary Playwright interaction script. The initial QA server needed elevated filesystem access for Laravel session/template caches; restarting it with that access resolved its 500 response. An initial mobile screenshot caught the sidebar transition mid-animation; subsequent captures wait for the final drawer position. Mobile heading actions were wrapped to avoid clipping.

Evidence (all disposable QA data): [desktop collapsed sidebar](ui/sidebar-collapsed-desktop.png), [filtered audit](ui/audit-filtered-desktop.png), [trip additional notes](ui/trip-additional-notes.png), [vehicle deletion confirmation](ui/vehicle-delete-confirmation.png), [mobile sidebar](ui/sidebar-toggle-mobile.png), [mobile account menu](ui/account-dropdown-mobile.png).

Remaining coverage limits: browser interactions were verified in Edge at these two viewport sizes; other browser engines were not tested. HTTP feature tests cover unauthorized deletion, wrong password/word, expired challenges, stale versions, unfinished trips, retained completed history, trip note authorization, private trip documents and audit date validation.

## OSO office and sidebar verification (28 October)

Summary: renamed the user-facing Driver role to Operation Support Officer (OSO), added fixed account department/office dropdowns, enforced OSO trip vehicle eligibility by office, and centered the collapsed sidebar logo/icons while hiding its SG footer.

Environment: isolated disposable SQLite database; http://127.0.0.1:8001; installed Edge through Playwright because Browser plugin is not available. Viewports 1440×1000 and 390×844. No live records were altered.

Flow: Super Admin sign in → collapse sidebar → verify logo/icon centers and hidden footer → Add User dropdowns/OSO role → Accra OSO sign in → own-office trip vehicles → mobile form → Tamale OSO sign in → own-office trip vehicles.

| Check | Result |
|---|---|
| Intended page identity and meaningful content | Pass |
| Blank-page/framework overlay | None |
| Console/page errors | None |
| Sidebar logo and every icon centered within 1 px | Pass |
| SG sidebar footer hidden when collapsed | Pass |
| Department/office exact options and OSO label | Pass |
| Accra/Tamale OSO vehicle option isolation | Pass |
| Mobile trip form layout | Pass |
| Full PHP test suite | 52 passed, 571 assertions |

Automated HTTP tests additionally verify cross-office direct POST rejection, successful same-office trip creation, rejection after office changes, blank-office rejection, fixed department/location validation on create/edit, unchanged Super Admin fleet access and existing trip/vehicle/user permissions. Existing password/account tests were updated to use the newly required department choices. Commands: targeted `vendor/bin/pint`, `php artisan test`, disposable Playwright interaction script.

Evidence: [centered collapsed sidebar](ui/oso-centered-sidebar.png), [account dropdowns](ui/oso-account-dropdowns.png), [Accra OSO vehicles](ui/oso-accra-vehicles.png), [Tamale OSO vehicles](ui/oso-tamale-vehicles.png), [mobile trip form](ui/oso-trip-mobile.png). Mobile captures wait for the sidebar resize transition to finish. Other browser engines and viewport sizes were not tested.

## 29 October: asset import foundation

Implemented normalized asset tables, a separate reference seeder, bounded XLSX reader, default dry-run CLI importer, private admin upload/preview/commit and reports. Added a read-only imported register with URL filters, partner tags, source details and filtered CSV. Existing asset records/workflows remain unchanged.

The full supplied workbook imported as 440 distinct assets, 13 held by partner, eight unknown costs and 1,105 exception flags. A second run created zero assets. The GCCP sheet has 70 tablet rows / 69 distinct serials, so the brief's 67-count criterion is not asserted. Cost is row-specific per the user's correction; Price-only costs are provisional; unknown donor mappings and depreciation stay unset. Printed total comparisons remain explicitly unreconciled.

QA environment: isolated SQLite at http://127.0.0.1:8001, installed Edge with Playwright because Browser plugin is unavailable. Desktop 1440×1000 and mobile 390×844. Flow: login → Asset Register → Imported register → partner filter → source detail → full workbook upload/preview → commit repeat → search → filtered CSV download → mobile layout.

| Check | Result |
|---|---|
| Page identity / meaningful content | Pass |
| Blank page / framework overlay | None in completed QA |
| Browser console / page errors | None |
| 440 assets and 13 partner records | Pass |
| Full workbook preview and repeated import | Pass; zero new assets on repeats |
| Filtered CSV download and mobile register | Pass |
| Full regression suite | 60 tests, 633 assertions passed |

An initial action-template directive adjacency issue was corrected; the full regression suite and rendered asset pages then passed. XLSX tests also found a permissive date-format parser, corrected to select strict year-first or day-first formats before parsing.

Evidence: [imported register](ui/asset-imported-register.png), [partner/source detail](ui/asset-partner-source.png), [import preview](ui/asset-import-preview.png), [mobile register](ui/asset-register-mobile.png). Commands: `php artisan assets:import`, `php artisan test`, targeted Pint, disposable Playwright script. Other browser engines and viewports were not tested.

This is Phase 1 plus read-only inspection, not completion of the entire asset brief. Detailed setup, cost policy, reconciliation interpretation and remaining phases are in `assets/README.md`. Raw reports contain source data and remain local/private.

## Workbook import removal — 29 October 2026

Removed both Import workbook buttons, all `/assets/imports` routes, upload/preview/commit/report controller and templates, `assets:import` CLI command, importer and XLSX reader. The prior import stays reverted; this removal makes no database changes and preserves source/audit history. Existing asset entry and lifecycle workflows remain available. Regression checks confirm import URLs return 404 for every role and the CLI command is absent. Full suite: 54 tests / 598 assertions passed. The removed import tests were replaced by feature-removal regression checks.

## 29 October: shared manual asset groups

Implemented the requested **Add Asset → Type → Details** flow for Motor Vehicle, Office Equipment and Furniture, Fixtures and Fittings. The screenshot field union is stored in `asset_details`, keyed to existing asset records. The additive migration backfills old asset metadata without rewriting original records. Office and Department are validated dropdowns; Project/Donor fields and columns are present. Origin is vehicle-specific. Price zero is valid. Existing approvals/handover rules remain enforced and the detail projection updates only from saved/approved record values.

The new table scrolls within its own region and includes row number/year, the screenshot fields, Type, Project, Donor, Department and Status. Type/Project/Donor filters combine; Office/Status/search and pagination retain URL state. CSV uses the same filters and escapes formula prefixes. Workbook import has not been restored.

QA: installed Edge via Playwright (Browser plugin not available), isolated SQLite server http://127.0.0.1:8001, desktop 1440×1000 and mobile 390×844. Flow: sign in → Add Asset → choose each of three types → complete/save each form → inspect saved detail → shared table → combined Type/Project/Donor filters → CSV download → mobile form/register. Three QA assets only; no live records added during browser QA.

| Check | Result |
|---|---|
| Page identity / nonblank content | Pass |
| Framework error page | None |
| Console / page errors | None |
| All three asset-entry forms and persistence | Pass |
| Dropdown offices and vehicle-only Origin | Pass |
| Shared table and combined filters | Pass |
| Filtered CSV and mobile containment | Pass |
| Regression suite | 60 tests / 669 assertions passed |

Commands: `php artisan migrate --force`, targeted Pint, `php artisan test`, temporary Playwright flow. Original generic asset fixtures were updated for the required type/year/price and canonical department. The existing approval and acknowledgement checks pass with the new payloads. Other browser engines were not tested.

Evidence: [type step](ui/asset-type-step.png), [motor vehicle form](ui/asset-vehicle-form.png), [shared register](ui/asset-shared-register.png), [combined filters](ui/asset-filtered-register.png), [mobile furniture form](ui/asset-furniture-mobile.png), [mobile register](ui/asset-group-register-mobile.png). User workflow and storage details: `assets/MANUAL-REGISTER.md`.
