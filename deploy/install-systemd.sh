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

# Юниты, которых больше нет в репозитории (recordings:retry-uploads теперь запускает планировщик)
for name in serdal-recordings-retry.timer serdal-recordings-retry.service; do
    if [ -f "$DST/$name" ]; then
        sudo systemctl disable --now "$name" >/dev/null 2>&1 || true
        sudo rm -f "$DST/$name"
        echo "    удалён $name"
        changed=1
    fi
done

if [ "$changed" = 1 ]; then
    sudo systemctl daemon-reload
fi

# Раньше юниты были WantedBy=serdal.target, которого не существует, — после перезагрузки
# сервера они не стартовали. reenable пересоздаёт симлинки под актуальный [Install].
sudo systemctl reenable serdal-queue.service serdal-queue-recordings.service \
    serdal-reverb.service serdal-pulse.service serdal-scheduler.timer >/dev/null 2>&1
sudo rm -rf "$DST/serdal.target.wants"
sudo systemctl start serdal-queue-recordings.service serdal-scheduler.timer
