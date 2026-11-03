# Microsoft 365 mail setup

The portal supports Microsoft Graph client credentials through `MAIL_MAILER=microsoft_graph`.

## Entra configuration

Use the SEND Admin Portal Mail registration. Under Certificates & secrets, create a client secret and store its **Value** (not its ID) in `app/.env` as `M365_CLIENT_SECRET`. Do not commit or share the value. Record the expiry and renew it before expiry.

Under **Enterprise applications**, find this application and copy its **Object ID**. This is different from the Object ID under App registrations.

## Restrict sending to the admin mailbox

An Exchange administrator should run these commands in Exchange Online PowerShell. Install/import the ExchangeOnlineManagement module if needed. Replace the placeholder with the Enterprise application's Object ID.

```powershell
Connect-ExchangeOnline
$portalAppId = 'fa03c388-1375-4fe2-a052-67abe3b41fb9'
$portalServicePrincipalId = '21a7c15f-b6ce-44a1-a515-ed64d92f1c58'
New-ServicePrincipal -AppId $portalAppId -ObjectId $portalServicePrincipalId -DisplayName 'SEND Admin Portal Mail'
New-ManagementScope -Name 'SEND Portal Sender' -RecipientRestrictionFilter "PrimarySmtpAddress -eq 'admin@sendwestafrica.org'"
New-ManagementRoleAssignment -Name 'SEND Portal Send Mail' -Role 'Application Mail.Send' -App $portalServicePrincipalId -CustomResourceScope 'SEND Portal Sender'
Test-ServicePrincipalAuthorization -Identity $portalServicePrincipalId -Resource 'admin@sendwestafrica.org'
```

The result should show `Application Mail.Send` with `InScope=True`. Test another existing mailbox and confirm `InScope=False`. Do not add a tenant-wide Microsoft Graph Mail.Send application grant in Entra: it would independently allow sending outside this scope. If one was already granted to this dedicated app, remove it. Permission changes can take 30 minutes to two hours to propagate.

Reference: https://learn.microsoft.com/en-us/exchange/permissions-exo/application-rbac

## Enable locally

After credentials and permissions are ready, set these in the ignored `app/.env`:

```dotenv
MAIL_MAILER=microsoft_graph
MAIL_FROM_ADDRESS=admin@sendwestafrica.org
MAIL_FROM_NAME="SEND Admin Portal"
M365_TENANT_ID=c63a7d27-bb2a-4574-947f-5870d7809274
M365_CLIENT_ID=fa03c388-1375-4fe2-a052-67abe3b41fb9
M365_CLIENT_SECRET="your-secret-value"
APP_URL=http://127.0.0.1:8001
```

Run `C:\xampp\php\php.exe artisan config:clear` from `app`, and restart long-running queue/scheduler workers. Use the real HTTPS application URL when deployed. Local reset links work only on the computer hosting the portal.

Request a password reset for an existing account. Confirm receipt and that the link opens correctly. Graph HTTP 202 means accepted for processing, not confirmed inbox delivery; use Exchange message trace if needed. Missing credentials, authentication failures and submission failures raise errors rather than silently logging mail. No live email has been tested during initial implementation.
