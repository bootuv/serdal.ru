<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\EnsureCabinetRole;
use App\Livewire\Cabinet\Admin\Tariff as TariffScreen;
use App\Livewire\Cabinet\Admin\Tariffs;
use App\Models\Tariff;
use App\Models\User;
use App\Services\SubscriptionService;
use Database\Seeders\TariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Админка → Тарифы (/cabinet/admin/tariffs) и карточка тарифа (/cabinet/admin/tariffs/{id|new}). */
class TariffsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(TariffSeeder::class);
        SubscriptionService::flushCanStartCache();
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'username' => $role . uniqid(), 'is_active' => true, 'is_blocked' => false, 'is_profile_completed' => true]);
    }

    private function t(string $slug): Tariff
    {
        return Tariff::where('slug', $slug)->firstOrFail();
    }

    private function order(): array
    {
        return Tariff::orderBy('sort')->pluck('slug')->all();
    }

    public function test_access(): void
    {
        $pro = $this->t('pro');
        foreach (['/cabinet/admin/tariffs', '/cabinet/admin/tariffs/' . $pro->id, '/cabinet/admin/tariffs/new'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
            $tutor = $this->user(User::ROLE_TUTOR);
            $this->actingAs($tutor)->get($url)->assertRedirect(EnsureCabinetRole::homeFor($tutor));
            $this->actingAs($this->user(User::ROLE_STUDENT))->get($url)->assertRedirect(route('cabinet.student.home'));
            auth()->logout();
        }

        $admin = $this->user(User::ROLE_ADMIN);
        $this->actingAs($admin)->get('/cabinet/admin/tariffs')->assertOk()->assertSee('Добавить тариф')->assertSee('Профи');
        $this->actingAs($admin)->get('/cabinet/admin/tariffs/' . $pro->id)->assertOk()->assertSee('Так тариф видят учителя')->assertDontSee($pro->slug . '"', false);
        $this->actingAs($admin)->get('/cabinet/admin/tariffs/new')->assertOk()->assertSee('Новый тариф');
        $this->actingAs($admin)->get('/cabinet/admin/tariffs/999999')->assertNotFound();
        $this->actingAs($admin)->get('/cabinet/admin/tariffs/abc')->assertNotFound();
    }

    public function test_list_facts_badges_and_active_subscriptions(): void
    {
        $this->t('master')->update(['is_active' => false]);
        $tutor = $this->user(User::ROLE_TUTOR);
        SubscriptionService::activate($tutor, $this->t('pro'));

        Livewire::actingAs($this->user(User::ROLE_ADMIN))->test(Tariffs::class)
            ->assertSee('На сайте 3 тарифа · 1 скрыт · 1 активная подписка')
            ->assertSee('Скрыт')
            ->assertSee('Бесплатно')
            ->assertSee('1 490 ₽ за 30 дней');
    }

    public function test_reorder_by_drag_and_by_click_with_undo(): void
    {
        $this->assertSame(['start', 'basic', 'pro', 'master'], $this->order());
        $c = Livewire::actingAs($this->user(User::ROLE_ADMIN))->test(Tariffs::class)
            ->call('move', $this->t('master')->id, $this->t('basic')->id, true)
            ->assertSee('Порядок сохранён — на сайте он уже новый');
        $this->assertSame(['start', 'master', 'basic', 'pro'], $this->order());

        $c->call('undo')->assertSet('undoOrder', null);
        $this->assertSame(['start', 'basic', 'pro', 'master'], $this->order());

        $c->call('pick', $this->t('start')->id)->assertSee('Нажмите на тариф, перед которым должен стоять «Старт»')
            ->call('dropBefore', null)->assertSet('moving', null);
        $this->assertSame(['basic', 'pro', 'master', 'start'], $this->order());
    }

    public function test_create_tariff_generates_unique_slug(): void
    {
        Tariff::create(['name' => 'Школа старая', 'slug' => 'shkola', 'price' => 100, 'period_days' => 30, 'max_participants' => 2, 'is_active' => false, 'sort' => 100]);

        Livewire::actingAs($this->user(User::ROLE_ADMIN))->test(TariffScreen::class, ['tariff' => 'new'])
            ->set('name', 'Школа')
            ->set('price', '12 900')
            ->set('yearlyPrice', '')
            ->set('lessonsUnlimited', true)
            ->set('participants', '50')
            ->set('durationUnlimited', true)
            ->set('recording', '365')
            ->set('newFeature', 'Записи занятий')
            ->call('addFeature')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $t = Tariff::where('name', 'Школа')->firstOrFail();
        $this->assertSame('shkola-2', $t->slug);
        $this->assertSame(12900, (int) $t->price);
        $this->assertNull($t->lessons_per_month);
        $this->assertNull($t->max_duration_minutes);
        $this->assertSame(['Записи занятий'], $t->features);
        $this->assertSame('start', Tariff::orderBy('sort')->value('slug'));
        $this->assertSame($t->id, Tariff::orderByDesc('sort')->value('id'));
    }

    public function test_validation_and_edit(): void
    {
        $pro = $this->t('pro');
        Livewire::actingAs($this->user(User::ROLE_ADMIN))->test(TariffScreen::class, ['tariff' => (string) $pro->id])
            ->assertSet('name', $pro->name)
            ->set('name', '')
            ->set('lessonsUnlimited', false)
            ->set('lessons', '')
            ->call('save')
            ->assertHasErrors(['name', 'lessons'])
            ->set('name', 'Профи плюс')
            ->set('lessons', '150')
            ->set('yearlyPrice', '29 900')
            ->assertSee('скидка')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast', message: 'Тариф сохранён');

        $pro->refresh();
        $this->assertSame('Профи плюс', $pro->name);
        $this->assertSame('pro', $pro->slug);
        $this->assertSame(150, (int) $pro->lessons_per_month);
        $this->assertSame(29900, (int) $pro->yearly_price);
    }

    public function test_hide_with_consequences_undo_and_return(): void
    {
        $start = $this->t('start');
        $tutor = $this->user(User::ROLE_TUTOR);
        SubscriptionService::activate($tutor, $start);

        $c = Livewire::actingAs($this->user(User::ROLE_ADMIN))->test(TariffScreen::class, ['tariff' => (string) $start->id])
            ->call('openHide')
            ->assertSee('Скрыть тариф «Старт»?')
            ->assertSee('1 активная подписка продолжит действовать')
            ->assertSee('Это бесплатный тариф')
            ->call('hide')
            ->assertSee('Тариф скрыт с сайта')
            ->assertSee('Вернуть на сайт');
        $this->assertFalse($start->fresh()->is_active);
        $this->assertNotNull($start->fresh()); // не удалён — история на месте

        $c->call('unhide')->assertNotDispatched('toast');
        $this->assertTrue($start->fresh()->is_active);

        $c->call('toggleSite')->assertSet('hiding', true)->call('hide')->call('dismissUndo')
            ->call('unhide')->assertDispatched('toast', message: 'Тариф снова на сайте');
        $this->assertTrue($start->fresh()->is_active);
    }
}
