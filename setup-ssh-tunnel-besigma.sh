#!/usr/bin/env bash
set -euo pipefail

SSH_HOST="${BESIGMA_SSH_HOST:-52.74.245.15}"
SSH_PORT="${BESIGMA_SSH_PORT:-22}"
SSH_USER="${BESIGMA_SSH_USER:-ubuntu}"
SSH_KEY="${BESIGMA_SSH_PKEY:-$(dirname "$0")/public/bsigma-jumpserver.pem}"
LOCAL_PORT="${BESIGMA_DB_PORT:-3307}"
DB_HOST="${BESIGMA_REMOTE_HOST:-10.11.58.139}"
DB_PORT="${BESIGMA_REMOTE_PORT:-3306}"

if [[ ! -f "$SSH_KEY" ]]; then
  echo "ERROR: Key file not found: $SSH_KEY"
  exit 1
fi

chmod 600 "$SSH_KEY" 2>/dev/null || true

echo "========================================"
echo "SSH Tunnel Setup - Besigma Database"
echo "Local: 127.0.0.1:${LOCAL_PORT} -> ${DB_HOST}:${DB_PORT}"
echo "Jump:  ${SSH_USER}@${SSH_HOST}:${SSH_PORT}"
echo "Keep this process running (lebih aman dengan autossh / systemd Restart=always)."
echo "========================================"

exec ssh -N -L "127.0.0.1:${LOCAL_PORT}:${DB_HOST}:${DB_PORT}" \
  -i "$SSH_KEY" \
  -p "$SSH_PORT" \
  -o StrictHostKeyChecking=no \
  -o IdentitiesOnly=yes \
  -o ExitOnForwardFailure=yes \
  -o ServerAliveInterval=30 \
  -o ServerAliveCountMax=3 \
  -o TCPKeepAlive=yes \
  "${SSH_USER}@${SSH_HOST}"
