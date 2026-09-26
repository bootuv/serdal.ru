<?php

namespace App\Livewire\Cabinet\Admin;

use App\Livewire\Cabinet\Admin\Concerns\AdminScreen;
use App\Services\DictionaryService;
use App\Services\SiteSettingsService;
use App\Services\YooKassaService;
use App\Support\SeoSettings;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Настройки: вкладки «Видеосвязь / Записи / Оплата / Реквизиты и оферта / Сайт и поисковики / Блок для школ / Справочники».
 * Каждая вкладка сохраняется отдельно. Макеты: AdminSettings, AdminDictionaries.
 * Ключи — как в Filament ManageBigBlueButton (App\Services\SiteSettingsService); справочники — App\Services\DictionaryService.
 */
#[Layout('components.layouts.cabinet', ['title' => 'Настройки', 'active' => 'settings'])]
class Settings extends Component
{
    use AdminScreen;
    use WithFileUploads;

    public const TABS = [
        'video' => 'Видеосвязь',
        'records' => 'Записи',
        'payments' => 'Оплата',
        'legal' => 'Реквизиты и оферта',
        'seo' => 'Сайт и поисковики',
        'b2b' => 'Блок для школ',
        'dictionaries' => 'Справочники',
    ];

    /** Переключатели по вкладкам: какие ключи можно переключать методом flip(). */
    private const SWITCHES = [
        'video' => ['record', 'auto_start_recording', 'allow_start_stop_recording', 'mute_on_start', 'webcams_only_for_moderator'],
        'records' => ['recording_auto_upload', 'recording_delete_after_upload'],
        'payments' => ['yookassa_recurring_enabled'],
        'seo' => ['seo_indexing_enabled', 'seo_ai_crawlers_enabled'],
        'b2b' => ['b2b_enabled'],
    ];

    /** Картинки для поисковиков: свойство загрузки => ключ настройки. */
    private const SEO_FILES = ['ogImage' => 'seo_og_image', 'logo' => 'seo_logo', 'touchIcon' => 'seo_apple_touch_icon'];

    #[Url(except: 'video')]
    public string $tab = 'video';

    public array $video = [];
    public array $records = [];
    public array $payments = [];
    public array $legal = [];
    public array $seo = [];
    public array $b2b = [];

    public $ogImage = null;
    public $logo = null;
    public $touchIcon = null;

    public string $featDraft = '';

    /** Вкладки с несохранёнными изменениями и только что сохранённые. */
    public array $dirty = [];
    public array $saved = [];

    public bool $modeModal = false;

    // Справочники
    #[Url(except: 'subjects')]
    public string $dict = 'subjects';
    public string $dictFilter = 'all';
    public string $dictQ = '';
    public bool $adding = false;
    public string $addName = '';
    public ?int $editId = null;
    public string $editName = '';
    public ?int $mergeSource = null;
    public string $mergeTarget = '';
    public string $mergeQ = '';

    public function mount(): void
    {
        $this->authorizeAdmin();

        if (! array_key_exists($this->tab, self::TABS)) {
            $this->tab = 'video';
        }
        if (! in_array($this->dict, DictionaryService::KINDS, true)) {
            $this->dict = 'subjects';
        }

        $this->load();
    }

    private function settings(): SiteSettingsService
    {
        return app(SiteSettingsService::class);
    }

    private function dictionaries(): DictionaryService
    {
        return app(DictionaryService::class);
    }

    private function load(?string $only = null): void
    {
        foreach (['video', 'records', 'payments', 'legal', 'seo', 'b2b'] as $group) {
            if ($only === null || $only === $group) {
                $this->{$group} = $this->settings()->{$group}();
            }
        }
    }

    public function updated(string $property): void
    {
        $group = explode('.', $property)[0];
        if (isset(self::SEO_FILES[$group])) {
            $this->validateOnly($group);
            $group = 'seo';
        }
        if (in_array($group, ['video', 'records', 'payments', 'legal', 'seo', 'b2b'], true)) {
            $this->markDirty($group);
        }
    }

    public function updatedTab(): void
    {
        if (! array_key_exists($this->tab, self::TABS)) {
            $this->tab = 'video';
        }
        $this->resetValidation();
    }

