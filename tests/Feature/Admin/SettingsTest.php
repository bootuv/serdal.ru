<?php

namespace Tests\Feature\Admin;

use App\Livewire\Cabinet\Admin\Settings;
use App\Models\Setting;
use App\Models\User;
use App\Services\YooKassaService;
use App\Support\SeoSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Админка → Настройки (/cabinet/admin/settings): вкладки сохраняются отдельно, режим ЮKassa — сразу. */
class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('s3');
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'first_name' => 'Анна',
            'last_name' => 'Куликова',
            'username' => $role . uniqid(),
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => true,
        ]);
    }

    private function setting(string $key): ?string
    {
        return Setting::where('key', $key)->value('value');
    }

    public function test_access(): void
    {
        $url = route('cabinet.admin.settings');
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($this->user(User::ROLE_TUTOR))->get($url)->assertRedirect(route('cabinet.teacher.today'));
        $this->actingAs($this->user(User::ROLE_STUDENT))->get($url)->assertRedirect(route('cabinet.student.home'));
        $this->actingAs($this->user(User::ROLE_ADMIN))->get($url)->assertOk()
            ->assertSee('Настройки')->assertSee('Видеосвязь')->assertSee('Справочники')->assertSee('Сервер видеосвязи');
    }

    public function test_every_tab_renders(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        foreach (['video' => 'Занятия по умолчанию', 'records' => 'Хранение записей', 'payments' => 'Режим ЮKassa',
            'legal' => 'Реквизиты', 'seo' => 'Поисковики', 'b2b' => 'Что входит', 'dictionaries' => 'Похожие названия'] as $tab => $text) {
            $this->actingAs($admin)->get(route('cabinet.admin.settings', ['tab' => $tab]))->assertOk()->assertSee($text);
        }
    }

    public function test_video_tab_saves_only_its_keys(): void
    {
        Livewire::actingAs($this->user(User::ROLE_ADMIN))->test(Settings::class)
            ->set('video.bbb_url', 'https://video.serdal.ru/bigbluebutton/')
            ->set('video.bbb_secret', 'secret')
            ->call('flip', 'video', 'record')
            ->call('flip', 'video', 'unknown')
            ->set('video.duration', 90)
            ->assertSet('dirty.video', true)
            // Несохранённая вкладка помечена, когда открыта другая
            ->set('tab', 'records')
            ->assertSee('Видеосвязь · не сохранено')
            ->set('tab', 'video')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('saved.video', true)
            ->assertSee('Сохранено');

        $this->assertSame('https://video.serdal.ru/bigbluebutton/', $this->setting('bbb_url'));
        $this->assertSame('secret', $this->setting('bbb_secret'));
        $this->assertSame('1', $this->setting('bbb_record'));
        $this->assertSame('90', $this->setting('bbb_duration'));
        // Другие вкладки не сохранялись
        $this->assertNull($this->setting('recording_auto_upload'));
        $this->assertNull($this->setting('legal_name'));
    }

    public function test_validation_per_tab(): void
    {
        Livewire::actingAs($this->user(User::ROLE_ADMIN))->test(Settings::class)
            ->set('video.bbb_url', 'не адрес')
            ->call('save')
            ->assertHasErrors(['video.bbb_url'])
            ->set('tab', 'b2b')
            ->set('b2b.b2b_email', 'почта')
            ->call('save')
            ->assertHasErrors(['b2b.b2b_email'])
            ->set('tab', 'payments')
            ->set('payments.extra_lessons_max', 500)
            ->call('save')
            ->assertHasErrors(['payments.extra_lessons_max']);
    }

    public function test_payments_legal_b2b_and_records(): void
    {
        Livewire::actingAs($this->user(User::ROLE_ADMIN))->test(Settings::class, ['tab' => 'payments'])
            ->set('payments.yookassa_shop_id', '482915')
            ->set('payments.yookassa_secret_key', 'live_key')
            ->call('flip', 'payments', 'yookassa_recurring_enabled')
            ->set('payments.extra_lesson_price', 350)
            ->call('save')
            ->assertHasNoErrors()
            ->set('tab', 'legal')
            ->set('legal.legal_name', 'ИП Соколов Дмитрий Андреевич')
            ->set('legal.offer_edition_date', '2026-08-15')
            ->set('legal.offer_refund_days', 7)
            ->call('save')
            ->assertHasNoErrors()
            ->set('tab', 'records')
            ->call('flip', 'records', 'recording_auto_upload')
            ->call('save')
            ->set('tab', 'b2b')
            ->call('flip', 'b2b', 'b2b_enabled')
            ->assertSee('Скрыт — на сайте его нет')
            ->call('removeFeature', 0)
            ->set('featDraft', 'Обучение команды')
            ->call('addFeature')
            ->assertSee('Обучение команды')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('482915', $this->setting('yookassa_shop_id'));
        $this->assertSame('1', $this->setting('yookassa_recurring_enabled'));
        $this->assertSame('350', $this->setting('extra_lesson_price'));
        $this->assertSame('ИП Соколов Дмитрий Андреевич', $this->setting('legal_name'));
        $this->assertSame('2026-08-15', $this->setting('offer_edition_date'));
        $this->assertSame('7', $this->setting('offer_refund_days'));
        $this->assertSame('1', $this->setting('recording_auto_upload'));
        $this->assertSame('0', $this->setting('b2b_enabled'));
        $features = json_decode($this->setting('b2b_features'), true);
        $this->assertCount(5, $features);
        $this->assertSame('Обучение команды', end($features));
    }

    public function test_yookassa_mode_switches_with_confirmation(): void
    {
        $test = Livewire::actingAs($this->user(User::ROLE_ADMIN))->test(Settings::class, ['tab' => 'payments'])
            ->call('askMode')
            ->assertSee('Включить тестовый режим?')
            ->call('toggleMode')
            ->assertDispatched('toast', tone: 'danger');
        $this->assertTrue(YooKassaService::isTestMode());

        $test->assertSee('Тестовый')
            ->call('askMode')
            ->assertSee('Включить боевой режим?')
            ->call('toggleMode')
            ->assertDispatched('toast', message: 'ЮKassa работает в боевом режиме');
        $this->assertFalse(YooKassaService::isTestMode());
    }

    public function test_seo_tab_saves_texts_switches_and_images(): void
    {
        Livewire::actingAs($this->user(User::ROLE_ADMIN))->test(Settings::class, ['tab' => 'seo'])
            ->assertSet('seo.seo_site_name', 'Serdal')
            ->set('seo.seo_default_title', 'Serdal — занятия онлайн')
            ->call('flip', 'seo', 'seo_indexing_enabled')
            ->assertSee('Сайт скрыт')
            ->set('ogImage', UploadedFile::fake()->image('og.png', 1200, 630))
            ->assertSet('dirty.seo', true)
            ->call('save')
            ->assertHasNoErrors();

        SeoSettings::flush();
        $this->assertSame('Serdal — занятия онлайн', SeoSettings::get('seo_default_title'));
        $this->assertFalse(SeoSettings::enabled('seo_indexing_enabled'));
        $path = SeoSettings::get('seo_og_image');
        $this->assertStringStartsWith('seo/', $path);
        Storage::disk('s3')->assertExists($path);
    }

    public function test_home_hero_texts_are_edited_in_seo_tab(): void
    {
        $this->get('/')->assertSee('Репетиторы и наставники онлайн</h1>', false);

        Livewire::actingAs($this->user(User::ROLE_ADMIN))->test(Settings::class, ['tab' => 'seo'])
            ->assertSee('Обложка главной')
            ->set('seo.home_hero_title', 'Занятия с репетитором онлайн')
            ->set('seo.home_hero_subtitle', 'Предметы, экзамены и языки')
            ->call('save')
            ->assertHasNoErrors();

        SeoSettings::flush();
        $this->get('/')
            ->assertSee('<h1 class="h1 white-text">Занятия с репетитором онлайн</h1>', false)
            ->assertSee('Предметы, экзамены и языки');

        Livewire::actingAs($this->user(User::ROLE_ADMIN))->test(Settings::class, ['tab' => 'seo'])
            ->set('seo.home_hero_title', '')
            ->call('save')
            ->assertHasErrors('seo.home_hero_title');
    }
}
