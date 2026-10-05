<?php

namespace App\Demo\Teacher;

use App\Demo\Screen;
use App\Demo\World;
use App\Models\MaterialFolder;
use App\Models\TeacherMaterial;
use App\Support\HumanDate;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * «Материалы» учителя — App\Livewire\Cabinet\Teacher\Materials.
 *
 * Состояние в адресе: ?folder=302 (открытая папка), ?search=…, ?file=1006 (окно файла), ?folderModal=new|edit,
 * ?upload=1 (окно загрузки), ?confirm=file|folder, ?selecting=1&picked=1006,1007&bulk=move|delete,
 * ?fileVisibility=… / ?uploadVisibility=… / ?uploadFolder=… (переключатели в окнах).
 * Загрузка, замена, перетаскивание и сохранение — тостом: в демо ничего не меняется.
 */
class Materials extends Screen
{
    public const PATH = 'materials';

    public const EXAMPLES = [
        'materials',
        'materials?folder=302',
        'materials?folder=301&folderModal=edit',
        'materials?folderModal=new',
        'materials?folder=302&file=1006',
        'materials?folder=302&file=1006&fileVisibility=rooms',
        'materials?file=1002&confirm=file',
        'materials?upload=1&uploadVisibility=rooms',
        'materials?search=вариант',
        'materials?search=интеграл',
        'materials?folder=302&selecting=1&picked=1006,1007&bulk=move',
        'materials?folder=302&selecting=1&picked=1006&bulk=delete',
        'materials?folder=301&folderModal=edit&confirm=folder',
    ];

    public string $view = 'livewire.cabinet.teacher.materials';

    public string $title = 'Материалы';

    public ?string $active = 'materials';

    private const UPLOAD_TOAST = 'Это демо — файлы не загружаются. Зарегистрируйтесь, чтобы загрузить свои материалы';

    /** Папки: id => [родитель, название]. */
    private const FOLDERS = [
        301 => [null, 'ОГЭ по математике'],
        302 => [301, 'Варианты'],
        303 => [301, 'Теория'],
        304 => [null, 'ЕГЭ, профиль'],
        305 => [304, 'Пробники 2027'],
        306 => [null, 'Физика'],
        307 => [null, 'Презентации к урокам'],
    ];

    /**
     * Файлы: id => [папка, название, имя файла, размер в байтах, дней назад, доступ (all | private | [занятия]), описание].
     * Занятия — комнаты из World::ROOMS.
     */
    private const FILES = [
        1001 => [null, 'Правила работы на занятиях', 'pravila-zanyatiy.pdf', 184_320, 41, 'all', null],
        1002 => [null, 'Таблица квадратов и степеней', 'tablica-kvadratov.png', 431_000, 0, 'all', 'Распечатать и держать перед глазами'],
        1003 => [null, 'План подготовки на год', 'plan-podgotovki-2026-2027.docx', 65_536, 1, 'private', null],
        1004 => [301, 'Демоверсия ОГЭ-2027 по математике', 'oge-2027-demo.pdf', 1_887_437, 33, [201], null],
        1005 => [301, 'Справочные материалы ОГЭ', 'oge-spravochnik.pdf', 512_000, 33, [201], 'Формулы, которые выдают на экзамене'],
        1006 => [302, 'Вариант 1 (с ответами)', 'oge-variant-01.pdf', 742_000, 26, [201], null],
        1007 => [302, 'Вариант 2 (с ответами)', 'oge-variant-02.pdf', 768_000, 19, [201], null],
        1008 => [302, 'Вариант 3', 'oge-variant-03.pdf', 735_000, 5, 'private', 'Ответы откроем после разбора'],
        1009 => [303, 'Квадратные уравнения — конспект', 'kvadratnye-uravneniya.pdf', 356_000, 24, [201, 205], null],
        1010 => [303, 'Функции и графики', 'funkcii-i-grafiki.pptx', 4_404_019, 17, [201], null],
        1011 => [303, 'Признаки подобия треугольников', 'podobie-treugolnikov.docx', 98_304, 12, [204], null],
        1012 => [304, 'Кодификатор ЕГЭ-2027', 'ege-2027-kodifikator.pdf', 1_258_291, 35, 'all', null],
        1013 => [304, 'Производная: теория и задачи', 'proizvodnaya.pdf', 925_000, 14, [202, 206], 'Задачи 7 и 12 из ЕГЭ'],
        1014 => [304, 'Тригонометрические уравнения', 'trigonometriya-zadanie-13.docx', 143_360, 9, [206], null],
        1015 => [305, 'Пробник №1, сентябрь', 'probnik-1-sentyabr.pdf', 1_048_576, 15, [202, 206], null],
        1016 => [305, 'Разбор пробника №1', 'razbor-probnika-1.pptx', 6_081_741, 8, [206], null],
        1017 => [306, 'Законы Ньютона — конспект', 'zakony-nyutona.pdf', 402_000, 20, [203], null],
        1018 => [306, 'Кинематика: формулы', 'kinematika-formuly.jpg', 1_572_864, 6, [203], null],
        1019 => [306, 'Лабораторная: маятник', 'laboratornaya-mayatnik.docx', 51_200, 2, 'private', null],
        1020 => [307, 'Теорема Пифагора', 'teorema-pifagora.pptx', 3_250_585, 22, [204, 205], null],
        1021 => [307, 'Проценты и пропорции', 'procenty-i-proporcii.pptx', 2_726_297, 10, 'all', null],
    ];

