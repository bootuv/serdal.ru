<?php

namespace App\Livewire\Cabinet\Student;

use App\Models\MaterialFolder;
use App\Models\TeacherMaterial;
use App\Models\User;
use App\Services\StudentMaterialsService;
use App\Support\HumanDate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Материалы ученика. Макет: «Ученик · Материалы» (docs/design/BRAND.md). */
#[Layout('components.layouts.cabinet', ['title' => 'Материалы', 'active' => 'materials'])]
class Materials extends Component
{
    /** Сколько строк показываем в карточке учителя на общем экране. */
    private const CARD_ROWS = 6;

    /** Учитель: 'all' или ID. */
    #[Url(except: 'all')]
    public string $teacher = 'all';

    /** Открытая папка. */
    #[Url]
    public ?int $folder = null;

    #[Url(except: '')]
    public string $search = '';

    public int $limit = 60;

    public function mount(): void
    {
        abort_unless(auth()->user()?->role === User::ROLE_STUDENT, 403);

        // Чужие учитель и папка — как будто их нет
        if ($this->teacher !== 'all' && ! $this->teacherIds()->contains((int) $this->teacher)) {
            abort(404);
        }

        if ($this->folder !== null && ! $this->service()->visibleFolders(auth()->user())->whereKey($this->folder)->exists()) {
            abort(404);
        }
    }

    public function updatedTeacher(): void
    {
        if ($this->teacher !== 'all' && ! $this->teacherIds()->contains((int) $this->teacher)) {
            $this->teacher = 'all';
        }

        $this->folder = null;
        $this->limit = 60;
    }

    public function updatedSearch(): void
    {
        $this->limit = 60;
    }

    public function showMore(): void
    {
        $this->limit += 60;
    }

    public function render()
    {
        $student = auth()->user();
        $teachers = User::whereIn('id', $this->teacherIds())->with('subjects:id,name')->orderBy('name')->get(['id', 'name']);
        $teacherId = $this->teacher !== 'all' ? (int) $this->teacher : null;
        $searching = filled(trim($this->search));

        $data = [
            'teachers' => $teachers,
            'multi' => $teachers->count() > 1,
            'searching' => $searching,
            'total' => $this->service()->materials($student)->count(),
            'openFolder' => null,
            'fresh' => collect(),
            'sections' => collect(),
            'results' => collect(),
            'hasMore' => false,
        ];

        if ($this->folder !== null && ! $searching) {
            return view('livewire.cabinet.student.materials', array_merge($data, $this->folderView($student)));
        }

        if ($searching) {
            $query = $this->service()->search($this->service()->materials($student, $teacherId), $this->search)
                ->orderBy('sort_order')->orderByDesc('created_at');
            $results = $query->limit($this->limit + 1)->get();
            $data['hasMore'] = $results->count() > $this->limit;
            $data['results'] = $results->take($this->limit)->map(fn (TeacherMaterial $m) => $this->fileRow($m, [
                $data['multi'] && ! $teacherId ? $m->teacher?->name : null,
                $m->folder?->name,
            ]));

            return view('livewire.cabinet.student.materials', $data);
        }

        $data['fresh'] = $this->fresh($student, $teacherId, $data['multi']);

        $single = $teacherId !== null || ! $data['multi'];
        $data['sections'] = $teachers
            ->when($teacherId, fn ($c) => $c->where('id', $teacherId))
            ->map(fn (User $t) => $this->section($student, $t, $single))
            ->values();
        $data['hasMore'] = $single && (bool) ($data['sections']->first()['more'] ?? false);

        return view('livewire.cabinet.student.materials', $data);
    }

    /** Карточка учителя: папки и файлы верхнего уровня. */
    private function section(User $student, User $teacher, bool $single): array
    {
        $folders = $this->folderRows($student, $teacher->id, null);
        $filesLimit = $single ? $this->limit : max(0, self::CARD_ROWS - $folders->count());
        $files = $this->service()->materials($student, $teacher->id)
            ->whereNull('folder_id')
            ->orderBy('sort_order')->orderByDesc('created_at')
            ->limit($filesLimit + 1)
            ->get();

        $more = $files->count() > $filesLimit || (! $single && $folders->count() > self::CARD_ROWS);

        return [
            'id' => $teacher->id,
            'name' => $teacher->name,
            'subjects' => $teacher->subjects->pluck('name')->join(', '),
            'rows' => $folders->take($single ? PHP_INT_MAX : self::CARD_ROWS)
                ->concat($files->take($filesLimit)->map(fn (TeacherMaterial $m) => $this->fileRow($m)))
                ->values(),
            'more' => $more,
        ];
    }

