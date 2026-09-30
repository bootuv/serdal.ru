# Новый сервер видеосвязи (BigBlueButton) для Serdal

Как поднять ещё один сервер BBB и подключить его к платформе. Платформа сама распределяет занятия
между серверами по нагрузке (см. раздел «Серверы видеосвязи» в `CLAUDE.md`), но от настроек самого
сервера зависят записи в облаке и учёт посещаемости — поэтому каждый сервер настраивается одинаково.

Серверы (на 30.09.2026):

| Сервер | Версия BBB | Машина |
|---|---|---|
| `room.serdal.ru` | 3.1.0-beta.2 (Ubuntu 22.04) | 8 ядер, 31 ГБ, 433 ГБ |
| `room2.serdal.ru` | **4.0.0-rc.3** (Ubuntu 24.04), поставлен по этой инструкции | Core i3-12100 (4 ядра / 8 потоков), 15 ГБ, NVMe 451 ГБ |

Линия «3.1» в BigBlueButton переименована в **4.0** — новые серверы ставим на 4.0.
Стабильного 4.0 на 30.09.2026 ещё нет (последний — кандидат в релиз 4.0.0-rc.3); когда выйдет — обновить оба сервера.

## 1. Что нужно заранее

- Отдельная машина с **Ubuntu 24.04** (BBB 4.0 ставится только на неё), 64 бит, от **8 потоков и 16 ГБ** памяти,
  от 100 ГБ диска (записи занимают место, пока не выгружены в облако).
- Публичный IP и **поддомен** с записью A на этот IP, например `room3.serdal.ru`. Запись должна появиться **до** установки:
  без неё не выдадут сертификат. Проверка: `dig +short room3.serdal.ru @8.8.8.8`.
- Открытые порты: **80, 443 TCP**, **3478** (TURN) и **16384–32768 UDP** (звук и видео) — установщик с `-w` откроет их сам.
  Никакого другого веб-сервера на машине.
- Почта для сертификата Let's Encrypt — всегда **elberd06@gmail.com** (туда приходят предупреждения об истечении сертификата).

## 2. Установка (≈15 минут)