    public function modalParams(): array
    {
        return ['folderModal', 'file', 'confirm', 'upload', 'bulk', 'fileVisibility', 'uploadVisibility', 'uploadFolder'];
    }

    public function props(): array
    {
        return ['uploadOpen' => $this->state('upload', false)];
    }

    public function actions(): array
    {
        $folder = $this->currentId();
        $picked = count($this->stateInts('picked'));

        return [
            // Навигация и поиск
            'openFolder' => ['set' => ['folder' => '{0}', 'search' => null]],
            'clearSearch' => ['set' => ['search' => null]],

            // Папки
            'newFolder' => ['set' => ['folderModal' => 'new']],
            'editFolder' => ['set' => ['folderModal' => 'edit']],
            'saveFolder' => ['close' => true, 'toast' => $this->state('folderModal') === 'edit' ? 'Папка сохранена' : 'Папка создана'],
            'deleteFolder' => [
                'close' => true,
                'set' => ['folder' => $folder ? self::FOLDERS[$folder][0] : null],
                'toast' => 'Папка удалена, файлы остались',
            ],
            'moveFolderTo' => ['toast' => 'Папка перемещена'],
            'reorderFolder' => ['toast' => 'Порядок папок в демо не сохраняется'],

            // Файлы
            'editFile' => ['set' => ['file' => '{0}']],
            'saveFile' => ['close' => true, 'toast' => 'Файл сохранён'],
            'deleteFile' => ['close' => true, 'toast' => 'Файл удалён'],
            'moveMaterial' => ['toast' => 'Файл перемещён'],
            'reorderMaterial' => ['toast' => 'Порядок файлов в демо не сохраняется'],

            // Несколько файлов
            'startSelect' => ['set' => ['selecting' => '1', 'picked' => null, 'bulk' => null]],
            'cancelSelect' => ['set' => ['selecting' => null, 'picked' => null, 'bulk' => null]],
            'toggle' => ['toggle' => 'picked'],
            'askBulk' => ['set' => ['bulk' => '{0}']],
            'moveSelected' => [
                'set' => ['selecting' => null, 'picked' => null, 'bulk' => null],
                'toast' => 'Перемещено: ' . plural_ru($picked, 'файл', 'файла', 'файлов'),
            ],
            'deleteSelected' => [
                'set' => ['selecting' => null, 'picked' => null, 'bulk' => null],
                'toast' => 'Удалено: ' . plural_ru($picked, 'файл', 'файла', 'файлов'),
            ],

            // Загрузка: файлы с компьютера в демо не принимаем
            'openUpload' => ['set' => ['upload' => '1']],
            'uploadMultiple' => ['toast' => self::UPLOAD_TOAST],
            'saveUpload' => ['close' => true, 'toast' => self::UPLOAD_TOAST],
        ];
    }

