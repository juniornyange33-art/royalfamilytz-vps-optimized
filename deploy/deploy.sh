#!/usr/bin/env bash
set -euo pipefail

# deploy/deploy.sh
# Run this on the server in the application directory to pull latest and restart services.

APP_DIR="${APP_DIR:-$(pwd)}"
COMPOSE="${COMPOSE:-docker-compose}"

echo "Deploy script starting in $APP_DIR"
cd "$APP_DIR"

if [ -d .git ]; then
  echo "Fetching latest code from origin/master"
  git fetch --all --prune
  git reset --hard origin/master
fi

echo "Pulling images (if using Docker) and starting services"
$COMPOSE pull || true
$COMPOSE up -d --remove-orphans

echo "Pruning unused images"
docker image prune -f || true

echo "Showing service status"
$COMPOSE ps

echo "Tail last 200 lines of combined logs (ctrl-c to exit)"
$COMPOSE logs --tail=200 -f
