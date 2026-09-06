# Royal Family TZ — PHP + MySQL + ClickPesa Edition

Royal Family TZ is a native PHP application prepared for **MySQL in XAMPP** and ClickPesa Collection API payments. It includes clean URL routing, public pages, member signup/login, session-protected member pages, MySQL records, ClickPesa mobile-money USSD-PUSH, ClickPesa hosted card checkout, ClickPesa webhook verification, membership activation after successful payment, donation records, and an admin payment dashboard.

## XAMPP setup

Start **Apache** and **MySQL** in XAMPP. Copy the project folder into the Apache document root. If your DocumentRoot is the normal XAMPP folder, use:

```text
C:\xampp\htdocs\royalfamilytz
```

If you changed Apache’s DocumentRoot directly to the project folder, use:

```text
C:\xampp\htdocs\royalfamilytz
```

and open `http://localhost/`. Otherwise open `http://localhost/royalfamilytz/`.

Open `http://localhost/phpmyadmin`, select the **Import** tab, and import `database.sql` for a new database. If you already imported the earlier four-table version, import `migration-clickpesa.sql` instead. It adds `order_reference`, `provider_ref`, `channel`, and `failure_message` to `transactions`.

The database is named `royalfamilytz`. Standard XAMPP defaults are host `127.0.0.1`, port `3306`, username `root`, and an empty password.

## Configure ClickPesa

Copy:

```text
local-config.php.example
```

to:

```text
local-config.php
```

Open `local-config.php` and replace the two placeholders with the credentials from your ClickPesa application:

```php
'CLICKPESA_CLIENT_ID' => 'your-real-client-id',
'CLICKPESA_API_KEY' => 'your-real-api-key',
```

The application uses the ClickPesa API base URL:

```text
https://api.clickpesa.com/third-parties
```

The PHP integration performs the documented authorization-token request, validates mobile-money details, initiates USSD-PUSH, and generates the hosted checkout link for card payments. It accepts Tanzanian numbers in formats such as `0712345678`, `255712345678`, or `+255712345678` and sends ClickPesa the normalized `255712345678` form.

## Configure the ClickPesa webhook

Expose your local Apache server through a public HTTPS tunnel or deploy the application to a public HTTPS host. In ClickPesa Dashboard, go to **Settings → Developers → your application → Application Webhooks** and configure these events:

```text
PAYMENT RECEIVED
PAYMENT FAILED
```

Set the webhook URL to:

```text
https://your-public-domain.example/api/clickpesa/webhook
```

For a temporary local tunnel, the URL will be the public tunnel hostname followed by `/api/clickpesa/webhook`. Do not use `localhost` in ClickPesa’s dashboard because ClickPesa cannot reach a private local address.

If checksum security is enabled for your ClickPesa application, put the checksum key in `local-config.php`:

```php
'CLICKPESA_CHECKSUM_KEY' => 'your-checksum-key',
```

The webhook rejects invalid checksums and updates the matching MySQL transaction using `order_reference`. A successful subscription webhook activates the member and generates a membership ID.

## Admin login

The seed script creates:

```text
Email: admin@royalfamilytz.org
Password: password
```

Use this only for initial local testing and change the password before deployment. The admin dashboard is available at `/admin` and displays member counts, paid revenue, ClickPesa configuration status, and transaction records.

## Payment behavior

Mobile Money uses ClickPesa’s preview and initiate USSD-PUSH endpoints. Card payments use ClickPesa’s hosted checkout link. Each attempt is first stored as `pending` in `transactions`; the webhook is the source of truth that changes it to `paid` or `failed`. The PHP application does not mark a transaction as paid merely because the customer submitted a form.

## Security and production requirements

Keep `local-config.php` private and never upload it to a public repository. Use HTTPS, a dedicated MySQL user instead of `root`, CSRF protection, rate limiting, and a strong administrator password before accepting real payments. Configure ClickPesa webhooks only after the public URL is reachable and tested.

## References

