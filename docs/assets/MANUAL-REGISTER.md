# Manual Asset Register

The current Asset Register at `/records/assets` supports three groups in one shared detail table: Motor Vehicle, Office Equipment, and Furniture, Fixtures and Fittings. Workbook import remains removed and the earlier import remains reverted.

## Add an asset

Choose **Add Asset → Type → Continue → Asset details → Add Asset**. Office / location is required and limited to Accra or Tamale. Department is required and limited to HR/Admin, Programs or Finance. Project and Donor are optional text fields; saved values populate the register filter dropdowns.

The detail form and table include the supplied screenshot fields: Year, Name of Asset, Description, Office ID / Registration, Date of Purchase, Supplier, Model / Make / Year of Manufacture, Serial / Chassis, Office / Location, Staff Responsible, Invoice and Price in GH¢. Origin appears in the Motor Vehicle form. The table also includes a view row number, Type, Project, Donor, Department and Status. It scrolls horizontally to preserve readable columns on small screens.

Staff responsible can name a person or team. It records the descriptive register value; formal assignment/custody still uses the existing approval and recipient-acknowledgement workflow. Motor Vehicle assets are asset inventory records; this does not automatically create or change a vehicle in the operational fleet module.

## Data and approvals

`asset_details` stores group-specific columns for all three types with a unique foreign key to the existing `records` asset row. Name, office and department remain shared core record fields. Existing assets are backfilled into this detail table without rewriting their original data. Known legacy ICT categories map to Office Equipment for filtering; unknown categories stay visible until an authorized user chooses a type.

New assets save atomically with detail rows and audit entries. Existing edits remain proposed changes requiring independent approval. Detail projections and filters only change when approved record values change. Original assignment, transfer, disposal, version-conflict and handover safeguards remain. Existing extra legacy metadata is retained even when it is not part of the new form.

## Filters and exports

Type, Donor and Project filters combine. Office, Status and text search are also available. URL parameters reproduce a view and stay on pagination links. **Export CSV** applies the same filters and includes the screenshot fields plus Project, Donor and Department; formula prefixes are escaped.

## Verification

60 tests / 669 assertions passed. New tests exercise the type-selection step, all three forms saving into one table, invalid type/office/department/price rejection, combined filters and matching CSV, legacy zero-cost preservation, and approved-edit synchronization. Prior approval/handover tests were updated to submit the newly required asset fields and still pass.

Edge/Playwright QA on an isolated database created all three groups through the UI, checked Origin only for vehicles, saved detail pages, the shared table, combined Donor/Project/Type filtering, CSV download and mobile layout without page overflow or browser errors. Desktop 1440×1000; mobile 390×844. Browser plugin was unavailable. Existing user records were not used for QA.