    public function data(): array
    {
        $folder = $this->currentId();
        $search = trim($this->state('search', ''));
        $searching = $search !== '';
        $fileId = $this->state('file', 0);
        $fileId = isset(self::FILES[$fileId]) ? $fileId : null;
        $file = $fileId ? $this->material($fileId) : null;
        $uploadOpen = $this->state('upload', false);
        $folderModal = in_array($this->state('folderModal'), ['new', 'edit'], true) ? $this->state('folderModal') : null;
        if ($folderModal === 'edit' && ! $folder) {
            $folderModal = null;
        }
        $selecting = $this->state('selecting', false);
        $picked = $selecting ? array_values(array_filter($this->stateInts('picked'), fn (int $id) => isset(self::FILES[$id]))) : [];
        $bulk = in_array($this->state('bulk'), ['move', 'delete'], true) ? $this->state('bulk') : null;
        $confirm = in_array($this->state('confirm'), ['file', 'folder'], true) ? $this->state('confirm') : null;
        $fileVisibility = $this->visibility('fileVisibility', $file ? $file->visibility : TeacherMaterial::VISIBILITY_ALL);
        $uploadFolder = (string) $this->state('uploadFolder', (string) ($folder ?? ''));
        $paths = $this->paths();

        $files = collect(self::FILES)
            ->filter(fn (array $f, int $id) => $searching
                ? collect([$f[1], $f[2], $f[6]])->filter()->contains(fn ($s) => mb_stripos($s, $search) !== false)
                : $f[0] === $folder)
            ->map(fn (array $f, int $id) => $this->fileRow($id, $searching ? ($paths[$f[0]] ?? null) : null))
            ->sortBy(fn (array $r) => self::FILES[$r['id']][4])
            ->values();

        $current = $folder ? $this->folderModel($folder) : null;

        return [
            // Публичные свойства компонента
            'folder' => $folder,
            'search' => $search,
            'limit' => 60,
            'folderModal' => $folderModal,
            'folderName' => $folderModal === 'edit' ? $current->name : '',
            'folderParent' => $folderModal === 'edit' ? (string) (self::FOLDERS[$folder][0] ?? '') : '',
            'fileId' => $fileId,
            'fileTitle' => $file?->title ?? '',
            'fileDescription' => (string) $file?->description,
            'replacement' => null,
            'replacementName' => null,
            'fileFolder' => (string) ($file?->folder_id ?? ''),
            'fileVisibility' => $fileVisibility,
            'fileRooms' => $fileId && is_array(self::FILES[$fileId][5]) ? array_map('strval', self::FILES[$fileId][5]) : [],
            'confirm' => $confirm,
            'uploadOpen' => $uploadOpen,
            'uploads' => [],
            'uploadNames' => [],
            'uploadFolder' => $uploadFolder,
            'uploadVisibility' => $this->visibility('uploadVisibility', TeacherMaterial::VISIBILITY_ALL),
            'uploadRooms' => [],
            'selecting' => $selecting,
            'picked' => $picked,
            'bulk' => $bulk,
            'bulkFolder' => (string) ($folder ?? ''),

            // render()
            'sub' => plural_ru(count(self::FILES), 'файл', 'файла', 'файлов') . ' в ' . plural_ru(count(self::FOLDERS), 'папке', 'папках', 'папках'),
            'searching' => $searching,
            'crumbs' => $this->crumbs($folder, $searching),
            'folders' => $searching ? collect() : $this->folderRows($folder),
            'files' => $files,
            'hasMore' => false,
            'sortable' => ! $searching && ! $selecting,
            'isEmpty' => false,
            'access' => $this->accessSummary(),
            'current' => $current,
            'folderOptions' => ['' => 'Все материалы'] + $paths->all(),
            'parentOptions' => $folderModal === 'edit' ? $this->parentOptions($folder, $paths) : [],
            'roomOptions' => ($fileId || $uploadOpen) ? $this->roomOptions() : collect(),
            'file' => $file,
            'visibilityItems' => [
                TeacherMaterial::VISIBILITY_PRIVATE => 'Только вам',
                TeacherMaterial::VISIBILITY_ROOMS => 'Выбранным',
                TeacherMaterial::VISIBILITY_ALL => 'Всем ученикам',
            ],
        ];
    }

