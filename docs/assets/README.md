> **Feature removed — 29 October 2026:** Workbook import has been removed at the user's request. Upload/preview/commit/report routes, import buttons, CLI command and import/parser implementation are deleted. The previous import is reverted. Existing schema, source evidence and audit records remain for historical integrity. The instructions below describe the earlier implementation and are no longer executable.
# Asset register: import foundation (Phase 1)

Implemented in the existing Laravel 12 / Blade / SQLite application. Existing users, roles, assets in `records`, approval requests and acknowledgements are preserved. New normalized assets are stored separately, with an optional legacy record link reserved for later integration.

## Open in the application

Sign in as Super Admin or Admin. Open **Asset Register → Imported register** to inspect imported assets, or **Import workbook** to preview another XLSX upload. Import previews retain no database changes. The confirmation button imports the exact previewed file, with a 30-minute session-bound expiry and checksum check. Officer may read/export normalized assets; OSO cannot access these pages.

Current imported records are read-only. Existing create/edit/assignment/disposal forms continue to operate on the original register. Do not add the same assets there again. Unifying manual entry and lifecycle workflows with normalized records is Phase 2.

## Confirmed rules

- Cost is row-specific, **not a batch total**. Never multiply, spread or forward-fill it.
- If Cost is present, it is the provisional row unit cost. When only Price exists, it is a flagged provisional fallback. Price/Cost differences are reported. Unknown amounts are null, not zero.
- Money is rounded to cents per asset. Printed totals can differ through tax, rounding, missing values, duplicate source rows and overlapping subtotal/balance scopes. No reconciliation is certified automatically.
- Partner-held records remain, with `held_by_partner` visible in the register and detail page.
- Donor/project relationships stay blank until confirmed. Project names and provenance are retained. Depreciation is disabled; useful lives and residual policies are unset.
- No automatic correction of missing, malformed or duplicated tags. Raw values remain in provenance and exceptions.

## Results from the supplied workbook

Live normalized import #1 created **440 assets**, including **13 held by partner**, **8 unknown-cost assets**, and **1,105 exception flags** across source rows. Exception counts count issues, not distinct assets. A repeat dry run creates **zero new assets**. Existing legacy records were not modified.

The source GCCP sheet contains **70 tablet rows and 69 distinct serials**. Serial `351506550771217` appears twice. All repeated project rows are retained as provenance; distinct GCCP tablet assets total **69**, not the brief's anticipated 67. The importer does not delete records to force that number.

Known imported cost is **GH¢ 5,553,215.77**, strictly provisional. Eight unknown costs are excluded. Notable flags: 286 malformed tags (including project segments outside the required generator format), 260 Price-only rows, 183 unmapped supplier rows, 165 missing serial rows, 32 Price/Cost differences, 17 duplicate conflicts, eight duplicate-tag flags, and shifted location/custodian rows requiring review. Some flags repeat across master/project sheets.

Reconciliation files:
- `import-first-run.json`: committed first-run report, full exceptions, per-sheet classifications/skipped rows and printed-total differences, per-project unique-asset totals.
- `import-repeat-preview.json`: repeat run with zero new assets.
- `import-dry-run.json`: initial exploratory dry-run report; first-run/repeat reports above are authoritative for final code.

Reports contain source data and must remain private. The source workbook is copied to private storage at `app/storage/app/private/asset-sources/SEND-assets-2026.xlsx`; the original E: file is unchanged.

## CLI

Run from the `app` directory:

```powershell
C:\xampp\php\php.exe artisan migrate --force
C:\xampp\php\php.exe artisan db:seed --class=AssetReferenceSeeder
C:\xampp\php\php.exe artisan assets:import "C:/path/register.xlsx" --report="C:/path/preview.json"
C:\xampp\php\php.exe artisan assets:import "C:/path/register.xlsx" --commit --actor=1 --report="C:/path/import.json"
```

Choose the ID of an existing active Admin/Super Admin for `--actor`. CLI defaults to dry run. The separate idempotent reference seeder is also called inside the import transaction. Dry-run seeding rolls back with all other preview writes. Migrations themselves contain no seed data and can be reversed.

## Import behavior

The XLSX reader uses PHP PharData and SimpleXML, so no Python or spreadsheet server dependency is required in production. It reads cached cell values only and never executes formulas or macros. Numeric lexical values are preserved to avoid truncating long serial/invoice numbers. XML external declarations/DTD are rejected; archives are read without extraction. Upload, expanded XML and row limits are enforced; at most columns A–P are consumed. Formula cells without cached values become exceptions.

Master sheets are processed before project views. Rows are classified as project/year/header/asset/summary/blank/unknown; headers forward-fill project/year, while valid purchase dates determine purchase year. Motor rows use the vehicle column layout, including identifiable motor rows embedded in project sheets. Shifted location/custodian rows are quarantined instead of silently repaired. Unknown categories, locations and suppliers remain reviewable.

Deduplication prioritizes normalized serial, then tag, then a conservative matching fingerprint for project views. Distinct serials sharing a tag are separate assets; the conflicting asset has no assigned tag and retains the original in raw provenance. Ambiguous fallback matches are quarantined. Repeated imports attach a new source batch without overwriting earlier/manual asset values. Conflicts retain the master/earlier record and log both alternatives.

Canonical reference tables, raw source rows, components, exceptions, import batches, audit entries and tag counters are normalized. A tag reservation service uses a transaction and locked counter, accounts for existing sequences including leading-zero tags, and never reuses reserved numbers. UI tag assignment comes in Phase 2.

## Inspection and data quality

List filters combine through the URL: search, category including immediate children, donor/project, year bounds, location/custodian/supplier, status/condition, cost bounds, held-by-partner and missing-tag/serial/zero-cost/open-exception flags. Filtered CSV uses the same query and escapes spreadsheet formula prefixes. Details show source references and exceptions. The import report shows first 100 exceptions; authenticated JSON download includes all. Correcting mappings/exceptions and reprocessing rows is Phase 3; these controls are not claimed as complete here.

## Verification and remaining phases

60 automated tests / 633 assertions pass, including fixture row/date/money/tag parsing, rollback-only previews, idempotence, partner custody, row Cost semantics, duplicate tags, components, authorization, upload/commit, tag reservation, reversible schema and CSV injection protection. The full source was imported twice in an isolated QA database; the second run created zero new assets. Desktop/mobile Edge checks passed for full workbook preview/commit, imported list, partner filter/detail, search, filtered CSV download and responsive layout, with no browser console or page errors.

Phase 2 remaining: unified manual create/edit and lifecycle workflows, assignment history integration, independent transfer/disposal approval, bulk actions/bulk add, tag assignment UI, saved views, column chooser, Excel export and richer detail tabs. Phase 3 remaining: dashboards, complete PDF/Excel reports, data-quality editing/reprocessing, policy-controlled depreciation and stocktakes. Dashboard counts still reflect the legacy `records` module until integration. Do not treat this foundation as the complete feature set in the brief.

## Import reversal — 29 October 2026

At the user's request, import #1 was marked REVERTED and all 440 unchanged imported assets were soft-deleted from the active normalized register. Zero active imported assets remain. No assignments or post-import changes existed. The original legacy register and source workbook are unchanged. Source provenance, exceptions, original reconciliation reports and per-asset IMPORT_REVERTED audit entries are retained as historical evidence. The results above describe the original import, not the current active inventory.