Официальный установщик [bbb-install](https://github.com/bigbluebutton/bbb-install), ветка `v4.0.x-release`, версия `noble-400`,
**без Greenlight** (флаг `-g` не ставим: занятия создаёт Serdal), `-w` — межсетевой экран:

```bash
wget -qO /root/bbb-install.sh https://raw.githubusercontent.com/bigbluebutton/bbb-install/v4.0.x-release/bbb-install.sh
bash /root/bbb-install.sh -w -v noble-400 -s room3.serdal.ru -e elberd06@gmail.com 2>&1 | tee /root/bbb-install.log
```

Что было при установке `room2.serdal.ru` и что это значит:

- `Job for freeswitch.service failed` в середине установки — FreeSWITCH поднялся со второй попытки, проверьте
  `bbb-conf --status` после установки: все службы должны быть `✔`.
- `curl: Could not resolve host` в финальной проверке — сервер закэшировал «домена нет», если имя проверяли
  до появления записи. Лечится `resolvectl flush-caches`.
- Отдельной службы `bbb-html5` в 4.0 нет — это нормально.
- В 4.0 звук и видео идут через **LiveKit** (`livekit-server`), TURN — пакет `bbb-coturn` (служба `coturn`,
  `realm` = домен сервера в `/etc/turnserver.conf`). Файла `turn-stun-servers.xml`, как в 3.x, больше нет.
  TURN нужен ученикам за строгими сетями (школьный, офисный интернет) — проверьте `systemctl is-active coturn`.

## 3. Обязательно для Serdal

Установщик 4.0 **не ставит** ни вебхуки, ни видеоформат записей — пункты 3.1 и 3.2 обязательны.

### 3.1. Вебхуки — посещаемость, завершение занятий, записи

```bash
sudo apt-get install -y bbb-webhooks
sudo bbb-conf --restart
```

Serdal сам регистрирует вебхук на сервере при старте каждого занятия — постоянные хуки настраивать не нужно.
**Без вебхуков** посещаемость собирается неполно: занятие, завершённое внутри BBB или по лимиту времени,
останется без участников — ученикам не начислится оплата, занятие не спишется с лимита тарифа.

### 3.2. Запись в формате «видео» — выгрузка в облако

Serdal выгружает в облако только видео (`/playback/video/<id>/video-0.m4v`). Запись в формате
«презентация» остаётся на сервере BBB и в облако не попадёт.

```bash
sudo apt-get install -y bbb-playback-video
```

Файл `/etc/bigbluebutton/recording/recording.yml` — как на текущем сервере, **только видео**
(формат «презентация» Serdal не использует — его обработка только тратит время и диск):

```yaml
steps:
  archive: "sanity"
  sanity: "captions"
  captions: "process:video"
  "process:video": "publish:video"
```

Исходники записи после публикации удаляет свой скрипт — перенесите его с текущего сервера, иначе диск
заполнится исходниками (десятки гигабайт в неделю):

```bash
# со своего компьютера (ключ SSH есть на обоих серверах)
ssh root@room.serdal.ru cat /usr/local/bigbluebutton/core/scripts/post_publish/zz_delete_raw.rb \
  | ssh root@room3.serdal.ru 'cat > /usr/local/bigbluebutton/core/scripts/post_publish/zz_delete_raw.rb && chmod 755 /usr/local/bigbluebutton/core/scripts/post_publish/zz_delete_raw.rb'
```

Исходники занятий **без записи** BBB удаляет сам через 14 дней (`/etc/cron.daily/bigbluebutton`, `unrecorded_days=14`) —
на текущем сервере это стандартное значение, его не трогаем.

**Окончательное удаление записей.** Когда Serdal удаляет запись с сервера (после выгрузки в облако, по просьбе учителя
или по сроку хранения), BBB её не стирает, а только переносит в `/var/bigbluebutton/deleted/` — и оттуда её никто не убирает
(на `room.serdal.ru` за 8 месяцев так скопилось 11 ГБ). Ежедневная очистка папок, перенесённых туда больше недели назад:

```bash
cat > /etc/cron.daily/serdal-bbb-purge-deleted <<'CRON'
#!/bin/sh
# Serdal: окончательно удалить записи, которые BBB «удалил» (deleteRecordings только переносит их в /var/bigbluebutton/deleted).
# Serdal удаляет запись с сервера после выгрузки в облако или по просьбе учителя; неделя — запас на случай ошибки.
# Возраст — ctime папки: меняется при переносе в deleted/, а не при записи занятия.
find /var/bigbluebutton/deleted -mindepth 2 -maxdepth 2 -type d -ctime +7 -exec rm -rf {} + 2>/dev/null
exit 0
CRON
chmod 755 /etc/cron.daily/serdal-bbb-purge-deleted
run-parts --test /etc/cron.daily | grep serdal    # скрипт виден cron
```

Папка опубликованных видео (`/var/bigbluebutton/published/video`) на работающем сервере почти пустая — это нормально,
если в админке включено «Удалять с сервера видеосвязи после загрузки»: видео удаляется с сервера, как только
выгружено в облако.

```bash
sudo systemctl restart bbb-rap-resque-worker
```

### 3.3. Сеть

- Сервер BBB должен открывать `https://serdal.ru` — туда уходят вебхуки, оттуда BBB забирает презентации занятий.
- Сервер Serdal должен открывать `https://room2.serdal.ru` — оттуда скачивается видео записей.

### 3.4. Остальные настройки — как на текущем сервере

На текущем сервере изменены (перенесите на новый, затем `sudo bbb-conf --restart`):

`/etc/bigbluebutton/bbb-web.properties` — добавьте строки (адрес `serverURL` и ключ `securitySalt` у каждого сервера свои, их не копировать):

```properties
maxNumPages=500
maxFileSizeUpload=200000000
defaultWelcomeMessageFooter=Вы находитесь в конференц-системе образовательной платформы <a href="https://serdal.ru" target="_blank"><u>serdal.ru</u></a>.
```

(в самом файле текст записан как `\u…` — проще скопировать строки из файла текущего сервера целиком.
`defaultWelcomeMessage` тоже есть, но Serdal передаёт приветствие при создании каждого занятия, так что оно не показывается.)

`/etc/bigbluebutton/bbb-html5.yml` — лимит пометок на доске (по умолчанию 300); адреса в этом файле установщик
пишет под свой домен, **не копируйте** строки с `room.serdal.ru`. Дописать в конец файла (раздел `public:` там уже есть,
`yq` на сервере 4.0 нет):

```bash
printf "  whiteboard:\n    maxNumberOfAnnotations: 10000\n" >> /etc/bigbluebutton/bbb-html5.yml
```

**Размер презентаций — 200 МБ, как обещает Serdal** (окно загрузки и справка). По умолчанию BBB принимает 30 МБ и 200 страниц:
большая презентация молча не появится в классе. Реальный лимит — `maxFileSizeUpload` в `bbb-web.properties` (строка выше);
клиенту нужна его копия для подсказок — в `bbb-html5.yml`:

```yaml
  presentation:
    mirroredFromBBBCore:
      uploadSizeMax: 200000000
      uploadPagesMax: 500
```

Меняйте лимиты только в файлах из `/etc/bigbluebutton/` — файлы в `/usr/share/…` перезаписывает обновление BBB
(на `room.serdal.ru` 200 МБ сначала были прописаны именно там). Дописывая строку в конец файла, проверьте, что последняя строка
заканчивается переводом строки (`tail -c1 файл | od -c` → `\n`), иначе строки склеятся.

`/etc/bigbluebutton/recording/recording.yml` — из пункта 3.2.

### 3.5. Процессор — только для выделенного сервера

На выделенной машине (не виртуальной) Ubuntu держит процессор в режиме `powersave` — частота поднимается с запаздыванием,
живому звуку и видео это вредит. Режим `performance` с сохранением после перезагрузки (так сделано на `room2.serdal.ru`):

```bash
cat > /etc/systemd/system/cpu-performance.service <<'UNIT'
[Unit]
Description=CPU governor performance (Serdal BBB: steady latency for audio/video)
After=multi-user.target

[Service]
Type=oneshot
ExecStart=/bin/sh -c "for g in /sys/devices/system/cpu/cpu*/cpufreq/scaling_governor; do echo performance > $g; done"
RemainAfterExit=yes

[Install]
WantedBy=multi-user.target
UNIT
systemctl daemon-reload && systemctl enable --now cpu-performance.service
cat /sys/devices/system/cpu/cpu*/cpufreq/scaling_governor | sort | uniq -c   # везде performance
```

### 3.6. Оформление: название Serdal, иконка сайта, русский перевод

Штатный клиент BBB 4.0 называет вкладку «BigBlueButton», показывает иконку BBB, а в русском переводе
не хватает ~300 строк («Media Sharing», «Slides», «Play video from link»…). Файлы — в `docs/bbb/` репозитория;
всё лежит в `/etc` и переживает обновления BBB: русский файл пересобирается автоматически после каждого `apt`.

```bash
# со своего компьютера, из папки проекта serdal.ru
H=root@room3.serdal.ru
ssh $H mkdir -p /etc/bigbluebutton/serdal
scp docs/bbb/ru-overrides.json $H:/etc/bigbluebutton/serdal/
scp -r docs/bbb/public $H:/etc/bigbluebutton/serdal/      # стиль и кириллица для шрифта клиента
scp docs/bbb/serdal-bbb-branding $H:/usr/local/sbin/
scp docs/bbb/serdal.nginx $H:/etc/bigbluebutton/nginx/
ssh $H 'set -e
chmod 755 /usr/local/sbin/serdal-bbb-branding
curl -sf -o /etc/bigbluebutton/serdal/favicon.ico https://serdal.ru/images/favicon.ico
echo "DPkg::Post-Invoke { \"if [ -x /usr/local/sbin/serdal-bbb-branding ]; then /usr/local/sbin/serdal-bbb-branding || true; fi\"; };" > /etc/apt/apt.conf.d/99serdal-bbb-branding
/usr/local/sbin/serdal-bbb-branding
nginx -t && systemctl reload nginx'
# Затем перезапуск BBB (bbb-conf --restart) — ТОЛЬКО когда на сервере нет занятий: он их закрывает.
# Проверка: S=$(bbb-conf --secret | sed -n "s/.*Secret: //p"); curl -s "https://room3.serdal.ru/bigbluebutton/api/getMeetings?checksum=$(printf "getMeetings%s" "$S" | sha256sum | cut -d" " -f1)"
# → в ответе должно быть noMeetings.
```

**Шрифт.** Клиент BBB поставляет шрифт Source Sans Pro только с латиницей — русские буквы браузер рисует системным
шрифтом, и текст выглядит «кривовато». `docs/bbb/public/serdal.css` добавляет к тому же семейству кириллицу Source Sans 3
(файлы шрифта лежат на самом сервере, `/serdal/fonts/`), шрифты доски и значки не меняются.

**Перевод.** В штатном русском переводе BBB 4.0 есть строки с неверными шаблонами («Загрузка {0} {1}», пропавшие счётчики
«({count})») — исправлены в `ru-overrides.json` вместе с недостающими строками. Проверка после обновления BBB — сравнить
шаблоны `{…}` в `en.json` и в собранном `/etc/bigbluebutton/serdal/ru.json`.

Скрипт `serdal-bbb-branding` собирает русский перевод и сам ведёт раздел `app:` в `/etc/bigbluebutton/bbb-html5.yml`:
название вкладки `clientTitle: Serdal`, свой стиль `customStyleUrl` и номер сборки `html5ClientBuild` с отпечатком перевода. Второй раздел `app:` вручную
**не дописывайте** — с повторяющимся ключом BBB не запустится.

Зачем отпечаток: клиент грузит перевод по адресу `locales/ru.json?v=<html5ClientBuild>`, и браузер хранит ответ до нескольких
суток, не спрашивая сервер. Поменялся перевод — поменялся адрес — браузеры скачают новый. Действует после перезапуска BBB.

Проверка: `curl -s https://room3.serdal.ru/html5client/locales/ru.json | grep '"app.mediaSharing.modal.slides"'` → «Слайды».
Новые непереведённые строки после обновления BBB: сравните ключи `en.json` и `ru.json` в
`/usr/share/bigbluebutton/html5-client/locales/` и допишите перевод в `docs/bbb/ru-overrides.json`.

### 3.7. Вход по SSH только по ключу

Сначала добавьте свой ключ (`ssh-copy-id root@room3.serdal.ru`) и проверьте вход по нему, затем:

```bash
printf "PasswordAuthentication no\nKbdInteractiveAuthentication no\nPermitRootLogin prohibit-password\n" \
  > /etc/ssh/sshd_config.d/00-serdal-keys-only.conf    # 00 — раньше 50-cloud-init.conf, где пароль включён
sshd -t && systemctl reload ssh
```

Лимиты участников и длительности, запись, микрофоны при входе Serdal передаёт при создании каждого занятия
(«Настройки» → «Видеосвязь» и тариф учителя) — на сервере их настраивать не нужно.

## 4. Проверка на сервере

```bash
sudo bbb-conf --status         # все службы ✔
sudo bbb-conf --check          # ошибки и предупреждения установки — после «Potential problems» должно быть пусто
sudo bbb-conf --secret         # адрес API и секретный ключ — понадобятся в админке
dpkg -l | grep -E 'bbb-webhooks|bbb-playback-video'   # оба пакета установлены
systemctl is-active bbb-webhooks coturn                # обе службы active
ls /usr/local/bigbluebutton/core/scripts/post_publish/ # есть zz_delete_raw.rb
ls /etc/cron.daily/serdal-bbb-purge-deleted            # есть очистка удалённых записей
curl -s https://room3.serdal.ru/bigbluebutton/api       # снаружи: <returncode>SUCCESS</returncode>
```

## 5. Подключение к Serdal

1. Админка → «Настройки» → «Видеосвязь» → «Серверы видеосвязи» → «Добавить».
2. Название, адрес (`URL` из `bbb-conf --secret`), секретный ключ (`Secret`).
3. **Вместимость** — сколько человек сервер выдерживает одновременно. По ней делится нагрузка:
   сервер с вместимостью 200 получает вдвое больше занятий, чем со 100. Начните осторожно
   (для 8 ядер — около 100–150, для 4 ядер / 8 потоков — около 60–80) и поправьте по опыту.
4. «Сохранить» — Serdal сразу проверит сервер: в списке появится нагрузка, без пометки «Не отвечает».
   Версию BBB 4.0 в ответе API не сообщает — у таких серверов версия в списке не показывается.
   Алгоритм подписи запросов подбирается сам.

## 6. Проверка тестовым занятием

Пока сервер новый, выключите у остальных серверов «Принимает новые занятия» (или начните занятие,
когда новый сервер наименее загружен), затем:

1. Начните занятие с записью, зайдите учеником с другого устройства, подождите пару минут, завершите.
2. В админке у занятия видно, кто был в классе — вебхуки работают.
3. Через 10–30 минут (обработка записи) в «Записях» появилась запись и дошла до облака — видео-формат работает.
4. Верните остальным серверам «Принимает новые занятия».

## 7. Вывод сервера из работы

1. Выключите «Принимает новые занятия» — новые занятия туда не пойдут, идущие доработают.
2. Дождитесь, пока записи выгрузятся в облако (в «Записях» у них нет статуса «Загрузка»).
3. Удалите сервер в админке (пока на нём идут занятия, удалить нельзя).
