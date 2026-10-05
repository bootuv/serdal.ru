<?php

namespace App\Demo\Teacher;

use App\Demo\Concerns\DemoHomework;
use App\Demo\Screen;
use App\Models\HomeworkSubmission as Sub;
use App\Support\HumanDate;
use App\Support\RichText;
use Illuminate\Support\HtmlString;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

/**
 * «Проверка работы» — App\Livewire\Cabinet\Teacher\Review: ответ ученика с фото, оценка, комментарий,
 * «Принять и оценить» / «Вернуть на доработку», пометки на фото.
 *
 * Состояние в адресе: grade — выбранная оценка, done=graded|returned — работу только что приняли или вернули,
 * editing — «Изменить оценку», photo — номер фото на холсте пометок, photoList — список фото в окне пометок,
 * error=grade — «Принять» без оценки (ошибка, как у настоящей проверки).
 */
class Review extends Screen
{
    use DemoHomework;

    public const PATH = 'tasks/review/{submission}';

    public const EXAMPLES = ['tasks/review/401', 'tasks/review/402', 'tasks/review/403', 'tasks/review/404', 'tasks/review/406', 'tasks/review/407',
        'tasks/review/401?grade=8', 'tasks/review/401?error=grade', 'tasks/review/401?grade=8&done=graded', 'tasks/review/403?done=returned',
        'tasks/review/404?editing=1', 'tasks/review/401?photo=1', 'tasks/review/401?photoList=1'];

    public string $view = 'livewire.cabinet.teacher.review';

    public string $title = 'Проверка работы';

    public ?string $active = 'tasks';

    public function actions(): array
    {
        $graded = $this->state('grade', '') !== '';

        return [
            'pickGrade' => ['set' => ['grade' => '{0}', 'error' => null]],
            // Без оценки настоящая проверка показывает ошибку под шкалой
            'accept' => $graded
                ? ['set' => ['done' => 'graded', 'editing' => null, 'error' => null], 'toast' => 'Работа принята']
                : ['set' => ['error' => 'grade']],
            'giveBack' => ['set' => ['done' => 'returned', 'grade' => null, 'error' => null], 'toast' => 'Работа возвращена на доработку'],
            'edit' => ['set' => ['editing' => '1', 'done' => null]],
            'cancelEdit' => ['set' => ['editing' => null, 'grade' => null, 'error' => null]],
            'annotate' => ['set' => ['photo' => '{0}', 'photoList' => null]],
            'showPhotos' => ['set' => ['photo' => null, 'photoList' => '1']],
            'closeAnnotator' => ['close' => true],
        ];
    }

    public function modalParams(): array
    {
        return ['photo', 'photoList'];
    }

    public function components(): array
    {
        $data = $this->data();

        return [
            'image-annotator' => $data['current'] ? ['demo.teacher.image-annotator', [
                'imageUrl' => $data['current']['url'],
                'many' => count($data['photos']) > 1,
            ]] : null,
        ];
    }

