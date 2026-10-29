# Current roles and access

The application now offers exactly four roles: **Super Admin**, **Driver**, **Admin**, and **Officer**. Admin is the IT/admin role for vehicle management; there is no separate IT role in the role selector.

| Capability | Super Admin | Admin | Officer | Driver |
|---|---|---|---|---|
| Read the shared vehicle list and vehicle details | Yes | Yes | Yes | Yes |
| Add/edit vehicles and vehicle documents/history | Yes | Yes | No | No |
| Select any available vehicle when opening a trip | Yes, on behalf of a driver | Cannot open trips | Cannot open trips | Yes |
| Read Trips | All | All | All | Own trips |
| Close pending trips | Any trip | No | No | Own trips |
| Manage users | All accounts | Driver/Officer accounts | No | No |
| Vendor/asset record entry | Yes | Yes | Yes | No |
| Approval decisions and audit access | Yes | Yes | No | No |

Drivers land on Trips after sign-in and have only **Trips** in the sidebar. The logo, Security link and Sign out remain part of the application shell. The shared vehicle list remains readable through its URL even though it is not a driver sidebar item. Every driver sees all available vehicles in the trip vehicle selector; a default vehicle-driver assignment does not restrict selection.

## Vehicle record

The list button is **Add vehicle**. Add/edit forms contain vehicle name, registration, office/location, chassis/VIN, optional default driver and notes. Department, category, acquisition cost and the additional-information section are removed from vehicle forms and detail views; exports also use the reduced vehicle columns. Previously stored values are retained for history rather than deleted. Vehicle changes are saved by Super Admin or Admin with a version check and audit event. Other users cannot alter metadata, upload vehicle documents, or add vehicle operational entries through direct requests.

## User management

The Users page has an **Add User** button at the top. Clicking an editable username in **Organisation accounts** opens that account's edit page. The former account-authorization panel is removed. Super Admin can edit all accounts; Admin can edit Driver and Officer accounts and cannot elevate them to Admin/Super Admin. Account updates require the acting administrator's password, revoke the target's other sessions, and are audited. Self-disabling/demoting the active Super Admin remains blocked.

Driver licence-expiry verification is removed from user forms and trip eligibility checks. Historical licence values remain stored but are not consulted. Active account/Driver role, available vehicle, trip conflict, mileage and version checks continue to apply. Independent approval and recipient handover acknowledgement remain enforced.

## Existing accounts

The role migration preserves accounts, identifiers, passwords and history. Legacy Super Administrator spellings map to Super Admin; IT Administrator, Administrative Manager and Management map to Admin; Administrative Officer, Staff, Finance and Auditor map to Officer; Driver remains Driver. Legacy spellings are recognized for compatibility but are not selectable when creating or editing users.

## Verification

The full suite passed 44 tests with 456 assertions, followed by targeted role/vehicle tests after hiding the Officer's vehicle-creation dashboard shortcut. Browser checks used Playwright with installed Edge against an isolated QA database and verified driver-only navigation, shared fleet selection without licence verification, Admin vehicle creation/editing, username-based user editing, the four role options, Officer read-only vehicle access, and mobile layout. No browser page errors were observed.

## Navigation and record-history controls (28 October)

All authenticated users have a sidebar toggle and an Account/Sign out dropdown. Drivers retain Trips-only sidebar navigation and can access their password form through Account. Audit access remains limited to Super Admin and Admin; filters do not widen record or audit permissions.

Only Super Admin can request or perform vehicle deletion. Password and session-bound random-word confirmation are required, with a ten-minute expiry, request throttling and record-version checks. Pending trips block deletion. Soft deletion removes the vehicle from active registers and trip selectors but preserves completed-trip references and audit history.

Additional trip notes are append-only, timestamped and attributed to the author; only the assigned driver and Super Admin may add them. Trip document uploads have no expiry field and ignore supplied expiry values. The assigned driver may upload/download their own trip documents. Other module document and history controls remain in effect.

Verification: 49 tests / 524 assertions passed; desktop/mobile Edge interaction QA passed without console/page errors. See VERIFICATION.md for the tested flows and evidence.

## OSO terminology, user options and office eligibility (28 October)

The former Driver role is displayed as **Operation Support Officer (OSO)**. Existing role assignments and trip ownership remain compatible; the stored `driver` identifier is retained internally. Trip forms/details, vehicle default assignment, account role labels and trip CSV headings use OSO terminology.

User create/edit forms restrict Department to HR/Admin, Programs or Finance, and Office / location to Accra or Tamale. Both fields are validated server-side. Historical values are retained until edited; edit forms show an existing out-of-list value and require selection from the approved list.

An OSO can open a trip only with an available vehicle in their own office / location. The form filters the vehicle options and submission checks the current office again against the locked account and vehicle. Posted location/OSO overrides cannot bypass eligibility. Blank offices cannot select vehicles. All vehicles remain readable according to the existing shared fleet access rules. Super Admin retains unrestricted fleet access when opening on behalf of an OSO.

Collapsed desktop navigation centers the logo and every icon within the rail and hides the entire SG footer. Expanded navigation retains its organization footer.

## Manual grouped assets

Asset creation and editing retain existing create permission (Super Admin, Admin and Officer); OSO cannot create assets. The Asset Register uses required Type and Office dropdowns, common group detail storage, and URL-based Donor/Project/Type filters. Asset edits still require independent approval and custody still requires recipient acknowledgement. Workbook import remains removed. See assets/MANUAL-REGISTER.md for the current manual workflow.