    /** Открытая папка; ?folder=null (кнопка «Все материалы») и чужие номера — корень. */
    private function currentId(): ?int
    {
        $id = (int) $this->state('folder', 0);

        return isset(self::FOLDERS[$id]) ? $id : null;
    }

    private function visibility(string $key, string $default): string
    {
        $value = $this->state($key, $default);

        return in_array($value, [TeacherMaterial::VISIBILITY_PRIVATE, TeacherMaterial::VISIBILITY_ROOMS, TeacherMaterial::VISIBILITY_ALL], true) ? $value : $default;
    }

    private static function visibilityOf(array $f): string
    {
        return match (true) {
            is_array($f[5]) => TeacherMaterial::VISIBILITY_ROOMS,
            $f[5] === 'all' => TeacherMaterial::VISIBILITY_ALL,
            default => TeacherMaterial::VISIBILITY_PRIVATE,
        };
    }

    /** Файл моделью в памяти (окно файла читает её поля). Ссылка «Открыть файл» — мимо демо: покажет тост. */
    private function material(int $id): TeacherMaterial
    {
        $f = self::FILES[$id];
        $material = new class extends TeacherMaterial {
            public function getFileUrlAttribute(): string
            {
                return url('/demo-files/' . $this->original_name);
            }

            public function getPreviewUrlAttribute(): ?string
            {
                return null;
            }
        };
        $material->forceFill([
            'id' => $id,
            'teacher_id' => World::TEACHER_ID,
            'folder_id' => $f[0],
            'title' => $f[1],
            'description' => $f[6],
            'original_name' => $f[2],
            'file_path' => 'materials/' . World::TEACHER_ID . '/' . $f[2],
            'file_size' => $f[3],
            'visibility' => self::visibilityOf($f),
            'created_at' => Carbon::now()->subDays($f[4])->setTime(9 + $id % 4, ($id * 7) % 60),
        ]);
        $material->exists = true;

        return $material;
    }

    private function fileRow(int $id, ?string $path): array
    {
        $f = self::FILES[$id];
        $m = $this->material($id);
        $rooms = is_array($f[5]) ? $f[5] : [];
        $created = $m->created_at;

        return [
            'id' => $id,
            'title' => $f[1],
            'file' => $f[2],
            'thumb' => null,
            'access' => implode(' · ', array_filter([$path, $this->accessLabel($f)])),
            'lock' => $f[5] === 'private',
            'size' => str_replace('.', ',', $m->formatted_size),
            'date' => abs($created->copy()->startOfDay()->diffInDays(Carbon::today())) <= 1 ? HumanDate::day($created) : HumanDate::date($created),
        ];
    }

    /** Как TeacherMaterialsService::accessLabel. */
    private function accessLabel(array $f): string
    {
        return match (self::visibilityOf($f)) {
            TeacherMaterial::VISIBILITY_ALL => 'Видно всем ученикам',
            TeacherMaterial::VISIBILITY_ROOMS => 'Видно: ' . collect($f[5])->map(fn (int $room) => self::roomLabel($room))->unique()->join(', '),
            default => 'Только вам',
        };
    }

    /** Как TeacherMaterialsService::roomLabel: индивидуальное — имя ученика, групповое — «группа «…»». */
    private static function roomLabel(int $roomId): string
    {
        $room = World::ROOMS[$roomId];

        return $room[1] === 'group' ? 'группа «' . $room[0] . '»' : World::student($room[2][0])->name;
    }