    private function markDirty(string $group): void
    {
        $this->dirty[$group] = true;
        $this->saved[$group] = false;
    }

    /** Переключатель на вкладке (сохраняется кнопкой «Сохранить»). */
    public function flip(string $group, string $key): void
    {
        if (! in_array($key, self::SWITCHES[$group] ?? [], true)) {
            return;
        }
        $this->{$group}[$key] = ! ($this->{$group}[$key] ?? false);
        $this->markDirty($group);
    }

    /* ---------- Сохранение вкладок ---------- */

    protected function rules(): array
    {
        return match ($this->tab) {
            'video' => [
                'video.bbb_url' => ['nullable', 'url', 'max:255'],
                'video.bbb_secret' => ['nullable', 'string', 'max:255'],
                'video.max_participants' => ['required', 'integer', 'min:0'],
                'video.duration' => ['required', 'integer', 'min:0'],
            ],
            'payments' => [
                'payments.yookassa_shop_id' => ['nullable', 'string', 'max:255'],
                'payments.yookassa_secret_key' => ['nullable', 'string', 'max:255'],
                'payments.yookassa_test_shop_id' => ['nullable', 'string', 'max:255'],
                'payments.yookassa_test_secret_key' => ['nullable', 'string', 'max:255'],
                'payments.extra_lesson_price' => ['required', 'integer', 'min:1'],
                'payments.extra_lessons_max' => ['required', 'integer', 'min:1', 'max:100'],
            ],
            'legal' => [
                'legal.legal_name' => ['nullable', 'string', 'max:255'],
                'legal.legal_inn' => ['nullable', 'string', 'max:12'],
                'legal.legal_ogrn' => ['nullable', 'string', 'max:15'],
                'legal.legal_address' => ['nullable', 'string', 'max:255'],
                'legal.legal_email' => ['nullable', 'email', 'max:255'],
                'legal.legal_phone' => ['nullable', 'string', 'max:32'],
                'legal.offer_edition_date' => ['nullable', 'date'],
                'legal.offer_payment_provider' => ['required', 'string', 'max:255'],
                'legal.offer_payment_methods' => ['required', 'string', 'max:255'],
                'legal.offer_refund_days' => ['required', 'integer', 'min:0'],
                'legal.offer_refund_processing_days' => ['required', 'integer', 'min:0'],
            ],
            'seo' => [
                'seo.seo_site_name' => ['required', 'string', 'max:100'],
                'seo.seo_default_title' => ['required', 'string', 'max:120'],
                'seo.seo_default_description' => ['required', 'string', 'max:300'],
                'seo.seo_home_title' => ['required', 'string', 'max:120'],
                'seo.seo_home_description' => ['required', 'string', 'max:300'],
                'seo.seo_yandex_verification' => ['nullable', 'string', 'max:100'],
                'seo.seo_google_verification' => ['nullable', 'string', 'max:100'],
                'seo.seo_social_links' => ['nullable', 'string'],
                'seo.seo_llms_description' => ['nullable', 'string', 'max:1000'],
                'seo.seo_head_extra' => ['nullable', 'string'],
                'ogImage' => ['nullable', 'image', 'max:2048'],
                'logo' => ['nullable', 'image', 'max:2048'],
                'touchIcon' => ['nullable', 'image', 'max:2048'],
            ],
            'b2b' => [
                'b2b.b2b_title' => ['required', 'string', 'max:255'],
                'b2b.b2b_description' => ['nullable', 'string'],
                'b2b.b2b_price_label' => ['required', 'string', 'max:255'],
                'b2b.b2b_price_note' => ['nullable', 'string', 'max:255'],
                'b2b.b2b_email' => ['required', 'email', 'max:255'],
                'b2b.b2b_features' => ['array'],
            ],
            default => [
                'ogImage' => ['nullable', 'image', 'max:2048'],
                'logo' => ['nullable', 'image', 'max:2048'],
                'touchIcon' => ['nullable', 'image', 'max:2048'],
            ],
        };
    }

