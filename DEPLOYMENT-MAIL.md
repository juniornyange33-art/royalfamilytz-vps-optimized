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
- Trip ticket emails include a single-page PDF ticket attachment with booking details and the order reference.
- If you prefer QR codes embedded inside the PDF, enable outbound access to `chart.googleapis.com` and ensure an image library (Imagick) is available; I can update the generator to embed PNG QR images into the PDF when those are available.

Security
- Do NOT commit secrets to the repository. Use environment variables or secure deployment secrets.
- If using SMTP, prefer a dedicated SMTP account or an App Password.
