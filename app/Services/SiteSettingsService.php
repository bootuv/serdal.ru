<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\OfferSettings;
use App\Support\SeoSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Настройки сайта по группам (вкладки «Настройки» новой админки). Ключи и значения по умолчанию —
 * каждая группа читается и сохраняется отдельно.
 * Партнёрская программа — в App\Services\ReferralService.
 */
class SiteSettingsService
{
    public const B2B_FEATURES_DEFAULT = [
        '5 рабочих мест преподавателей включено (дополнительное место — 1 900 ₽/мес)',
        'White-label: платформа под брендом вашего центра',
        'Административная панель для управления преподавателями и учениками',
        'Приоритетная поддержка и SLA',
        'Обучение и онбординг команды',
    ];

    private function get(string $key): ?string
    {
        return Setting::where('key', $key)->value('value');
    }

    private function put(string $key, $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value === null ? '' : (string) $value]);
    }

    private function flag(bool $value): string
    {
        return $value ? '1' : '0';
    }

    /* ---------- Видеосвязь ---------- */

    public function video(): array
    {
        return [
            'bbb_url' => (string) $this->get('bbb_url'),
            'bbb_secret' => (string) $this->get('bbb_secret'),
            'record' => $this->get('bbb_record') === '1',
            'auto_start_recording' => $this->get('bbb_auto_start_recording') === '1',
            'allow_start_stop_recording' => $this->get('bbb_allow_start_stop_recording') !== '0',
            'mute_on_start' => $this->get('bbb_mute_on_start') === '1',
            'webcams_only_for_moderator' => $this->get('bbb_webcams_only_for_moderator') === '1',
            'max_participants' => (string) ($this->get('bbb_max_participants') ?? 0),
            'duration' => (string) ($this->get('bbb_duration') ?? 0),
        ];
    }

    public function saveVideo(array $d): void
    {
        $this->put('bbb_url', trim((string) ($d['bbb_url'] ?? '')));
        $this->put('bbb_secret', trim((string) ($d['bbb_secret'] ?? '')));
        $this->put('bbb_record', $this->flag(! empty($d['record'])));
        $this->put('bbb_auto_start_recording', $this->flag(! empty($d['auto_start_recording'])));
        $this->put('bbb_allow_start_stop_recording', $this->flag(! empty($d['allow_start_stop_recording'])));
        $this->put('bbb_mute_on_start', $this->flag(! empty($d['mute_on_start'])));
        $this->put('bbb_webcams_only_for_moderator', $this->flag(! empty($d['webcams_only_for_moderator'])));
        $this->put('bbb_max_participants', max(0, (int) ($d['max_participants'] ?? 0)));
        $this->put('bbb_duration', max(0, (int) ($d['duration'] ?? 0)));
    }

    /* ---------- Записи ---------- */

    public function records(): array
    {
        return [
            'recording_auto_upload' => $this->get('recording_auto_upload') === '1',
            'recording_delete_after_upload' => $this->get('recording_delete_after_upload') === '1',
        ];
    }

    public function saveRecords(array $d): void
    {
        $this->put('recording_auto_upload', $this->flag(! empty($d['recording_auto_upload'])));
        $this->put('recording_delete_after_upload', $this->flag(! empty($d['recording_delete_after_upload'])));
    }

    /* ---------- Оплата (ЮKassa и дополнительные занятия) ---------- */

    public function payments(): array
    {
        return [
            'yookassa_shop_id' => (string) $this->get('yookassa_shop_id'),
            'yookassa_secret_key' => (string) $this->get('yookassa_secret_key'),
            'yookassa_test_shop_id' => (string) $this->get('yookassa_test_shop_id'),
            'yookassa_test_secret_key' => (string) $this->get('yookassa_test_secret_key'),
            'yookassa_recurring_enabled' => $this->get('yookassa_recurring_enabled') === '1',
            'extra_lesson_price' => (string) SubscriptionService::extraLessonPrice(),
            'extra_lessons_max' => (string) SubscriptionService::extraLessonsMax(),
        ];
    }

    /** Режим ЮKassa переключается отдельно (toggleYooKassaTestMode) — здесь его не трогаем. */
    public function savePayments(array $d): void
    {
        foreach (['yookassa_shop_id', 'yookassa_secret_key', 'yookassa_test_shop_id', 'yookassa_test_secret_key'] as $key) {
            $this->put($key, trim((string) ($d[$key] ?? '')));
        }
        $this->put('yookassa_recurring_enabled', $this->flag(! empty($d['yookassa_recurring_enabled'])));
        $this->put('extra_lesson_price', max(1, (int) ($d['extra_lesson_price'] ?? 0)));
        $this->put('extra_lessons_max', max(1, (int) ($d['extra_lessons_max'] ?? 0)));
    }

    /** Переключить боевой/тестовый режим ЮKassa сразу, без остальной формы. Возвращает новый режим (true — тестовый). */
    public function toggleYooKassaTestMode(): bool
    {
        $enable = ! YooKassaService::isTestMode();
        $this->put('yookassa_test_mode', $this->flag($enable));

        return $enable;
    }

    /* ---------- Реквизиты и оферта ---------- */

    public function legal(): array
    {
        return [
            'legal_name' => (string) $this->get('legal_name'),
            'legal_inn' => (string) $this->get('legal_inn'),
            'legal_ogrn' => (string) $this->get('legal_ogrn'),
            'legal_address' => (string) $this->get('legal_address'),
            'legal_email' => (string) ($this->get('legal_email') ?? 'info@serdal.ru'),
            'legal_phone' => (string) $this->get('legal_phone'),
            'offer_edition_date' => (string) ($this->get('offer_edition_date') ?: ''),
            'offer_payment_provider' => (string) ($this->get('offer_payment_provider') ?: OfferSettings::OFFER_DEFAULTS['offer_payment_provider']),
            'offer_payment_methods' => (string) ($this->get('offer_payment_methods') ?: OfferSettings::OFFER_DEFAULTS['offer_payment_methods']),
            'offer_refund_days' => (string) ($this->get('offer_refund_days') ?: OfferSettings::OFFER_DEFAULTS['offer_refund_days']),
            'offer_refund_processing_days' => (string) ($this->get('offer_refund_processing_days') ?: OfferSettings::OFFER_DEFAULTS['offer_refund_processing_days']),
        ];
    }

    public function saveLegal(array $d): void
    {
        foreach (['legal_name', 'legal_inn', 'legal_ogrn', 'legal_address', 'legal_email', 'legal_phone',
            'offer_edition_date', 'offer_payment_provider', 'offer_payment_methods'] as $key) {
            $this->put($key, trim((string) ($d[$key] ?? '')));
        }
        $this->put('offer_refund_days', max(0, (int) ($d['offer_refund_days'] ?? 0)));
        $this->put('offer_refund_processing_days', max(0, (int) ($d['offer_refund_processing_days'] ?? 0)));
    }

    /* ---------- Сайт и поисковики ---------- */

    public function seo(): array
    {
        $state = [];
        foreach (array_keys(SeoSettings::DEFAULTS) as $key) {
            if (in_array($key, SeoSettings::FILE_KEYS, true)) {
                continue;
            }
            $value = SeoSettings::get($key);
            $state[$key] = in_array($key, ['seo_indexing_enabled', 'seo_ai_crawlers_enabled'], true) ? $value !== '0' : $value;
        }

        return $state;
    }

    /** $files: ключ картинки => новый файл (загружается на CDN в папку seo). */
    public function saveSeo(array $d, array $files = []): void
    {
        foreach (array_keys(SeoSettings::DEFAULTS) as $key) {
            if (in_array($key, SeoSettings::FILE_KEYS, true)) {
                if (($files[$key] ?? null) instanceof UploadedFile) {
                    $this->put($key, $key === 'seo_og_image'
                        ? $this->storeLinkPreview($files[$key])
                        : $files[$key]->storePublicly('seo', 's3'));
                }
                continue;
            }
            $value = $d[$key] ?? null;
            $this->put($key, in_array($key, ['seo_indexing_enabled', 'seo_ai_crawlers_enabled'], true)
                ? $this->flag(! empty($value))
                : trim((string) $value));
        }
        SeoSettings::flush();
    }

    /**
     * Картинка для превью ссылок — всегда JPG до 1200px по ширине: PNG такого размера весит в разы больше,
     * а WebP понимают не все соцсети и мессенджеры. Прозрачные места становятся белыми.
     */
    private function storeLinkPreview(UploadedFile $file): string
    {
        try {
            $jpeg = (string) Image::read($file->get())->scaleDown(width: 1200)->toJpeg(88);
            $path = 'seo/' . Str::lower(Str::random(24)) . '.jpg';
            Storage::disk('s3')->put($path, $jpeg, 'public');

            return $path;
        } catch (\Throwable $e) {
            report($e);

            return $file->storePublicly('seo', 's3');
        }
    }

    /* ---------- Блок для школ (B2B на странице тарифов) ---------- */

    public function b2b(): array
    {
        return [
            'b2b_enabled' => $this->get('b2b_enabled') !== '0',
            'b2b_title' => (string) ($this->get('b2b_title') ?? 'Для образовательных центров (B2B)'),
            'b2b_description' => (string) ($this->get('b2b_description') ?? OfferSettings::B2B_DEFAULTS['b2b_description']),
            'b2b_price_label' => (string) ($this->get('b2b_price_label') ?? 'от 14 900 ₽'),
            'b2b_price_note' => (string) ($this->get('b2b_price_note') ?? '5 рабочих мест включено'),
            'b2b_features' => json_decode($this->get('b2b_features') ?? '', true) ?: self::B2B_FEATURES_DEFAULT,
            'b2b_email' => (string) ($this->get('b2b_email') ?? 'info@serdal.ru'),
        ];
    }

    public function saveB2b(array $d): void
    {
        $this->put('b2b_enabled', $this->flag(! empty($d['b2b_enabled'])));
        foreach (['b2b_title', 'b2b_description', 'b2b_price_label', 'b2b_price_note', 'b2b_email'] as $key) {
            $this->put($key, trim((string) ($d[$key] ?? '')));
        }
        $features = array_values(array_filter(array_map(fn ($f) => trim((string) $f), (array) ($d['b2b_features'] ?? [])), fn ($f) => $f !== ''));
        $this->put('b2b_features', json_encode($features, JSON_UNESCAPED_UNICODE));
    }
}
