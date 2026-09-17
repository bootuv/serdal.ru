<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class BackupToS3 extends Command
{
    protected $signature = 'backup:run
        {--files : Дополнительно заархивировать storage/app/public (загруженные пользователями файлы)}
        {--keep-days=14 : Сколько дней хранить ежедневные копии}
        {--keep-months=6 : Сколько месяцев хранить копии, сделанные 1-го числа}';

    protected $description = 'Бэкап базы данных (и, опционально, загруженных файлов) в S3 с ротацией старых копий';

    private const PREFIX = 'backups';

    public function handle(): int
    {
        $tmpDir = storage_path('app/temp');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $stamp = now()->format('Y-m-d_His');
        $ok = $this->backupDatabase($tmpDir, $stamp);

        if ($this->option('files')) {
            $ok = $this->backupFiles($tmpDir, $stamp) && $ok;
        }

        try {
            $this->prune();
        } catch (\Throwable $e) {
            Log::warning('[Backup] Prune failed: ' . $e->getMessage());
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function backupDatabase(string $tmpDir, string $stamp): bool
    {
        $db = config('database.connections.' . config('database.default'));
        $file = "{$tmpDir}/backup_db_{$stamp}.sql.gz";

        // Пароль передаём через файл опций, а не аргументом: аргументы видны всем в `ps`
        $cnf = tempnam($tmpDir, 'backup_cnf_');
        chmod($cnf, 0600);
        file_put_contents($cnf, sprintf(
            "[client]\nuser=\"%s\"\npassword=\"%s\"\n%s",
            addcslashes((string) $db['username'], '"\\'),
            addcslashes((string) $db['password'], '"\\'),
            !empty($db['unix_socket'])
                ? "socket={$db['unix_socket']}\n"
                : "host={$db['host']}\nport={$db['port']}\n"
        ));

        try {
            // bash + pipefail: в sh упавший mysqldump дал бы «успешный» пустой gzip
            $process = new Process(
                [
                    'bash', '-c',
                    'set -o pipefail; mysqldump --defaults-extra-file="$CNF" --single-transaction --quick'
                    . ' --no-tablespaces --routines --triggers --default-character-set=utf8mb4 "$DB" | gzip -6 > "$OUT"',
                ],
                null,
                ['CNF' => $cnf, 'DB' => $db['database'], 'OUT' => $file],
            );
            $process->setTimeout(1800);
            $process->run();

            if (!$process->isSuccessful() || !is_file($file) || filesize($file) < 1024) {
                return $this->reportFailure('database', trim($process->getErrorOutput()) ?: 'пустой дамп');
            }

            return $this->upload($file, 'db/' . basename($file), 'database');
        } finally {
            @unlink($cnf);
            @unlink($file);
        }
    }

    private function backupFiles(string $tmpDir, string $stamp): bool
    {
        $source = storage_path('app/public');
        if (!is_dir($source)) {
            return true;
        }

        $file = "{$tmpDir}/backup_files_{$stamp}.tar.gz";

        try {
            $process = new Process(['tar', '-czf', $file, '-C', dirname($source), basename($source)]);
            $process->setTimeout(3600);
            $process->run();

            // tar возвращает 1, если файл изменился во время чтения — для живого каталога это норма
            if ($process->getExitCode() > 1 || !is_file($file)) {
                return $this->reportFailure('files', trim($process->getErrorOutput()));
            }

            return $this->upload($file, 'files/' . basename($file), 'files');
        } finally {
            @unlink($file);
        }
    }

    private function upload(string $localFile, string $key, string $what): bool
    {
        $size = filesize($localFile);
        $stream = fopen($localFile, 'r');

        try {
            // Строго private: в дампе персональные данные и платежи. Диск с throw=true,
            // чтобы ошибка S3 не превратилась в молчаливое false.
            Storage::build(array_merge(config('filesystems.disks.s3'), ['throw' => true]))
                ->put(self::PREFIX . '/' . $key, $stream, 'private');
        } catch (\Throwable $e) {
            return $this->reportFailure($what, $e->getMessage());
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        Log::info("[Backup] {$what} uploaded", ['key' => self::PREFIX . '/' . $key, 'size' => $size]);
        $this->info("{$what}: " . self::PREFIX . "/{$key} (" . round($size / 1048576, 1) . ' МБ)');

        return true;
    }

    /**
     * Ежедневные копии храним keep-days дней, копии от 1-го числа — keep-months месяцев.
     */
    private function prune(): void
    {
        $disk = Storage::disk('s3');
        $dailyCutoff = now()->subDays((int) $this->option('keep-days'));
        $monthlyCutoff = now()->subMonths((int) $this->option('keep-months'));

        foreach (['db', 'files'] as $dir) {
            foreach ($disk->files(self::PREFIX . '/' . $dir) as $path) {
                if (!preg_match('/_(\d{4}-\d{2}-\d{2})_\d{6}\./', $path, $m)) {
                    continue;
                }

                $date = \Carbon\Carbon::parse($m[1]);
                $isMonthly = $date->day === 1;

                if ($date->lt($isMonthly ? $monthlyCutoff : $dailyCutoff)) {
                    $disk->delete($path);
                    $this->line("удалена старая копия: {$path}");
                }
            }
        }
    }

    private function reportFailure(string $what, string $reason): bool
    {
        // error-уровень: неудавшийся бэкап должен быть заметен в логе
        Log::error("[Backup] {$what} failed: {$reason}");
        $this->error("{$what}: {$reason}");

        return false;
    }
}
