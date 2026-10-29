# SEND Ghana Administrative Management System

A working Laravel 12 development build of SEND-AMS, with a Blade interface and persistent local SQLite database. Source and dependencies are in `app/`.

## Open the application

Run `./Start-SEND-AMS.ps1` from PowerShell, then open **http://127.0.0.1:8000**. If the server is already running, open that address directly.

The first visit opens local setup. Create your own administrator account with a password of at least eight characters. There are no default passwords. Create a second authorized account in Users to exercise independent approvals; users cannot approve their own requests.

Initial setup is available only in the local environment and closes after the first account exists. On production, create the first account through `php artisan send:admin admin@your-organization.example --name="Administrator"` instead.

## Implemented

- Individual accounts, login throttling, password recovery through Laravel's mail transport, session revocation on password changes, eight-character minimum passwords and direct sign-in without MFA.
- Four roles (Super Admin, Driver, Admin and Officer); server-side action checks and ownership restrictions for staff and drivers. IT administrators have no operational-record access and can provision only staff and drivers.
- Vendor, vehicle, trip, and asset registers; searchable paginated lists; detailed records; additional identity, acquisition, compliance, and technical fields.
- Independent vendor/trip approval; bank changes with encrypted proposed and active values; approved record edits; optimistic version checks.
- Trip active-driver checks, shared available-vehicle selection, reservation conflict checks, odometer validation, two-phase driver/Super Administrator trip opening and owner/Super Administrator closure, and distance calculation.
- Asset assignment, transfer, recipient acknowledgement, and independent disposal approval. Disposal retains the record.
- Private document uploads and authenticated downloads, document expiry reminders, operational history entries for contracts, evaluations, fuel, maintenance, insurance, roadworthiness, incidents, warranty, and physical verification.
- Audit events, dashboard counts, in-app due-date reminders, and authorized CSV exports with spreadsheet-formula protection.
- Responsive interface, local first-run setup, database migrations, and automated workflow/security tests.

## Validation

From `app/`, run:

```powershell
C:\xampp\php\php.exe artisan test
C:\xampp\php\php.exe vendor\bin\pint --test
```

Browser QA uses Playwright with installed Edge because the Browser plugin is unavailable. The tested flow covers setup, password login, dashboard, vendor creation, search, self-approval guidance, and mobile navigation. No external assets or frontend build step are required.

## Deployment and remaining scope

Read [requirements and architecture](docs/SYSTEM.md), [operations](docs/OPERATIONS.md), and [delivery status](docs/STATUS.md). This is a development release, not a production-readiness certification. The full source brief remains in `writing-block.md`; the delivery status identifies items beyond this build.

See [verification evidence and screenshots](docs/VERIFICATION.md) for the test results and visual comparison.

The repository root and `app/` are denied by Apache configuration. Point the web server document root specifically at `app/public`. Never expose `.env`, the database, `vendor`, or private storage. The local launcher serves only `app/public` on the loopback interface.

Super Administrator and superuser accounts have full module/record access, including opening trips on behalf of drivers. See [access rules](docs/ACCESS.md).
