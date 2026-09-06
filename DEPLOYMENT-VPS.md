# Royal Family TZ — Docker VPS Production Deployment

This runbook deploys the native PHP/MySQL application as three always-on containers: PHP-Apache for the application, MySQL for persistent data, and Caddy for HTTPS and reverse proxying. Docker restart policies keep the services running after a reboot. The deployment assumes an Ubuntu 22.04 or 24.04 VPS with a public IPv4 address and a domain name.

> Do not put real ClickPesa, Google, SMTP, or database credentials into Git, screenshots, chat messages, or the ZIP archive. The production `.env` file must remain only on the VPS.

## 1. Requirements and DNS

You need a VPS with at least 1 GB RAM, 1 vCPU, 20 GB SSD, Ubuntu 22.04/24.04, and a public IPv4 address. A 2 GB VPS is more comfortable for MySQL, Docker, backups, and logs. You also need control of the domain DNS records.

Create an `A` record for the root domain and, if desired, a `www` record:

| Type | Name | Value | Proxy |
|---|---|---|---|
| A | `@` | Your VPS public IPv4 | DNS-only initially |
| CNAME | `www` | Your root domain | DNS-only initially |

Wait until `nslookup your-domain.tld` returns the VPS IP. Caddy must be able to receive public traffic on ports 80 and 443 to issue and renew the HTTPS certificate.

## 2. Connect to the VPS and install Docker

From your computer, connect using the VPS provider's SSH command:

```bash
ssh root@YOUR_VPS_IP
```

Create a non-root deployment user and install Docker:

```bash
adduser deploy
usermod -aG sudo deploy
apt update && apt -y upgrade
apt install -y ca-certificates curl git ufw fail2ban unzip
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
chmod a+r /etc/apt/keyrings/docker.asc
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo $VERSION_CODENAME) stable" > /etc/apt/sources.list.d/docker.list
apt update
apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
usermod -aG docker deploy
systemctl enable --now docker
```

Open only SSH, HTTP, and HTTPS:

```bash
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable
ufw status verbose
```

Log out and reconnect as the deployment user:

```bash
exit
ssh deploy@YOUR_VPS_IP
```

## 3. Upload the application

Create the production directory and copy the project to the VPS. You can use `scp` from your computer, or clone a private Git repository:

```bash
sudo mkdir -p /opt/royalfamilytz
sudo chown -R deploy:deploy /opt/royalfamilytz
cd /opt/royalfamilytz
```

If using an archive from your computer, run locally:

```bash
scp royalfamilytz-production-docker.zip deploy@YOUR_VPS_IP:/opt/royalfamilytz/
```

Then on the VPS:

```bash
cd /opt/royalfamilytz
unzip royalfamilytz-production-docker.zip
# If extraction created a nested folder, move its contents into /opt/royalfamilytz.
```

The directory must contain `docker-compose.yml`, `Dockerfile`, `database.sql`, `index.php`, and the `docker/` directory.

## 4. Create the private production environment

Copy the template and edit it:

```bash
cd /opt/royalfamilytz
cp .env.production.example .env
chmod 600 .env
nano .env
```

Use the live domain without `http://localhost`:

```dotenv
DOMAIN=royalfamilytz.org
```

If the website is served at the domain root, the PHP application automatically uses:

```text
https://royalfamilytz.org
```

The live Google callback URI is therefore:

```text
https://royalfamilytz.org/auth/google/callback
```

Generate strong database passwords instead of typing simple passwords:

```bash
openssl rand -base64 32
```

Fill in the production ClickPesa credentials, Google OAuth credentials, and SMTP credentials. Do not use test ClickPesa credentials for real transactions.

## 5. Start the application

Run the stack in the background:

```bash
cd /opt/royalfamilytz
docker compose up -d --build
```

Check service status and logs:

```bash
docker compose ps
docker compose logs --tail=100 db
docker compose logs --tail=100 app
docker compose logs --tail=100 web
```

The initial MySQL schema is imported automatically on the first start because `database.sql` is mounted into MySQL's initialization directory. If the database volume already exists, changing `database.sql` will not re-import it; use an explicit migration or restore procedure instead.

Check the HTTPS site:

```bash
curl -I https://royalfamilytz.org
curl -fsS https://royalfamilytz.org/health.php
```

## 6. Configure Google sign-in

In Google Cloud Console, open the same OAuth 2.0 Client ID used by the application. Configure the exact authorized origin and callback:

