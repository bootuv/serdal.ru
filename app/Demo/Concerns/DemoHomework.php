<?php

namespace App\Demo\Concerns;

use App\Demo\World;
use App\Models\Homework;
use App\Models\HomeworkSubmission as Sub;
use App\Models\Room;
use App\Services\HomeworkSubmissionService as Hw;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Домашние задания демо-мира: задания 301–310 и работы учеников 401–408 — моделями в памяти, со связями
 * (room, students, submissions, homework, student), как их отдаёт база настоящим экранам «Задания», «Задание», «Проверка работы».
 * Работы 401–403 — те же, что в «Нужно проверить» на «Сегодня» (App\Demo\Teacher\Today::review).
 *
 * Файлы не лежат на s3: фото ответов — нарисованные страницы тетради (SVG), ссылки на остальные файлы
 * ведут за пределы демо — demo-cabinet.js покажет тост вместо перехода.
 */
trait DemoHomework
{
    /** @var array<int, Homework>|null */
    private ?array $homeworks = null;

    /** Задание по id (со связями) или null. */
    protected function homework(int $id): ?Homework
    {
        return $this->homeworks()[$id] ?? null;
    }

    /** @return Collection<int, Homework> все задания учителя */
    protected function allHomeworks(): Collection
    {
        return collect($this->homeworks());
    }

    /** @return Collection<int, Sub> все работы учеников */
    protected function allSubmissions(): Collection
    {
        return $this->allHomeworks()->flatMap(fn (Homework $h) => $h->submissions)->keyBy('id');
    }

    protected function submission(int $id): ?Sub
    {
        return $this->allSubmissions()->get($id);
    }

    /** Очередь проверки — как Hw::toReview: сданные без оценки, самые давние сверху. */
    protected function toReview(): Collection
    {
        return $this->allSubmissions()
            ->filter(fn (Sub $s) => $s->status === Sub::STATUS_SUBMITTED && $s->grade === null)
            ->sortBy(fn (Sub $s) => [$s->submitted_at->timestamp, $s->id])
            ->values();
    }

    /** Плитки файлов — в форме Hw::fileViews, без s3. files: путь => [имя, байты]. */
    protected function demoFileViews(array $files, array $marked = []): array
    {
        $views = [];
        foreach ($files as $path => [$name, $bytes]) {
            $image = Hw::isImage($path);
            $views[] = [
                'path' => $path,
                'name' => $name,
                'meta' => Hw::kind($path) . ($bytes ? ' · ' . Hw::size($bytes) : ''),
                'image' => $image,
                'annotated' => isset($marked[$path]),
                'url' => $image ? self::notebookPhoto($path, isset($marked[$path])) : url('/demo-files/' . basename($path)),
            ];
        }

        return $views;
    }

    /** Вызов закрытого метода настоящего компонента: строки получаются ровно такими же, как в кабинете. */
    protected function real(string $component, string $method, mixed ...$args): mixed
    {
        return (fn () => $this->{$method}(...$args))->call(new $component);
    }