    protected function messages(): array
    {
        return [
            'video.bbb_url.url' => 'Введите адрес целиком, вместе с https://',
            '*.*.required' => 'Заполните поле',
            '*.*.integer' => 'Введите целое число',
            '*.*.min' => 'Слишком маленькое число',
            '*.*.max' => 'Слишком длинное значение',
            '*.*.email' => 'Проверьте почту',
            'legal.offer_edition_date.date' => 'Проверьте дату',
            'payments.extra_lessons_max.max' => 'Не больше 100',
            'ogImage.image' => 'Нужна картинка PNG или JPG',
            'logo.image' => 'Нужна картинка PNG или JPG',
            'touchIcon.image' => 'Нужна картинка PNG или JPG',
            'ogImage.max' => 'Картинка больше 2 МБ',
            'logo.max' => 'Картинка больше 2 МБ',
            'touchIcon.max' => 'Картинка больше 2 МБ',
        ];
    }

    public function save(): void
    {
        $tab = $this->tab;
        if (! in_array($tab, ['video', 'records', 'payments', 'legal', 'seo', 'b2b'], true)) {
            return;
        }

        $this->validate();

        match ($tab) {
            'video' => $this->settings()->saveVideo($this->video),
            'records' => $this->settings()->saveRecords($this->records),
            'payments' => $this->settings()->savePayments($this->payments),
            'legal' => $this->settings()->saveLegal($this->legal),
            'seo' => $this->settings()->saveSeo($this->seo, [
                'seo_og_image' => $this->ogImage,
                'seo_logo' => $this->logo,
                'seo_apple_touch_icon' => $this->touchIcon,
            ]),
            'b2b' => $this->settings()->saveB2b($this->b2b),
        };

        if ($tab === 'seo') {
            $this->reset('ogImage', 'logo', 'touchIcon');
        }
        $this->load($tab);
        $this->dirty[$tab] = false;
        $this->saved[$tab] = true;
    }

    /* ---------- ЮKassa: режим переключается сразу, с подтверждением ---------- */

    public function askMode(): void
    {
        $this->modeModal = true;
    }

    public function closeMode(): void
    {
        $this->modeModal = false;
    }

    public function toggleMode(): void
    {
        $test = $this->settings()->toggleYooKassaTestMode();
        $this->modeModal = false;

        if ($test && ! YooKassaService::isConfigured()) {
            $this->dispatch('toast', message: 'Тестовый режим включён, но ключи тестового магазина не заполнены — платежи недоступны', tone: 'danger');

            return;
        }
        $this->dispatch('toast', message: $test ? 'ЮKassa работает в тестовом режиме' : 'ЮKassa работает в боевом режиме');
    }

    /* ---------- Блок для школ: пункты «Что входит» ---------- */

    public function addFeature(): void
    {
        $text = trim($this->featDraft);
        if ($text === '') {
            return;
        }
        $this->b2b['b2b_features'][] = $text;
        $this->featDraft = '';
        $this->markDirty('b2b');
    }

    public function removeFeature(int $index): void
    {
        $features = $this->b2b['b2b_features'] ?? [];
        unset($features[$index]);
        $this->b2b['b2b_features'] = array_values($features);
        $this->markDirty('b2b');
    }

    /* ---------- Справочники ---------- */

    private function kindWords(): array
    {
        return $this->dict === 'directs'
            ? ['one' => 'направление', 'ph' => 'Название направления', 'search' => 'Найти направление', 'exists' => 'Такое направление уже есть', 'removed' => 'Направление удалено']
            : ['one' => 'предмет', 'ph' => 'Название предмета', 'search' => 'Найти предмет', 'exists' => 'Такой предмет уже есть', 'removed' => 'Предмет удалён'];
    }

    public function updatedDict(): void
    {
        if (! in_array($this->dict, DictionaryService::KINDS, true)) {
            $this->dict = 'subjects';
        }
        $this->reset('dictFilter', 'dictQ', 'adding', 'addName', 'editId', 'editName', 'mergeSource', 'mergeTarget', 'mergeQ');
        $this->resetValidation();
    }

    public function resetDictView(): void
    {
        $this->reset('dictFilter', 'dictQ');
    }