```text
Authorized JavaScript origins:
https://royalfamilytz.org

Authorized redirect URIs:
https://royalfamilytz.org/auth/google/callback
```

The redirect URI must match exactly, including HTTPS, domain, path, and any trailing path. After changing `.env`, recreate the app container:

```bash
docker compose up -d --build app
```

Open the site through the live HTTPS domain, not `localhost`, an internal IP, or a stale Cloudflare Tunnel URL.

## 7. Configure ClickPesa production webhooks

The application webhook endpoint is:

```text
https://royalfamilytz.org/api/clickpesa/webhook
```

In ClickPesa, open the relevant production application under **Settings → Developers → Application Webhooks** and configure the URL for:

```text
PAYMENT RECEIVED
PAYMENT FAILED
```

ClickPesa documents application webhooks as HTTP POST callbacks for API and hosted-checkout transactions, and the receiving endpoint should acknowledge the request with a 2xx response. [1]

Test the endpoint only through ClickPesa's official test mechanism or a properly checksum-signed request. Do not disable checksum verification in production.

After the first successful payment, verify both the customer experience and database state:

```bash
docker compose logs --tail=100 app
docker compose exec db mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "SELECT order_reference,type,amount,status,created_at FROM transactions ORDER BY id DESC LIMIT 10;"
```

## 8. Configure SMTP

Use a dedicated mailbox or domain SMTP account. If using Gmail, use an App Password rather than the normal Gmail password, and enable two-factor authentication on the mailbox. Set `MAIL_FROM`, `SMTP_USER`, and `SMTP_PASSWORD` in `.env`, then restart the app:

```bash
docker compose up -d --build app
```

Test signup verification, admin notifications, contact replies, and the welcome email without exposing the SMTP password.

## 9. Create the first production administrator

The included schema contains a seeded administrator for development compatibility. Do not keep its known development password in production. Log in immediately after deployment and change it, or update the password directly using a generated PHP password hash.

Generate a hash on the VPS:

```bash
docker compose exec app php -r 'echo password_hash("REPLACE_WITH_A_LONG_UNIQUE_PASSWORD", PASSWORD_DEFAULT), PHP_EOL;'
```

Apply the hash to the intended administrator account:

```bash
docker compose exec db mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "UPDATE users SET password_hash='PASTE_HASH_HERE' WHERE email='YOUR_ADMIN_EMAIL';"
```

After confirming the new login works, remove or disable any unused administrator accounts.

## 10. Backups

Create a backup directory and test the provided backup script:

```bash
cd /opt/royalfamilytz
mkdir -p backups
./docker/backup.sh
ls -lh backups
```

Schedule it daily at 02:30:

```bash
crontab -e
```

Add:

```cron
30 2 * * * cd /opt/royalfamilytz && /opt/royalfamilytz/docker/backup.sh >> /var/log/royalfamilytz-backup.log 2>&1
```

Copy backups to a second location. A backup stored only on the same VPS is not sufficient protection against disk loss or account deletion. Periodically test restoration on a separate database or VPS.

## 11. Updates and rollback

Before an update:

```bash
cd /opt/royalfamilytz
./docker/backup.sh
git pull   # only if deployment uses Git
# or replace the application files with the reviewed release archive
docker compose up -d --build
```

Monitor the new version:

```bash
docker compose ps
docker compose logs -f --tail=100 app
```

Do not run `docker compose down -v` during a normal update. The `-v` option deletes named database and upload volumes and can destroy production data.

## 12. Final production checklist

| Check | Expected result |
|---|---|
| Domain DNS | Resolves to the VPS public IP |
| HTTPS | Browser shows a valid certificate |
| Docker | `db`, `app`, and `web` show `Up` |
| MySQL | Database volume is persistent and backups succeed |
| Google | Login returns to the live domain and opens Dashboard |
| ClickPesa | Application webhook receives signed `PAYMENT RECEIVED` and `PAYMENT FAILED` events |
| SMTP | Verification and admin emails arrive with subject and body |
| Uploads | Profile and trip images persist after `docker compose up -d --build` |
| Security | `.env` is mode 600, ports 80/443/SSH only, and no secrets are in Git |
| Recovery | A fresh database and uploads restore has been tested |

## References

[1]: https://docs.clickpesa.com/home/webhooks "ClickPesa Webhooks documentation"
