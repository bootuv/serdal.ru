<?php

namespace App\Services;

use App\Models\Direct;
use App\Models\Subject;
use App\Models\TeacherApplication;
use App\Support\SeoSettings;
use App\Support\SubjectIcons;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Справочники «Предметы» и «Направления»: переименование, добавление, объединение, удаление неиспользуемых,
 * поиск похожих названий. Связи: учителя (subject_user / direct_user) и заявки учителей (JSON-массивы id в teacher_applications).
 */
class DictionaryService
{
    public const KINDS = ['subjects', 'directs'];

    /** @return array{model: class-string, pivot: string, key: string, column: string} */
    private function meta(string $kind): array
    {
        return match ($kind) {
            'subjects' => ['model' => Subject::class, 'pivot' => 'subject_user', 'key' => 'subject_id', 'column' => 'subjects'],
            'directs' => ['model' => Direct::class, 'pivot' => 'direct_user', 'key' => 'direct_id', 'column' => 'directs'],
        };
    }

    /**
     * Все значения справочника по алфавиту с использованием: teachers — учителей, applications — заявок на рассмотрении.
     * У предметов ещё значок, назначенный вручную: icon и color (null — подбирается по названию, App\Support\SubjectIcons).
     *
     * @return Collection<int, array{id: int, name: string, teachers: int, applications: int, icon: ?string, color: ?string}>
     */
    public function items(string $kind): Collection
    {
        $m = $this->meta($kind);
        $teachers = DB::table($m['pivot'])->select($m['key'], DB::raw('count(distinct user_id) as n'))->groupBy($m['key'])->pluck('n', $m['key']);
        $applications = $this->applicationCounts($m['column']);

        $columns = $kind === 'subjects' ? ['id', 'name', 'icon', 'color'] : ['id', 'name'];

        return $m['model']::orderBy('name')->get($columns)
            ->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'teachers' => (int) ($teachers[$item->id] ?? 0),
                'applications' => (int) ($applications[$item->id] ?? 0),
                'icon' => $item->icon ?? null,
                'color' => $item->color ?? null,
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /** Заявки на рассмотрении, где выбрано значение: id => сколько. */
    private function applicationCounts(string $column): array
    {
        $counts = [];
        TeacherApplication::where('status', TeacherApplication::STATUS_PENDING)->pluck($column)
            ->each(function ($ids) use (&$counts) {
                foreach (array_unique(array_map('intval', (array) $ids)) as $id) {
                    $counts[$id] = ($counts[$id] ?? 0) + 1;
                }
            });

        return $counts;
    }

    /** Есть ли уже такое название (без учёта регистра и лишних пробелов). */
    public function exists(string $kind, string $name, ?int $exceptId = null): bool
    {
        $norm = self::normalize($name);

        return $this->meta($kind)['model']::query()
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->pluck('name')
            ->contains(fn ($n) => self::normalize($n) === $norm);
    }

    public function add(string $kind, string $name): object
    {
        $item = $this->meta($kind)['model']::create(['name' => self::clean($name)]);
        $this->flushPublicPages();

        return $item;
    }

    public function rename(string $kind, int $id, string $name): void
    {
        $this->meta($kind)['model']::findOrFail($id)->update(['name' => self::clean($name)]);
        $this->flushPublicPages();
    }

    /** Значок предмета в каталоге: null — подбирать по названию. Неизвестные значения не сохраняются. */
    public function setSubjectIcon(int $id, ?string $icon, ?string $color): void
    {
        Subject::findOrFail($id)->update([
            'icon' => SubjectIcons::validMark($icon) ? $icon : null,
            'color' => isset(SubjectIcons::COLORS[$color ?? '']) ? $color : null,
        ]);
        $this->flushPublicPages();
    }

    /** Названия — это заголовки и адреса страниц каталога (/repetitory/…), карта сайта и llms.txt: пересобрать сразу. */
    private function flushPublicPages(): void
    {
        foreach (SeoSettings::CACHE_KEYS as $key) {
            Cache::forget($key);
        }
    }

    /** Удалить можно только значение без учителей и заявок на рассмотрении. Возвращает false, если используется. */
    public function delete(string $kind, int $id): bool
    {
        $item = collect($this->items($kind))->firstWhere('id', $id);
        if (! $item || $item['teachers'] > 0 || $item['applications'] > 0) {
            return false;
        }

        $m = $this->meta($kind);
        DB::transaction(function () use ($m, $id) {
            // В рассмотренных заявках убираем ссылку на удалённое значение
            $this->rewriteApplications($m['column'], $id, null);
            $m['model']::whereKey($id)->delete();
        });
        $this->flushPublicPages();

        return true;
    }

    /** Объединить: связи учителей и заявок переходят к $targetId, $sourceId удаляется. */
    public function merge(string $kind, int $sourceId, int $targetId): void
    {
        if ($sourceId === $targetId) {
            return;
        }

        $m = $this->meta($kind);
        $m['model']::findOrFail($targetId);
        $m['model']::findOrFail($sourceId);

        DB::transaction(function () use ($m, $sourceId, $targetId) {
            $already = DB::table($m['pivot'])->where($m['key'], $targetId)->pluck('user_id');
            // У кого уже есть оба — оставляем одну связь
            DB::table($m['pivot'])->where($m['key'], $sourceId)->whereIn('user_id', $already)->delete();
            DB::table($m['pivot'])->where($m['key'], $sourceId)->update([$m['key'] => $targetId]);

            $this->rewriteApplications($m['column'], $sourceId, $targetId);
            $m['model']::whereKey($sourceId)->delete();
        });
        $this->flushPublicPages();
    }

    /** В JSON-массивах заявок заменить id (или убрать, если $to = null). */
    private function rewriteApplications(string $column, int $from, ?int $to): void
    {
        TeacherApplication::query()->whereNotNull($column)->get(['id', $column])
            ->each(function (TeacherApplication $app) use ($column, $from, $to) {
                $ids = array_map('intval', (array) $app->{$column});
                if (! in_array($from, $ids, true)) {
                    return;
                }
                $ids = array_map(fn ($id) => $id === $from ? $to : $id, $ids);
                $ids = array_values(array_unique(array_filter($ids, fn ($id) => $id !== null)));
                $app->forceFill([$column => $ids])->saveQuietly();
            });
    }

    /**
     * Похожие названия — кандидаты на объединение. Пара похожа, если после нормализации (регистр, пробелы, ё)
     * названия совпадают, одно целиком содержит другое по словам («Английский» / «Английский язык», «ЕГЭ» / «Подготовка к ЕГЭ»)
     * или отличаются не больше чем на 2 буквы (опечатки; только для названий длиннее 4 букв).
     * source — что влить (используется реже), target — во что.
     *
     * @return array<int, array{source: array, target: array}>
     */
    public function similar(string $kind): array
    {
        $items = $this->items($kind)->values()->all();
        $pairs = [];

        for ($i = 0; $i < count($items); $i++) {
            for ($j = $i + 1; $j < count($items); $j++) {
                if (! self::similarNames($items[$i]['name'], $items[$j]['name'])) {
                    continue;
                }
                [$a, $b] = [$items[$i], $items[$j]];
                // Остаётся то, у чего больше учителей, затем заявок; при равенстве — более полное (длинное) название
                $rank = fn ($x) => [$x['teachers'], $x['applications'], mb_strlen($x['name'])];
                $aWins = $rank($a) >= $rank($b);
                [$source, $target] = $aWins ? [$b, $a] : [$a, $b];
                $pairs[] = ['source' => $source, 'target' => $target];
            }
        }

        return $pairs;
    }

    public static function similarNames(string $a, string $b): bool
    {
        $a = self::normalize($a);
        $b = self::normalize($b);

        if ($a === '' || $b === '') {
            return false;
        }
        if ($a === $b) {
            return true;
        }
        // Одно название содержит другое целыми словами
        [$short, $long] = mb_strlen($a) <= mb_strlen($b) ? [$a, $b] : [$b, $a];
        if (str_contains(' ' . $long . ' ', ' ' . $short . ' ')) {
            return true;
        }
        // Опечатки: расстояние Левенштейна ≤ 2 для названий длиннее 4 букв
        if (mb_strlen($a) > 4 && mb_strlen($b) > 4 && self::levenshtein($a, $b) <= 2) {
            return true;
        }

        return false;
    }

    /** Нижний регистр, ё → е, без пунктуации и лишних пробелов. */
    public static function normalize(string $name): string
    {
        $s = mb_strtolower(str_replace(['ё', 'Ё'], 'е', $name));
        $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s);

        return trim(preg_replace('/\s+/u', ' ', $s));
    }

    /** Название для хранения: без лишних пробелов. */
    public static function clean(string $name): string
    {
        return trim(preg_replace('/\s+/u', ' ', $name));
    }

    /** Расстояние Левенштейна по буквам (levenshtein() в PHP считает байты, а кириллица — 2 байта). */
    private static function levenshtein(string $a, string $b): int
    {
        $x = mb_str_split($a);
        $y = mb_str_split($b);
        $prev = range(0, count($y));

        foreach ($x as $i => $cx) {
            $cur = [$i + 1];
            foreach ($y as $j => $cy) {
                $cur[$j + 1] = min($prev[$j + 1] + 1, $cur[$j] + 1, $prev[$j] + ($cx === $cy ? 0 : 1));
            }
            $prev = $cur;
        }

        return $prev[count($y)];
    }
}