    /** @return array<int, Homework> */
    private function homeworks(): array
    {
        if ($this->homeworks !== null) {
            return $this->homeworks;
        }

        $now = Carbon::now();
        $today = Carbon::today();
        $at = fn (int $days, string $time) => $today->copy()->addDays($days)->setTimeFromTimeString($time);

        // id => [название, занятие, ученики, выдано (дней назад), срок, макс. балл, видно ученикам, условие, файлы]
        $tasks = [
            301 => ['Квадратные уравнения, вариант 3', 201, [101], 4, $at(1, '20:00'), 10, true,
                '<p>Решите уравнения 1–10 из варианта 3. Для каждого найдите дискриминант и проверьте корни подстановкой.</p><p>Решение — фото страниц тетради.</p>',
                ['homework-attachments/kvadratnye-v3.pdf' => ['Квадратные уравнения, вариант 3.pdf', 245760]]],
            302 => ['Производная: задачи 1–12', 202, [102], 6, $at(-1, '20:00'), 10, true,
                '<p>Задачи 1–12 из сборника Ященко, раздел «Производная». В задачах с касательной сделайте чертёж.</p>',
                ['homework-attachments/proizvodnaya.pdf' => ['Производная — задачи.pdf', 532480]]],
            303 => ['Законы Ньютона, задачи 1–8', 203, [103], 7, $at(-3, '20:00'), 10, true,
                '<p>Задачи 1–8 из листка. В каждой нарисуйте силы, действующие на тело, и запишите второй закон Ньютона в проекциях.</p>',
                ['homework-attachments/nyuton.pdf' => ['Законы Ньютона — листок.pdf', 389120]]],
            304 => ['Пробник ЕГЭ №4, часть 2', 206, [106, 107, 108], 9, $at(-4, '20:00'), 10, true,
                '<p>Задания 13–19 пробного варианта. Оформляйте решение полностью, как на экзамене: обоснования обязательны.</p>',
                ['homework-attachments/probnik-4.pdf' => ['Пробник ЕГЭ №4.pdf', 1153434]]],
            305 => ['Подобные треугольники', 204, [104], 8, $at(-5, '20:00'), 10, true,
                '<p>Номера 535–542 из учебника Атанасяна. К каждой задаче — чертёж.</p>', []],
            306 => ['Дроби: сложение и вычитание', 205, [105], 1, $at(2, '20:00'), 10, true,
                '<p>Карточка с примерами 1–20. Не забудьте приводить дроби к общему знаменателю и сокращать ответ.</p>',
                ['homework-attachments/drobi.pdf' => ['Дроби — карточка.pdf', 184320]]],
            307 => ['Тригонометрия: формулы приведения', 206, [106, 107, 108], 1, $at(5, '20:00'), 10, true,
                '<p>Выучите формулы приведения и решите задания 1–15 из файла. Это пригодится в задании 13 ЕГЭ.</p>',
                ['homework-attachments/trigonometriya.pdf' => ['Формулы приведения.pdf', 716800]]],
            308 => ['Системы уравнений, вариант 1', 201, [101], 0, null, 10, false,
                '<p>Решите системы 1–8 способом подстановки, системы 9–12 — способом сложения.</p>', []],
            309 => ['Кинематика: равноускоренное движение', 203, [103], 10, $at(-2, '20:00'), 10, true,
                '<p>Задачи 1–10. Для каждой постройте график скорости.</p>', []],
            310 => ['Логарифмы: задачи 1–10', 202, [102], 14, $at(-9, '20:00'), 10, true,
                '<p>Вычислите значения выражений 1–10, используя свойства логарифмов.</p>', []],
        ];

        // id => [задание, ученик, статус, сдано, оценка, ответ, файлы, комментарий учителя, пересдано, изменено, пометки]
        $works = [
            401 => [301, 101, Sub::STATUS_SUBMITTED, $now->copy()->subHours(3), null,
                '<p>Решил все, кроме пятого: там дискриминант отрицательный, получается, корней нет? Проверьте, пожалуйста.</p>',
                ['homework-submissions/401-1.jpg' => ['Фото 1', 2411724], 'homework-submissions/401-2.jpg' => ['Фото 2', 2202010]], null, false, null, []],
            402 => [302, 102, Sub::STATUS_SUBMITTED, $now->copy()->subDay()->setTime(21, 15), null,
                '<p>Задачи 1–12 решены. В 9 и 11 использовала правило производной произведения, в 12 сделала чертёж касательной.</p>',
                ['homework-submissions/402-1.pdf' => ['Производная — Аушева.pdf', 1887437], 'homework-submissions/402-2.jpg' => ['Чертёж к задаче 12.jpg', 1572864]], null, false, null, []],
            403 => [303, 103, Sub::STATUS_SUBMITTED, $now->copy()->subDays(3)->setTime(18, 2), null,
                '<p>Сделал все восемь. В седьмой не уверен с направлением силы трения.</p>',
                ['homework-submissions/403-1.jpg' => ['Фото 1', 2097152]], null, false, null, []],
            404 => [304, 107, Sub::STATUS_GRADED, $at(-5, '19:20'), 9,
                '<p>Решила 13–18, в 19 только пункт а.</p>',
                ['homework-submissions/404-1.jpg' => ['Фото 1', 2516582]],
                '<p>Очень хорошо! В 15 аккуратнее с ОДЗ — пометила на фото. 19б разберём на занятии.</p>', false, $at(-3, '10:30'),
                ['homework-submissions/404-1.jpg' => 'feedback-attachments/404-1-marked.jpg']],
            405 => [304, 106, Sub::STATUS_GRADED, $at(-4, '19:40'), 7,
                '<p>13, 14, 15 и 17 решил, 16 не получилось.</p>',
                ['homework-submissions/405-1.jpg' => ['Фото 1', 2306867]],
                '<p>Решения верные, но в 14 не хватает обоснования. 16 разберём в среду.</p>', false, $at(-3, '10:45'), []],
            406 => [304, 108, Sub::STATUS_REVISION_REQUESTED, $at(-5, '22:10'), null,
                '<p>Сделал 13 и 15.</p>',
                ['homework-submissions/406-1.jpg' => ['Фото 1', 1992294]],
                '<p>В 15 потерян знак при переносе — посмотри пометки на фото. Исправь и дореши 14 и 17.</p>', false, $at(-2, '11:00'),
                ['homework-submissions/406-1.jpg' => 'feedback-attachments/406-1-marked.jpg']],
            407 => [305, 104, Sub::STATUS_GRADED, $at(-6, '17:05'), 10,
                '<p>Все задачи решены, чертежи на фото.</p>',
                ['homework-submissions/407-1.jpg' => ['Фото 1', 2202010]],
                '<p>Отлично! Чертежи аккуратные, решения полные.</p>', false, $at(-5, '09:15'), []],
            408 => [310, 102, Sub::STATUS_GRADED, $at(-10, '20:40'), 8,
                '<p>Готово, в 7 и 9 сомневаюсь.</p>',
                ['homework-submissions/408-1.pdf' => ['Логарифмы — Аушева.pdf', 942080]],
                '<p>В 7 ошибка в свойстве логарифма частного, 9 — верно.</p>', false, $at(-9, '12:00'), []],
        ];

        $rooms = collect(World::ROOMS)->map(function (array $r, int $id) {
            $room = new Room;
            $room->forceFill(['id' => $id, 'name' => $r[0], 'type' => $r[1], 'user_id' => World::TEACHER_ID]);
            $room->exists = true;

            return $room;
        });

        $result = [];
        foreach ($tasks as $id => [$title, $roomId, $studentIds, $ago, $deadline, $max, $visible, $text, $files]) {
            // submitted_count у модели — аксессор с запросом к базе; в памяти берём посчитанное ниже (как withCount)
            $h = new class extends Homework
            {
                protected $table = 'homeworks';

                public function getSubmittedCountAttribute(): int
                {
                    return (int) ($this->attributes['submitted_count'] ?? 0);
                }
            };
            $h->forceFill([
                'id' => $id,
                'teacher_id' => World::TEACHER_ID,
                'title' => $title,
                'description' => $text,
                'room_id' => $roomId,
                'deadline' => $deadline,
                'max_score' => $max,
                'is_visible' => $visible,
                'attachments' => array_keys($files),
                'file_names' => array_map(fn ($f) => $f[0], $files),
                'created_at' => $today->copy()->subDays($ago)->setTime(15, 30),
            ]);
            $h->exists = true;
            $h->demoFiles = $files;
            $h->setRelation('room', $rooms[$roomId]);
            $h->setRelation('students', new EloquentCollection(collect($studentIds)->map(fn ($s) => World::student($s))->sortBy('name')->values()->all()));

            $subs = [];
            foreach ($works as $sid => [$hid, $studentId, $status, $submitted, $grade, $content, $subFiles, $feedback, $resubmitted, $updated, $marks]) {
                if ($hid !== $id) {
                    continue;
                }
                $s = new Sub;
                $s->forceFill([
                    'id' => $sid,
                    'homework_id' => $id,
                    'student_id' => $studentId,
                    'status' => $status,
                    'content' => $content,
                    'attachments' => array_keys($subFiles),
                    'file_names' => array_map(fn ($f) => $f[0], $subFiles),
                    'annotations' => $marks,
                    'feedback' => $feedback,
                    'grade' => $grade,
                    'submitted_at' => $submitted,
                    'updated_at' => $updated ?? $submitted,
                    'created_at' => $submitted,
                ]);
                $s->exists = true;
                $s->demoFiles = $subFiles;
                $s->resubmitted = $resubmitted;
                $s->setRelation('homework', $h);
                $s->setRelation('student', World::student($studentId));
                $subs[] = $s;
            }
            $h->setRelation('submissions', new EloquentCollection($subs));

            // Числа, которые настоящий список берёт из Hw::withProgress
            $h->students_count = count($studentIds);
            $h->submitted_count = collect($subs)->whereNotNull('submitted_at')->count();
            $h->graded_count = collect($subs)->whereNotNull('grade')->count();
            $h->review_count = collect($subs)->filter(fn ($s) => $s->status === Sub::STATUS_SUBMITTED && $s->grade === null)->count();
            $h->revision_count = collect($subs)->where('status', Sub::STATUS_REVISION_REQUESTED)->count();

            $result[$id] = $h;
        }

        return $this->homeworks = $result;
    }

