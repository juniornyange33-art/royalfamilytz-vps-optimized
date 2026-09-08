Mailer setup for Royal Family TZ

This project supports two mailing options (choose one):

1) Resend API (recommended)
- Add environment variable: `RESEND_API_KEY` with your Resend API key.
- Resend handles deliverability and attachments automatically.

2) SMTP (fallback)
- Set the following environment variables or add them to `local-config.php` under `['smtp']`:
  - `SMTP_HOST` (e.g. smtp.gmail.com)
  - `SMTP_PORT` (465 for SSL or 587 for TLS)
  - `SMTP_SECURITY` (ssl or tls)
  - `SMTP_USER` (SMTP username)
  - `SMTP_PASSWORD` (SMTP password)
  - `MAIL_FROM` (email address used in From header)
  - `MAIL_FROM_NAME` (display name for From)

Notes and diagnostics
- A diagnostics endpoint is available at `/api/mail-test?to=you@example.com` which attempts to send a test email and reports non-secret configuration values.
- For Gmail SMTP, you may need an App Password or enable "Less secure apps" depending on your account settings.
- The server must allow outbound TCP connections on the SMTP port (465/587) for SMTP fallback.

Ticket attachments
- Trip ticket emails attempt to attach a PNG ticket with a QR code.
- The QR image is fetched at send time from Google Chart API. Ensure the server has outbound network access to `chart.googleapis.com` for QR generation. If the QR fetch fails, the ticket email will still be sent without the image.

Security
- Do NOT commit secrets to the repository. Use environment variables or secure deployment secrets.
- If using SMTP, prefer a dedicated SMTP account or an App Password.