    public function data(): array
    {
        $s = $this->submission((int) $this->param('submission')) ?? $this->submission(401);
        $h = $s->homework;
        $student = $s->student;
        $firstName = $student->first_name ?: $student->name;
        $this->title = $h->title;

        // Действия, которые в настоящем экране меняют работу: «Принять», «Вернуть», «Изменить оценку»
        $done = $this->state('done', '');
        $editing = $this->state('editing', false);
        $grade = $this->state('grade', '');
        if ($done === 'graded' && $grade !== '') {
            $s = (clone $s)->forceFill(['status' => Sub::STATUS_GRADED, 'grade' => (int) $grade, 'feedback' => null]);
        } elseif ($done === 'returned' && $s->status === Sub::STATUS_SUBMITTED) {
            $s = (clone $s)->forceFill(['status' => Sub::STATUS_REVISION_REQUESTED, 'feedback' => null, 'updated_at' => now()]);
        }
        if ($editing && $grade === '') {
            $grade = (string) $s->grade;
        }

        $max = $h->effective_max_score;
        if ($this->state('error') === 'grade') {
            view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag(['grade' => "Выберите оценку от 1 до {$max}"])));
        }

        $mode = match (true) {
            $editing => 'form',
            $s->status === Sub::STATUS_REVISION_REQUESTED => 'returned',
            $s->grade !== null => 'graded',
            default => 'form',
        };

        // Учитель видит пометки сразу (Hw::answerFiles с пометками)
        $answer = $this->demoFileViews($s->demoFiles, $s->markedFiles());
        $photos = array_values(array_map(
            fn ($f) => $f + ['label' => preg_match('/^Фото \d+$/u', $f['name']) ? mb_strtolower($f['name']) : $f['name']],
            array_filter($answer, fn ($f) => $f['image']),
        ));
        $photoIndex = $this->state('photo');
        $current = $photoIndex !== null ? ($photos[(int) $photoIndex] ?? null) : null;
        $photoList = $this->state('photoList', false) && count($photos) > 1;

        [$position, $total, $nextUrl] = $this->queue($s);

        return [
            'homework' => $h,
            'student' => $student,
            'firstName' => $firstName,
            'facts' => $this->facts($s, $mode),
            'content' => RichText::html($s->content),
            'feedback' => $mode === 'form' ? null : RichText::html($s->feedback),
            'photos' => $photos,
            'otherFiles' => array_values(array_filter($answer, fn ($f) => ! $f['image'])),
            'mode' => $mode,
            'canReturn' => $s->status === Sub::STATUS_SUBMITTED && ! $editing,
            'max' => $max,
            'scale' => $max <= 10 ? range(1, $max) : null,
            'gradeLabel' => $h->formatGrade($s->grade),
            'newFiles' => [],
            'position' => $position,
            'total' => $total,
            'nextUrl' => $nextUrl,
            'description' => RichText::html($h->description),
            'taskFiles' => $this->demoFileViews($h->demoFiles),
            'marksNote' => $s->marksVisibleToStudent()
                ? $firstName . ' увидит пометки сразу после сохранения'
                : $firstName . ' увидит пометки, когда вы проверите работу',
            'issued' => 'Выдано ' . HumanDate::date($h->created_at)
                . ($h->deadline ? ' · срок до ' . HumanDate::date($h->deadline) . ', ' . $h->deadline->format('H:i') : ''),
            'current' => $current,
            'backUrl' => route('cabinet.teacher.tasks'),
            // Публичные свойства компонента
            'submission' => $s,
            'grade' => $grade === '' ? null : (int) $grade,
            'comment' => $editing ? RichText::toPlain($s->feedback) : '',
            'commentOriginal' => null,
            'editing' => $editing,
            'picked' => [],
            'files' => [],
            'annotating' => $current['path'] ?? null,
            'photoList' => $photoList,
        ];
    }

    /** Место работы в очереди на проверку и следующая работа — как Review::queue. */
    private function queue(Sub $s): array
    {
        $ids = $this->toReview()->pluck('id')->all();
        $index = array_search($s->id, $ids, true);

        $next = $index === false
            ? ($ids[0] ?? null)
            : ($ids[$index + 1] ?? ($ids[0] !== $s->id ? $ids[0] : null));

        return [
            $index === false ? null : $index + 1,
            count($ids),
            $next ? route('cabinet.teacher.review', $next) : null,
        ];
    }

    /** Строка фактов — как Review::facts (там «пересдано» читается из истории работы в базе). */
    private function facts(Sub $s, string $mode): HtmlString
    {
        $h = $s->homework;
        $at = $s->submitted_at;

        $sent = match (true) {
            (bool) $s->resubmitted => 'исправлено ' . HumanDate::day($at),
            $h->deadline && $at->gt($h->deadline) => 'сдано позже срока, ' . HumanDate::day($at),
            (bool) $h->deadline => 'сдано вовремя, ' . HumanDate::day($at),
            default => 'сдано ' . HumanDate::day($at),
        };

        $parts = array_map('e', array_filter([$s->student?->name, $h->room?->name, $sent]));

        $days = (int) $at->copy()->startOfDay()->diffInDays(today(), true);
        if ($mode === 'form' && $s->grade === null && $days >= 1) {
            $parts[] = '<span class="font-semibold text-ink">ждёт ' . e(plural_ru($days, 'день', 'дня', 'дней')) . '</span>';
        }

        return new HtmlString(implode(' · ', $parts));
    }
}