    /**
     * «Фото» страницы тетради в клетку с решением (SVG data:) — вместо фото ученика на s3.
     * marked — с красными пометками учителя.
     */
    protected static function notebookPhoto(string $path, bool $marked = false): string
    {
        $pages = [
            '401-1' => ['№1  x² − 5x + 6 = 0', 'D = 25 − 24 = 1', 'x₁ = 3,  x₂ = 2', '№2  x² + x − 12 = 0', 'D = 1 + 48 = 49', 'x₁ = 3,  x₂ = −4', '№3  2x² − 7x + 3 = 0', 'D = 49 − 24 = 25', 'x₁ = 3,  x₂ = ½'],
            '401-2' => ['№4  x² − 6x + 9 = 0', 'D = 36 − 36 = 0', 'x = 3', '№5  x² + 2x + 5 = 0', 'D = 4 − 20 = −16 < 0', 'корней нет ?', '№6  3x² − 12 = 0', 'x² = 4', 'x = ±2'],
            '402-2' => ['№12  y = x² − 3x,  x₀ = 2', 'y′ = 2x − 3', 'y′(2) = 1', 'y(2) = −2', 'y = 1·(x − 2) − 2', 'y = x − 4', 'Ответ: y = x − 4'],
            '403-1' => ['№1  F = ma', 'a = F / m = 12 / 4 = 3 м/с²', '№2  Ox: F − Fтр = ma', 'Fтр = μmg = 0,2·5·10 = 10 Н', 'a = (30 − 10) / 5 = 4 м/с²', '№7  Fтр направлена', 'против движения', 'a = μg = 2 м/с²'],
            '404-1' => ['№15  log₂(x − 1) ≤ 3', '0 < x − 1 ≤ 8', '1 < x ≤ 9', '№13  а) cos 2x = sin x', '1 − 2sin²x = sin x', 'sin x = ½ или sin x = −1', 'x = π/6 + 2πk, …'],
            '405-1' => ['№13  а) 2sin²x − sin x = 0', 'sin x (2sin x − 1) = 0', 'x = πk', 'x = (−1)ᵏ π/6 + πk', '№14  V = ⅓ S·h', 'S = 36,  h = 8', 'V = 96'],
            '406-1' => ['№15  (x − 3)/(x + 2) ≥ 0', 'x − 3 ≥ 0 и x + 2 > 0', 'x ≥ 3', 'Ответ: x ≥ 3', '№13  а) tg x = 1', 'x = π/4 + πk'],
            '407-1' => ['№535  △ABC ∼ △A₁B₁C₁', 'k = AB / A₁B₁ = 6 / 4 = 1,5', 'BC = 1,5 · 5 = 7,5', '№536  ∠A = ∠A₁,', '∠B = ∠B₁ ⇒ подобны', 'AC = 9 · ⅔ = 6'],
        ];
        $key = pathinfo($path, PATHINFO_FILENAME);
        $lines = $pages[$key] ?? $pages['401-1'];

        $grid = '';
        for ($x = 0; $x <= 600; $x += 24) {
            $grid .= "<line x1='$x' y1='0' x2='$x' y2='640'/>";
        }
        for ($y = 0; $y <= 640; $y += 24) {
            $grid .= "<line x1='0' y1='$y' x2='600' y2='$y'/>";
        }

        $text = '';
        foreach ($lines as $i => $line) {
            $y = 72 + $i * 64;
            $text .= "<text x='60' y='$y'>" . htmlspecialchars($line, ENT_XML1) . '</text>';
        }

        $marks = $marked
            ? "<g fill='none' stroke='#DC2626' stroke-width='4' stroke-linecap='round'><ellipse cx='230' cy='126' rx='150' ry='34'/><path d='M420 118 l40 -30'/></g>"
                . "<text x='400' y='80' fill='#DC2626' font-size='28'>" . ($key === '406-1' ? 'знак!' : 'ОДЗ!') . '</text>'
            : '';

        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='600' height='640' viewBox='0 0 600 640'>"
            . "<rect width='600' height='640' fill='#fbfaf6'/>"
            . "<g stroke='#c9d6e8' stroke-width='1'>$grid</g>"
            . "<line x1='540' y1='0' x2='540' y2='640' stroke='#e8a0a0' stroke-width='2'/>"
            . "<g fill='#23408e' font-family='Bradley Hand, Segoe Print, Comic Sans MS, cursive' font-size='30'>$text</g>"
            . $marks
            . '</svg>';

        return 'data:image/svg+xml;charset=utf-8,' . rawurlencode($svg);
    }
}
