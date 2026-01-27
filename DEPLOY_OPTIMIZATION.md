# Руководство по оптимизации сервера (DEPLOY_OPTIMIZATION.md)

Это руководство поможет вам настроить сервер для максимальной производительности проекта.

## 1. Настройка Redis (Критически важно)

Сейчас проект использует базу данных для кэша и сессий. Это создает лишнюю нагрузку. Redis будет работать в 10-100 раз быстрее.

### Шаг 1: Установка Redis на сервер
Выполните команды на сервере (Ubuntu/Debian):
```bash
sudo apt update
sudo apt install redis-server
```

**Важно**: Проверьте версию PHP перед установкой расширения:
```bash
php -v
```
Если у вас 8.2 — ставьте `php8.2-redis`, если 8.4 — `php8.4-redis`.

```bash
# Пример для PHP 8.2
sudo apt install php8.2-redis
# Или для PHP 8.4
sudo apt install php8.4-redis
```

Запустите Redis:
```bash
sudo systemctl enable redis-server
sudo systemctl start redis-server
```

Убедитесь, что Redis работает:
```bash
redis-cli ping
# Должен ответить: PONG
```

### Шаг 2: Обновление .env файла
Откройте файл `.env` на сервере и измените следующие параметры:

```ini
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis

# Остальное можно оставить как есть, если Redis локальный
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

## 2. Оптимизация PHP (Opcache)

Убедитесь, что Opcache включен и настроен для продакшена в вашем `php.ini` (обычно `/etc/php/8.2/fpm/php.ini`):

```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
# ВАЖНО для продакшена: отключает проверку изменений файлов (нужен перезапуск PHP при деплое)
opcache.validate_timestamps=0
```

После изменения `php.ini` перезапустите PHP-FPM:
```bash
sudo systemctl restart php8.2-fpm
```

## 3. Оптимизация базы данных

Мы добавили необходимые индексы. Периодически (раз в месяц) можно запускать команду оптимизации, если используете MySQL:
```bash
mysqlcheck -o --all-databases
```

## 4. Очереди

Убедитесь, что workers очередей запущены через Supervisor, а не просто `php artisan queue:work` в консоли.
Пример конфига Supervisor (`/etc/supervisor/conf.d/serdal-worker.conf`):

```ini
[program:serdal-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/project/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/project/storage/logs/worker.log
```
