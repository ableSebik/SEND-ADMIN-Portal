# Vehicle operations and renewal alerts

Use **Operational history** in the sidebar to record servicing, roadworthiness and insurance renewals. Each saved renewal keeps the previous entry as history and cancels unsent reminders associated with it. Only the newest entry for each vehicle and renewal type is active. Overdue items remain visible after the due date; messages stop after renewal day.

**Send for servicing** opens the service request form, including project, date, current mileage, type of service, identified problem, provider, procurement method and diagnostic report availability. Saving the request automatically puts the vehicle in **Maintenance**. A completed servicing entry retains the work completion details and closes the open service request; it can also release the vehicle back to **Available**. Use the vehicle's availability form to mark it **Unavailable**, with a reason, if it is unusable or out of commission. A servicing entry never automatically releases an Unavailable vehicle. Only Available vehicles appear in trip selection, and availability is checked again when the trip is saved. An unclosed trip also prevents another trip using the same vehicle or OSO. Close the trip before opening a servicing request. Requester and completion recorder are retained; this tracker does not replace the separate signed approval process in the paper reference form.

The opening trip form loads current mileage and requires the OSO to verify the odometer. A different reading requires a reason; both positive and negative gaps are logged with the previous reading, trip, author and time. Completion saves the closing reading as current vehicle mileage. Closing readings cannot decrease current mileage. Concurrent or stale opening forms must reload when vehicle mileage has changed.

Administrators manage **Donors & projects**. Asset forms and trip project selection use these shared lists. Existing values were copied into the lists during migration, retaining historical records. Phone numbers for SMS alerts can be saved through **Users** in international format, such as `+233201234567`.

## Configure providers later

No provider credentials are included. With providers blank, due reminders are tracked as **Awaiting setup** or **Missing phone**, never as sent.

For email, configure the existing `MAIL_*` values in `app/.env` for your SMTP provider, set a valid sender address, then set `FLEET_EMAIL_ENABLED=true`. The `log` and `array` mailers do not count as actual delivery.

For SMS, set `FLEET_SMS_URL` to your HTTPS messaging gateway and `FLEET_SMS_TOKEN` to its bearer token. The gateway must accept JSON containing `to`, `message` and `idempotency_key`, and return a successful HTTP response only when it accepts delivery. The same key is also sent in the `Idempotency-Key` header. If your provider uses a different API format, adapt the SMS call in `app/Services/RenewalReminders.php` or connect an adapter gateway. Do not put credentials in source control.

Run `php artisan config:clear` after changing environment settings. Review **Reminder deliveries** in Operational history for errors. The Sent status means the configured mail transport or SMS gateway accepted the message; it does not confirm handset or inbox delivery.

## Run the scheduler

The app schedules `php artisan fleet:remind` daily at **08:00 Africa/Accra**. It sends email and SMS to every active Officer, Admin and Super Admin at 21, 14, 7, 3, 2, 1 and 0 days before the due date. Drivers and disabled accounts are excluded. Each due date, recipient and channel has a unique delivery record; successful deliveries are not repeated when the command reruns. Pending, failed, missing-phone and awaiting-setup attempts can be retried by rerunning the command on the same scheduled day. Earlier dates are not replayed when the scheduler starts late.

For local development, `Start-SEND-AMS.ps1` starts the app and a reminder scheduler together. Alternatively, keep `php artisan schedule:work` running from the app folder. For deployment, configure the host scheduler to run `php artisan schedule:run` every minute from that folder, using the same PHP and environment as the application. The app and scheduler must run for reminders to be processed. This scheduler must be configured on the deployment host independently of the local development process.

Email delivery cannot guarantee exactly-once semantics if a process stops after the provider accepts a message but before the database records it. Use a provider with idempotency support if stronger delivery guarantees are required.
