#!/usr/bin/env bash
#
# Идемпотентно ставит systemd-юниты serdal.ru из deploy/systemd в /etc/systemd/system.
# Вызывается из deploy.sh; можно запускать руками: sudo bash deploy/install-systemd.sh
#
set -euo pipefail

SRC="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/systemd"
DST=/etc/systemd/system
BACKUP=/var/backups/serdal-systemd
changed=0

for unit in "$SRC"/*; do
    name="$(basename "$unit")"
    if ! cmp -s "$unit" "$DST/$name"; then
        if [ -f "$DST/$name" ]; then
            sudo mkdir -p "$BACKUP"
            sudo cp -a "$DST/$name" "$BACKUP/$name.$(date +%Y%m%d%H%M%S)"
        fi
        sudo install -m 644 -o root -g root "$unit" "$DST/$name"
        echo "    обновлён $name"
        changed=1
    fi
done

if [ "$changed" = 1 ]; then
    sudo systemctl daemon-reload
fi

sudo systemctl enable --now serdal-queue-recordings.service serdal-recordings-retry.timer >/dev/null
