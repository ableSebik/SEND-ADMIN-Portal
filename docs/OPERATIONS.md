# Running and operating SEND-AMS

## Local use

1. Start `Start-SEND-AMS.ps1` or run `C:\xampp\php\php.exe artisan serve --host=127.0.0.1 --port=8000` from `app/`.
2. Open http://127.0.0.1:8000 and create the initial administrator if no accounts exist.
3. Sign in directly with your email and password. New passwords require at least eight characters; MFA has been removed.
4. Create users in User Management. Add at least one independent Administrative Manager for approval decisions. Driver and Super Administrator accounts can open trips.
5. Drivers can open trips using any available vehicle; licence-expiry verification is no longer required. Drivers see only Trips in the sidebar. Super Admin can open trips on behalf of a selected driver.

All displayed business times default to Accra; application timestamps are UTC. Default monetary presentation is GHS. The local database is `app/database/database.sqlite`; private files are below `app/storage/app/private`. Do not place real confidential documents in a development environment without approved endpoint controls.

## User guide

- **Vendors:** Add a vendor, attach compliance documents, and record contracts/evaluations under Operational history. An independent approver activates the vendor. Restricted banking changes require password confirmation and independent approval.
- **Vehicles / Driver Logs:** Super Admin or Admin registers the vehicle; all users can read the fleet. A signed-in driver can open their own trip; a Super Administrator can select a driver and open a trip on their behalf. Opening fields include the fixed driver identity, Department/Project, From, Destination, departure time and Mileage Before. Saving opening details gives the trip an amber Pending status. The original driver or a Super Administrator can use Add closing details to record Mileage After and return time (prefilled with the current time and editable). Both closing fields are required to mark the trip green Completed, calculate distance and update vehicle mileage. Trips no longer use the independent approval queue.
- **Asset Register:** Register an asset, request assignment or transfer, obtain approval, and have the recipient acknowledge receipt. Disposal also requires independent approval. All records remain available for historical review.
- **Record changes:** Use Edit record. The proposal preserves prior values and requires independent approval. Identifiers and odometer values cannot be changed through ordinary record editing.
- **Documents:** Permitted PDF, JPEG, PNG, Word and Excel files up to 10 MB go to private storage. Uploading another file retains both versions as separate attachments; automatic version grouping is not included.
- **Reminders:** Notifications lists documents and operational activities due within 30 days, including overdue dates. Current reminders use document/entry due dates; ordinary register date fields do not yet create reminders automatically.
- **Exports:** Reports downloads the authorized register as CSV. Export activity is audited. CSV can be opened in Excel; native XLSX/PDF formatting is not included.
- **Accounts:** Users can change their password on Security. Click a username in Organisation accounts to edit that account, or use the top Add User button. Super Admin can manage all users; Admin manages Driver/Officer users. Licence-expiry verification and the former authorization panel have been removed. Disabling or changing access revokes existing database sessions.

## Production preparation

Use a patched, vendor-supported PHP runtime compatible with the locked dependencies. The installed XAMPP PHP 8.2.12 is a local development dependency, not a production runtime recommendation. Provision a supported MySQL deployment, HTTPS, private persistent file storage, mail delivery, restricted database credentials, centralized logging and monitoring.

Configure the server document root to `/path/to/SEND-AMS/app/public`. Apache requires mod_rewrite and appropriate AllowOverride settings for the public directory. The parent directories deny direct web access. For Nginx, use `try_files $uri $uri/ /index.php?$query_string` inside the public root and deny dotfiles. Do not host the repository as a publicly browsable directory.

From `app/`:

```sh
composer install --no-dev --prefer-dist --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Set at least:

```dotenv
APP_NAME="SEND-AMS"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://administration.your-organization.example
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=send_ams
DB_USERNAME=send_ams
DB_PASSWORD=provide-through-your-secret-manager
SESSION_DRIVER=database
SESSION_LIFETIME=30
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
MAIL_MAILER=smtp
MAIL_HOST=your-approved-mail-host
MAIL_PORT=587
MAIL_USERNAME=your-approved-smtp-user
MAIL_PASSWORD=provide-through-your-secret-manager
MAIL_FROM_ADDRESS=administration@your-organization.example
MAIL_FROM_NAME="SEND Ghana Administration"
```

Use the TLS configuration required by your approved SMTP provider. The default local log mailer does not deliver email; password-reset email delivery requires this configuration and an end-to-end mail test.

```sh
php artisan migrate --force
php artisan send:admin admin@your-organization.example --name="Administrator"
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

The web process needs write access only to `storage` and `bootstrap/cache`; keep `.env` tightly restricted. Database accounts should have only the required application rights, with migrations executed through a controlled deployment identity where feasible.

## Queues and scheduling

Laravel's database queue/jobs schema is installed. If background jobs are introduced, supervise `php artisan queue:work --tries=3 --timeout=120` and run the scheduler with `* * * * * cd /path/to/app && php artisan schedule:run`. This build's expiry page is computed on demand; there is no scheduled reminder-email job or report-email job yet. Do not infer delivery merely because a queue worker is running.

## Backup, restore, and incident response

Back up the relational database, private files, application configuration, and APP_KEY through an approved encrypted backup system. Keep encryption-key recovery separate from the encrypted backup. Define retention, access, off-site copies, recovery-point and recovery-time targets with SEND Ghana. A backup status screen or successful dump is not evidence of recoverability.

Restore into an isolated environment, load the correct APP_KEY, restore database and private files, verify that encrypted banking data decrypts, run migrations if required, and execute login/approval/document-download tests. Record elapsed recovery time and reconciled record counts before sign-off. Do not run destructive fresh migrations on a populated environment.

On suspected compromise: restrict access, preserve application/database/audit evidence, revoke sessions and affected credentials, rotate secrets through a reviewed recovery plan, restore only from verified backups, and follow the organization's incident response and notification process. Changing APP_KEY without an explicit re-encryption plan makes existing encrypted data unreadable.

## Testing and upgrades

Run `php artisan test` against the isolated in-memory test database configured by `phpunit.xml`. Test production MySQL separately; SQLite tests do not establish MySQL concurrency guarantees. Run `php vendor/bin/pint --test`, dependency audit, a controlled restore exercise, authorization tests for every configured role, and browser smoke tests before production deployment. Re-run appropriate checks after upgrades.

No API integration contract is published in this build. All routes are authenticated server-rendered web routes with CSRF protection.