    /** Как TeacherMaterialsService::accessSummary. */
    private function accessSummary(): Collection
    {
        $rows = [];
        $add = function (string $key, array $row) use (&$rows) {
            $rows[$key] ??= $row + ['count' => 0];
            $rows[$key]['count']++;
        };

        foreach (self::FILES as $f) {
            if ($f[5] === 'all') {
                $add('all', ['kind' => 'all', 'name' => 'Всем ученикам', 'user' => null, 'rank' => 2]);
                continue;
            }
            if ($f[5] === 'private') {
                $add('private', ['kind' => 'private', 'name' => 'Только вам', 'user' => null, 'rank' => 3]);
                continue;
            }

            $keys = [];
            foreach ($f[5] as $roomId) {
                $room = World::ROOMS[$roomId];
                if ($room[1] !== 'group') {
                    $student = World::student($room[2][0]);
                    $keys['u' . $student->id] = ['kind' => 'person', 'name' => $student->name, 'user' => $student, 'rank' => 0];
                } else {
                    $keys['r' . $roomId] = ['kind' => 'group', 'name' => 'Группа «' . $room[0] . '»', 'user' => null, 'rank' => 1];
                }
            }
            foreach ($keys as $key => $row) {
                $add($key, $row);
            }
        }

        return collect($rows)->sortBy([['rank', 'asc'], ['count', 'desc'], ['name', 'asc']])->values();
    }

    private function folderModel(int $id): MaterialFolder
    {
        $folder = new MaterialFolder;
        $folder->forceFill(['id' => $id, 'teacher_id' => World::TEACHER_ID, 'parent_id' => self::FOLDERS[$id][0], 'name' => self::FOLDERS[$id][1]]);
        $folder->exists = true;

        return $folder;
    }

    /** «Все материалы › ОГЭ по математике › Варианты»; при поиске — «Все материалы › Поиск». */
    private function crumbs(?int $folder, bool $searching): array
    {
        $crumbs = [['id' => null, 'name' => 'Все материалы']];
        if ($searching) {
            return [...$crumbs, ['id' => null, 'name' => 'Поиск', 'search' => true]];
        }

        $chain = [];
        for ($id = $folder; $id !== null; $id = self::FOLDERS[$id][0]) {
            array_unshift($chain, ['id' => $id, 'name' => self::FOLDERS[$id][1]]);
        }

        return [...$crumbs, ...$chain];
    }

    /** Папки уровня: «2 папки · 3 файла» или «Пусто». */
    private function folderRows(?int $parent): Collection
    {
        return collect(self::FOLDERS)
            ->filter(fn (array $f) => $f[0] === $parent)
            ->map(function (array $f, int $id) {
                $sub = collect(self::FOLDERS)->where(0, $id)->count();
                $n = collect(self::FILES)->where(0, $id)->count();

                return [
                    'id' => $id,
                    'name' => $f[1],
                    'meta' => implode(' · ', array_filter([
                        $sub ? plural_ru($sub, 'папка', 'папки', 'папок') : null,
                        $n ? plural_ru($n, 'файл', 'файла', 'файлов') : null,
                    ])) ?: 'Пусто',
                ];
            })
            ->values();
    }

    /** Полные пути папок: [id => «ОГЭ по математике / Варианты»]. */
    private function paths(): Collection
    {
        return collect(self::FOLDERS)->map(function (array $f, int $id) {
            $names = [];
            for ($cur = $id; $cur !== null; $cur = self::FOLDERS[$cur][0]) {
                array_unshift($names, self::FOLDERS[$cur][1]);
            }

            return implode(' / ', $names);
        })->sort();
    }

    /** Куда можно переложить папку: всё, кроме неё самой и вложенных. */
    private function parentOptions(int $folder, Collection $paths): array
    {
        $exclude = [$folder];
        foreach (self::FOLDERS as $id => $f) {
            for ($cur = $f[0]; $cur !== null; $cur = self::FOLDERS[$cur][0]) {
                if ($cur === $folder) {
                    $exclude[] = $id;
                }
            }
        }

        return ['' => 'Все материалы'] + $paths->except($exclude)->all();
    }

    /** Занятия для выбора доступа: «Мальсагов Рустам · Алгебра · ОГЭ», «Группа «ЕГЭ по профильной математике»». */
    private function roomOptions(): Collection
    {
        return collect(World::ROOMS)->map(fn (array $room, int $id) => [
            'id' => (string) $id,
            'label' => $room[1] === 'group'
                ? 'Группа «' . $room[0] . '»'
                : World::student($room[2][0])->name . ' · ' . $room[0],
        ])->sortBy('label')->values();
    }
}