The ClickPesa request and webhook implementation follows the official documentation for [API integration](https://docs.clickpesa.com/home/integration-overview), [authorization tokens](https://docs.clickpesa.com/api-reference/authorization/generate-token), [USSD-PUSH](https://docs.clickpesa.com/api-reference/collection/ussd-push-requests/initiate-ussd-push-request), [hosted checkout](https://docs.clickpesa.com/api-reference/collection/generate-checkout-link/generate-checkout-link), [webhooks](https://docs.clickpesa.com/home/webhooks), and [checksum validation](https://docs.clickpesa.com/home/checksum).

## Community photos and slideshow

The supplied photographs are included under `assets/community/`. The homepage and About page each use an automatic slideshow with previous/next buttons and accessible dot navigation. The images are served locally by Apache, so no external image host is required.

## Member profile photos

Members can open `/profile`, choose a JPG, PNG, or WEBP image up to 4 MB, and save it. Files are stored in `uploads/profiles/` with generated filenames. Ensure the folder is writable by Apache on Windows. The profile photo is shown in the profile editor and is stored in the `users.profile_image` column.

## Google sign-up and sign-in

Google sign-in requires a Google OAuth web application. In Google Cloud Console, create an OAuth client of type **Web application** and add this authorized redirect URI:

```text
http://localhost/royalfamilytz/auth/google/callback
```

For a Cloudflare public URL, add the public callback as a second redirect URI, for example:

```text
https://your-public-host.example/auth/google/callback
```

Copy `local-config.php.example` to `local-config.php` and fill in:

```php
'APP_URL' => 'http://localhost/royalfamilytz',
'GOOGLE_CLIENT_ID' => 'your-client-id.apps.googleusercontent.com',
'GOOGLE_CLIENT_SECRET' => 'your-client-secret',
```

The login and signup pages then show **Continue with Google** and **Sign up with Google**. The callback uses OpenID Connect, links an existing account by email, or creates a new member account when the Google email is not yet registered. Never commit `local-config.php` or expose the client secret.

If the existing database was imported before these features were added, import `migration-profile-google.sql` after importing `migration-clickpesa.sql`.

## Email notifications

When an administrator creates a notification, it is always stored in MySQL and appears on member dashboards. If SMTP is configured, the application also sends an individual email to every matching user. The `Everyone` audience includes all users, while `Members` includes users with the `member` role.

For Gmail, enable two-step verification on the sending account and create a Gmail App Password. Do not use the normal Gmail password. Copy `local-config.php.example` to `local-config.php` and configure:

```php
'MAIL_FROM' => 'your-email@gmail.com',
'MAIL_FROM_NAME' => 'Royal Family TZ',
'SMTP_HOST' => 'smtp.gmail.com',
'SMTP_PORT' => '587',
'SMTP_SECURITY' => 'tls',
'SMTP_USER' => 'your-email@gmail.com',
'SMTP_PASSWORD' => 'your-16-character-app-password',
```

Keep `local-config.php` private. If SMTP is not configured, notifications still appear on dashboards and the admin receives a message explaining that no email was sent.

## Trips, packages, and reports

The application now includes a reusable trip system. Import `migration-trips.sql` into the `royalfamilytz` database, or allow the application to create the tables automatically when an admin opens the dashboard. The migration seeds a Chemka Trip example with these packages:

| Package | Price | Included |
|---|---:|---|
| Day Pass | TZS 75,000 | Entry, shared transport, and community lunch |
| Comfort Package | TZS 120,000 | Day Pass plus reserved seat, refreshments, and activity kit |
| Family Package | TZS 250,000 | Two adults and two children with shared transport and lunch |

Members can open `/trips`, select a package, enter the number of guests and phone number, and start ClickPesa payment. The booking remains pending until ClickPesa sends the payment callback. A successful callback marks both the transaction and trip booking as paid.

Administrators can open `/admin`, create a trip with up to three packages, and delete existing trips. Deleting a trip also removes its packages and bookings through the database cascade. Administrators can open `/admin/reports` to view total users, active memberships, total paid and pending amounts, user membership IDs, and the user associated with every transaction. The reports page also provides CSV downloads for users and transactions.
