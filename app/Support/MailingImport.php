<?php

namespace App\Support;

use ZipArchive;

/**
 * Разбор адресов для рассылки: файл CSV / Excel (.xlsx) или текст, вставленный из таблицы.
 *
 * В строке ищем почту в любой колонке (в ячейке может быть несколько адресов — берём все);
 * первая непустая колонка без почты — название школы, вторая — город. Строка без почты в начале — шапка таблицы,
 * дальше — ошибка (попадает в invalid, чтобы админ увидел, что не загрузилось).
 *
 * Результат: ['contacts' => [email => ['email', 'name', 'city']], 'invalid' => ['строка', …]].
 */
class MailingImport
{
    public const MAX_ROWS = 20000;

    private const EMAIL = '/[^\s,;<>"\'():\[\]]+@[^\s,;<>"\'():\[\]]+\.[^\s,;<>"\'():\[\]]{2,}/u';

    /** Текст из поля «Вставить» или содержимое CSV. */
    public static function fromText(string $text): array
    {
        $text = self::toUtf8($text);
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $delimiter = self::delimiter($lines);

        $rows = [];
        foreach ($lines as $line) {
            $rows[] = $delimiter ? str_getcsv($line, $delimiter, '"', '') : [$line];
        }

        return self::fromRows($rows);
    }

    /**
     * Файл: .csv / .txt — как текст, .xlsx — первый лист.
     *
     * @throws \InvalidArgumentException понятное сообщение для админа
     */
    public static function fromFile(string $path, string $extension): array
    {
        return match (strtolower($extension)) {
            'csv', 'txt' => self::fromText((string) file_get_contents($path)),
            'xlsx' => self::fromRows(self::xlsxRows($path)),
            'xls' => throw new \InvalidArgumentException('Старый формат Excel не читается — сохраните таблицу как .xlsx или .csv'),
            default => throw new \InvalidArgumentException('Нужен файл .xlsx или .csv'),
        };
    }

    /** @param array<int, array<int, mixed>> $rows */
    public static function fromRows(array $rows): array
    {
        $contacts = [];
        $invalid = [];
        $seenData = false;

        foreach (array_slice($rows, 0, self::MAX_ROWS) as $cells) {
            $cells = array_map(fn ($c) => trim(preg_replace('/\s+/u', ' ', (string) $c)), (array) $cells);
            if (implode('', $cells) === '') {
                continue;
            }

            $emails = [];
            $other = [];
            foreach ($cells as $cell) {
                $found = self::emails($cell);
                if ($found) {
                    $emails = array_merge($emails, $found);
                } elseif ($cell !== '') {
                    $other[] = $cell;
                }
            }

            if (! $emails) {
                // Строка без почты до первых данных — шапка таблицы («Почта», «Школа»)
                if ($seenData) {
                    $invalid[] = mb_substr(implode(' · ', array_filter($cells, fn ($c) => $c !== '')), 0, 120);
                }
                continue;
            }

            $seenData = true;
            foreach (array_unique($emails) as $email) {
                $contacts[$email] ??= [
                    'email' => $email,
                    'name' => isset($other[0]) ? mb_substr($other[0], 0, 255) : null,
                    'city' => isset($other[1]) ? mb_substr($other[1], 0, 255) : null,
                ];
            }
        }

        return ['contacts' => $contacts, 'invalid' => $invalid];
    }

    /** Все корректные адреса из ячейки, в нижнем регистре. */
    public static function emails(string $cell): array
    {
        if (! str_contains($cell, '@') || ! preg_match_all(self::EMAIL, $cell, $m)) {
            return [];
        }

        $out = [];
        foreach ($m[0] as $raw) {
            $email = mb_strtolower(rtrim(preg_replace('/^mailto:/i', '', $raw), '.'));
            if (filter_var($email, FILTER_VALIDATE_EMAIL, FILTER_FLAG_EMAIL_UNICODE) && mb_strlen($email) <= 191) {
                $out[] = $email;
            }
        }

        return $out;
    }

    /** Разделитель колонок: табуляция (вставка из таблицы), «;» (CSV из русского Excel), «,» или нет. */
    private static function delimiter(array $lines): ?string
    {
        $sample = implode("\n", array_slice(array_filter($lines, fn ($l) => trim($l) !== ''), 0, 50));
        foreach (["\t", ';', ','] as $d) {
            if (str_contains($sample, $d)) {
                return $d;
            }
        }

        return null;
    }

    /** CSV из Excel часто в Windows-1251, иногда с BOM. */
    private static function toUtf8(string $text): string
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1251');
        }

        return $text;
    }

    /** Строки первого листа .xlsx (без сторонних библиотек: это zip с XML). */
    private static function xlsxRows(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \InvalidArgumentException('Файл не открывается — сохраните таблицу как .xlsx или .csv');
        }

        try {
            $strings = [];
            if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                $doc = self::xml($xml);
                foreach ($doc->si as $si) {
                    // Текст ячейки может быть разбит на куски с разным оформлением (<r><t>)
                    $strings[] = isset($si->t) ? (string) $si->t : implode('', array_map(fn ($r) => (string) $r->t, iterator_to_array($si->r, false)));
                }
            }

            $sheet = $zip->getFromName(self::firstSheet($zip));
            if ($sheet === false) {
                throw new \InvalidArgumentException('В файле нет листа с данными');
            }

            $rows = [];
            foreach (self::xml($sheet)->sheetData->row as $row) {
                $cells = [];
                foreach ($row->c as $c) {
                    $col = self::column((string) $c['r']) ?? count($cells);
                    $type = (string) $c['t'];
                    $value = match ($type) {
                        's' => $strings[(int) $c->v] ?? '',
                        'inlineStr' => (string) ($c->is->t ?? ''),
                        default => (string) $c->v,
                    };
                    $cells[$col] = $value;
                }
                if ($cells) {
                    $filled = array_fill(0, max(array_keys($cells)) + 1, '');
                    $rows[] = array_replace($filled, $cells);
                }
                if (count($rows) >= self::MAX_ROWS) {
                    break;
                }
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    /** Путь к первому листу книги (обычно xl/worksheets/sheet1.xml, но не всегда). */
    private static function firstSheet(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbook !== false && $rels !== false) {
            $wb = self::xml($workbook);
            $first = $wb->sheets->sheet[0] ?? null;
            $rid = $first ? (string) ($first->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] ?? '') : '';
            foreach (self::xml($rels)->Relationship as $rel) {
                if ((string) $rel['Id'] === $rid) {
                    $target = ltrim((string) $rel['Target'], '/');

                    return str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
                }
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /** «C12» → 2. */
    private static function column(string $ref): ?int
    {
        if (! preg_match('/^([A-Z]+)/', $ref, $m)) {
            return null;
        }
        $n = 0;
        foreach (str_split($m[1]) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }

    private static function xml(string $xml): \SimpleXMLElement
    {
        $doc = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
        if ($doc === false) {
            throw new \InvalidArgumentException('Файл повреждён — сохраните таблицу заново как .xlsx или .csv');
        }

        return $doc;
    }
}