    public function startAdd(): void
    {
        $this->resetValidation();
        $this->reset('dictFilter', 'dictQ', 'editId', 'editName', 'addName');
        $this->adding = true;
    }

    public function cancelAdd(): void
    {
        $this->reset('adding', 'addName');
        $this->resetValidation('addName');
    }

    public function addItem(): void
    {
        $this->resetErrorBag('addName');
        $name = DictionaryService::clean($this->addName);
        if ($name === '') {
            $this->addError('addName', 'Введите название');

            return;
        }
        if (mb_strlen($name) > 255) {
            $this->addError('addName', 'Слишком длинное название');

            return;
        }
        if ($this->dictionaries()->exists($this->dict, $name)) {
            $this->addError('addName', $this->kindWords()['exists']);

            return;
        }

        $this->dictionaries()->add($this->dict, $name);
        $this->reset('adding', 'addName');
        $this->dispatch('toast', message: 'Добавлено');
    }

    public function startRename(int $id): void
    {
        $item = $this->dictionaries()->items($this->dict)->firstWhere('id', $id);
        if (! $item) {
            return;
        }
        $this->resetValidation();
        $this->adding = false;
        $this->editId = $id;
        $this->editName = $item['name'];
    }

    public function cancelRename(): void
    {
        $this->reset('editId', 'editName');
        $this->resetValidation('editName');
    }

    public function saveRename(): void
    {
        $this->resetErrorBag('editName');
        $name = DictionaryService::clean($this->editName);
        if (! $this->editId) {
            return;
        }
        if ($name === '') {
            $this->addError('editName', 'Введите название');

            return;
        }
        if (mb_strlen($name) > 255) {
            $this->addError('editName', 'Слишком длинное название');

            return;
        }
        if ($this->dictionaries()->exists($this->dict, $name, $this->editId)) {
            $this->addError('editName', $this->kindWords()['exists'] . ' — объедините их');

            return;
        }

        $this->dictionaries()->rename($this->dict, $this->editId, $name);
        $this->reset('editId', 'editName');
        $this->dispatch('toast', message: 'Переименовано');
    }

    public function deleteItem(int $id): void
    {
        if ($this->dictionaries()->delete($this->dict, $id)) {
            $this->dispatch('toast', message: $this->kindWords()['removed']);
        } else {
            $this->dispatch('toast', message: 'Удалить нельзя — сначала объедините с другим', tone: 'danger');
        }
    }

    public function openMerge(int $source, ?int $target = null): void
    {
        $this->mergeSource = $source;
        $this->mergeTarget = $target ? (string) $target : '';
        $this->mergeQ = '';
    }

    public function closeMerge(): void
    {
        $this->reset('mergeSource', 'mergeTarget', 'mergeQ');
    }

    public function merge(): void
    {
        $items = $this->dictionaries()->items($this->dict);
        $source = $items->firstWhere('id', $this->mergeSource);
        $target = $items->firstWhere('id', (int) $this->mergeTarget);
        if (! $source || ! $target || $source['id'] === $target['id']) {
            return;
        }

        $this->dictionaries()->merge($this->dict, $source['id'], $target['id']);
        $this->closeMerge();
        $this->dispatch('toast', message: '«' . $source['name'] . '» объединён с «' . $target['name'] . '»');
    }

    public function render()
    {
        $data = [
            'tabs' => collect(self::TABS)->map(fn ($label, $key) => $key !== $this->tab && ! empty($this->dirty[$key]) ? $label . ' · не сохранено' : $label)->all(),
            'isTest' => YooKassaService::isTestMode(),
            'seoImages' => $this->tab === 'seo' ? $this->seoImages() : [],
            'offerUrl' => route('offer'),
            'tariffsUrl' => route('tariffs'),
            'dictFacts' => null,
        ];

        if ($this->tab === 'dictionaries') {
            $data = array_merge($data, $this->dictionaryData());
        }

        return view('livewire.cabinet.admin.settings', $data);
    }

