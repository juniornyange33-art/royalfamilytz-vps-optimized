# Deploying RoyalFamilyTZ from GitHub Actions

This repository includes a GitHub Actions workflow to deploy the application to a VPS via SSH when pushes are made to `master`.

Required repository secrets (set in GitHub > Settings > Secrets):

- `SSH_HOST` — remote server IP or hostname
- `SSH_USER` — SSH user to connect as
- `SSH_PRIVATE_KEY` — private SSH key (PEM) for `SSH_USER`
- `SSH_PORT` — (optional) SSH port (default `22`)
- `REMOTE_APP_PATH` — path on the remote server where the repo is checked out and `docker-compose.yml` lives

What the workflow does:

- SSH to the server and runs `docker-compose pull` and `docker-compose up -d --remove-orphans` in `REMOTE_APP_PATH`.
- Prints `docker-compose ps` for quick verification.

Manual deployment commands (run on the server):

```bash
cd /path/to/app
git pull origin master
docker-compose pull
docker-compose up -d --remove-orphans
docker-compose ps
```

If you prefer not to use GitHub Actions, you can create an SSH keypair, add the public key to the server's `~/.ssh/authorized_keys` for the deploy user, and run the manual commands above.
