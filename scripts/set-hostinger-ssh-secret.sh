#!/usr/bin/env bash
set -euo pipefail

KEY_PATH="${1:-$HOME/.ssh/inmo_deploy}"
REPO="${2:-}"

if [[ ! -f "$KEY_PATH" ]]; then
  echo "No existe la clave: $KEY_PATH" >&2
  echo "Genera una con: ssh-keygen -t ed25519 -C github-actions-deploy -f ~/.ssh/inmo_deploy -N \"\"" >&2
  exit 1
fi

if ! ssh-keygen -y -f "$KEY_PATH" >/dev/null 2>&1; then
  echo "La clave en $KEY_PATH no es valida o tiene passphrase." >&2
  exit 1
fi

encoded="$(base64 -i "$KEY_PATH" | tr -d '\n')"

if command -v gh >/dev/null 2>&1; then
  if [[ -z "$REPO" ]]; then
    REPO="$(gh repo view --json nameWithOwner -q .nameWithOwner 2>/dev/null || true)"
  fi

  if [[ -n "$REPO" ]]; then
    printf '%s' "$encoded" | gh secret set HOSTINGER_SSH_KEY --repo "$REPO"
    echo "Secret HOSTINGER_SSH_KEY actualizado en $REPO"
    exit 0
  fi
fi

echo "Copia este valor en GitHub → Settings → Secrets → HOSTINGER_SSH_KEY:"
echo "$encoded"