    /** Строки «Картинки»: превью загруженного файла, стандартного или только что выбранного. */
    private function seoImages(): array
    {
        $meta = [
            'ogImage' => ['Картинка для превью ссылок', 'Telegram, WhatsApp, ВКонтакте · 1200×630'],
            'logo' => ['Логотип для поисковиков', 'PNG на прозрачном фоне'],
            'touchIcon' => ['Иконка для экрана телефона', 'PNG от 180×180'],
        ];
        $rows = [];
        foreach (self::SEO_FILES as $prop => $key) {
            $uploaded = SeoSettings::get($key) !== '';
            $new = $this->{$prop} && ! $this->getErrorBag()->has($prop) && method_exists($this->{$prop}, 'temporaryUrl');
            $rows[] = [
                'prop' => $prop,
                'title' => $meta[$prop][0],
                'sub' => $new ? 'Новая картинка · сохраните вкладку' : ($uploaded ? $meta[$prop][1] : 'Стандартная · ' . mb_strtolower(mb_substr($meta[$prop][1], 0, 1)) . mb_substr($meta[$prop][1], 1)),
                'url' => $new ? $this->safeTemporaryUrl($this->{$prop}) : SeoSettings::fileUrl($key),
                'action' => $uploaded || $new ? 'Заменить' : 'Загрузить',
            ];
        }

        return $rows;
    }

    private function safeTemporaryUrl($file): ?string
    {
        try {
            return $file->temporaryUrl();
        } catch (\Throwable) {
            return null;
        }
    }

    private function dictionaryData(): array
    {
        $svc = $this->dictionaries();
        $items = $svc->items($this->dict);
        $q = mb_strtolower(trim($this->dictQ));
        $unused = $items->filter(fn ($i) => $i['teachers'] === 0 && $i['applications'] === 0);

        $rows = $items
            ->filter(fn ($i) => $this->dictFilter !== 'unused' || ($i['teachers'] === 0 && $i['applications'] === 0))
            ->filter(fn ($i) => $q === '' || str_contains(mb_strtolower($i['name']), $q))
            ->values();

        $source = $this->mergeSource ? $items->firstWhere('id', $this->mergeSource) : null;
        $mergeOptions = collect();
        $mergeText = null;
        if ($source) {
            $mq = mb_strtolower(trim($this->mergeQ));
            $mergeOptions = $items->filter(fn ($i) => $i['id'] !== $source['id'] && ($mq === '' || str_contains(mb_strtolower($i['name']), $mq)))
                ->sortByDesc('teachers')->values();
            $target = $items->firstWhere('id', (int) $this->mergeTarget);
            if ($target) {
                $parts = array_filter([
                    $source['teachers'] ? plural_ru($source['teachers'], 'учитель', 'учителя', 'учителей') : null,
                    $source['applications'] ? plural_ru($source['applications'], 'заявка', 'заявки', 'заявок') : null,
                ]);
                $single = count($parts) === 1 && ($source['teachers'] + $source['applications']) === 1;
                $mergeText = [
                    'lead' => $parts ? implode(' и ', $parts) . ($single ? ' перейдёт в ' : ' перейдут в ') : 'Останется только ',
                    'target' => '«' . $target['name'] . '»',
                    'source' => '«' . $source['name'] . '»',
                ];
            }
        }

        $subjects = $this->dict === 'subjects' ? $items->count() : $svc->items('subjects')->count();
        $directs = $this->dict === 'directs' ? $items->count() : $svc->items('directs')->count();

        return [
            'dictFacts' => plural_ru($subjects, 'предмет', 'предмета', 'предметов') . ' · ' . plural_ru($directs, 'направление', 'направления', 'направлений'),
            'words' => $this->kindWords(),
            'rows' => $rows,
            'allCount' => $items->count(),
            'unusedCount' => $unused->count(),
            'pairs' => $svc->similar($this->dict),
            'mergeSourceItem' => $source,
            'mergeOptions' => $mergeOptions,
            'mergeText' => $mergeText,
        ];
    }

    /** «учителей: 41 · заявок: 2». */
    public static function used(array $item): string
    {
        return 'учителей: ' . $item['teachers'] . ($item['applications'] ? ' · заявок: ' . $item['applications'] : '');
    }
}