    /** Открытая папка: вложенные папки и файлы. */
    private function folderView(User $student): array
    {
        $folder = $this->service()->visibleFolders($student)->find($this->folder);
        $files = $this->service()->materials($student, $folder->teacher_id)
            ->where('folder_id', $folder->id)
            ->orderBy('sort_order')->orderByDesc('created_at')
            ->limit($this->limit + 1)
            ->get();

        $rows = $this->folderRows($student, $folder->teacher_id, $folder->id)
            ->concat($files->take($this->limit)->map(fn (TeacherMaterial $m) => $this->fileRow($m)));

        $parent = $folder->parent_id ? MaterialFolder::find($folder->parent_id) : null;

        return [
            'openFolder' => [
                'name' => $folder->name,
                'teacher' => $folder->teacher?->name,
                'back' => $parent
                    ? route('cabinet.student.materials', ['folder' => $parent->id])
                    : route('cabinet.student.materials', array_filter(['teacher' => $this->teacherIds()->count() > 1 ? $folder->teacher_id : null])),
                'backLabel' => $parent?->name ?? 'Материалы',
                'rows' => $rows->values(),
            ],
            'hasMore' => $files->count() > $this->limit,
        ];
    }

    /** Строки папок одного уровня: сколько файлов и когда обновлялась. */
    private function folderRows(User $student, int $teacherId, ?int $parentId): Collection
    {
        $visible = fn ($q) => $q->visibleToStudent($student);

        return $this->service()->visibleFolders($student, $teacherId)
            ->where('parent_id', $parentId)
            ->withCount(['materials' => $visible])
            ->withMax(['materials' => $visible], 'created_at')
            ->orderBy('sort_order')->orderBy('name')
            ->get()
            ->map(function (MaterialFolder $f) {
                $updated = $f->materials_max_created_at ? Carbon::parse($f->materials_max_created_at) : null;

                return [
                    'folder' => true,
                    'title' => $f->name,
                    'meta' => implode(' · ', array_filter([
                        $f->materials_count > 0 ? plural_ru($f->materials_count, 'файл', 'файла', 'файлов') : 'Вложенные папки',
                        $updated ? 'обновлено ' . HumanDate::date($updated) : null,
                    ])),
                    'href' => route('cabinet.student.materials', ['folder' => $f->id]),
                ];
            });
    }

    private function fileRow(TeacherMaterial $m, array $prefix = []): array
    {
        return [
            'folder' => false,
            'title' => $m->title,
            'file' => $m->original_name ?: $m->file_path,
            'meta' => implode(' · ', array_filter([...$prefix, $this->dateLabel($m->created_at), $this->size($m)])),
            'href' => $m->file_url,
        ];
    }

    /** Новое за неделю. Если файл открыт группе с ближайшим занятием — «к занятию сегодня в 16:00». */
    private function fresh(User $student, ?int $teacherId, bool $multi): Collection
    {
        $roomIds = $student->assignedRooms()->pluck('rooms.id');

        return $this->service()->materials($student, $teacherId)
            ->with(['rooms' => fn ($q) => $q->whereIn('rooms.id', $roomIds)])
            ->where('created_at', '>=', now()->subDays(7))
            ->latest()
            ->limit(5)
            ->get()
            ->map(function (TeacherMaterial $m) use ($multi) {
                $lesson = $m->rooms
                    ->filter(fn ($r) => $r->next_start && $r->next_start->isFuture())
                    ->sortBy('next_start')
                    ->first();

                return $this->fileRow($m) + [
                    'lead' => implode(' · ', array_filter([$multi ? $m->teacher?->name : null, $lesson ? null : 'добавлено ' . HumanDate::day($m->created_at)])),
                    'em' => $lesson ? 'к занятию ' . HumanDate::at($lesson->next_start) : null,
                ];
            });
    }

    private function dateLabel(?Carbon $date): ?string
    {
        if (! $date) {
            return null;
        }

        return abs($date->copy()->startOfDay()->diffInDays(today())) <= 1 ? HumanDate::day($date) : HumanDate::date($date);
    }

    private function size(TeacherMaterial $m): ?string
    {
        return $m->file_size > 0 ? str_replace('.', ',', $m->formatted_size) : null;
    }

    private function teacherIds(): Collection
    {
        return $this->service()->availableTeacherIds(auth()->user());
    }

    private function service(): StudentMaterialsService
    {
        return app(StudentMaterialsService::class);
    }
}
