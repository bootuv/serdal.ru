<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\EnsureCabinetRole;
use App\Livewire\Cabinet\Admin\Applications;
use App\Mail\TeacherApplicationApproved;
use App\Mail\TeacherApplicationRejected;
use App\Models\Direct;
use App\Models\Subject;
use App\Models\Tariff;
use App\Models\TeacherApplication;
use App\Models\User;
use Database\Seeders\TariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/** Админка → Заявки учителей (/cabinet/admin/applications): вкладки, окно заявки, одобрение и отказ. */
class ApplicationsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Mail::fake();
        $this->seed(TariffSeeder::class);
        $this->admin = $this->user(User::ROLE_ADMIN);
    }

    private function user(string $role, array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'username' => $role . uniqid(),
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => true,
        ], $attrs));
    }

    private function application(array $attrs = []): TeacherApplication
    {
        return TeacherApplication::create($attrs + [
            'last_name' => 'Лебедева',
            'first_name' => 'Екатерина',
            'middle_name' => 'Андреевна',
            'email' => 'ekaterina.lebedeva@mail.ru',
            'phone' => '+7 915 227-63-10',
            'telegram' => 'lebedeva_de',
            'about' => 'Преподаю английский 8 лет.',
            'status' => TeacherApplication::STATUS_PENDING,
        ]);
    }

    public function test_access(): void
    {
        $this->get('/cabinet/admin/applications')->assertRedirect(route('login'));
        $teacher = $this->user(User::ROLE_TUTOR);
        $this->actingAs($teacher)->get('/cabinet/admin/applications')->assertRedirect(EnsureCabinetRole::homeFor($teacher));
        $this->actingAs($this->user(User::ROLE_STUDENT))->get('/cabinet/admin/applications')->assertRedirect(route('cabinet.student.home'));

        $this->application();
        $this->actingAs($this->admin)->get('/cabinet/admin/applications')
            ->assertOk()
            ->assertSee('Заявки учителей')
            ->assertSee('На рассмотрении')
            ->assertSee('Екатерина Лебедева')
            ->assertSee('ekaterina.lebedeva@mail.ru');
    }

    public function test_tabs_search_and_empty_states(): void
    {
        $this->application();
        $old = $this->application(['first_name' => 'Дмитрий', 'last_name' => 'Зайцев', 'email' => 'dz@yandex.ru']);
        $old->forceFill(['created_at' => now()->subDays(2)])->save();
        $this->application(['first_name' => 'Сергей', 'last_name' => 'Ковалёв', 'email' => 'k@mail.ru', 'status' => 'rejected', 'reject_reason' => 'Нет опыта', 'decided_at' => now()]);

        Livewire::actingAs($this->admin)->test(Applications::class)
            ->assertSee('Дмитрий Зайцев')
            ->assertSee('ждёт 2 дня')
            ->assertDontSee('Сергей Ковалёв')
            ->set('q', 'dz@')
            ->assertSee('Дмитрий Зайцев')
            ->assertDontSee('Екатерина Лебедева')
            ->set('q', 'нет такой')
            ->assertSee('Никого не нашли — проверьте имя или почту')
            ->set('q', '')
            ->set('tab', 'rejected')
            ->assertSee('Сергей Ковалёв')
            ->assertSee('отклонена сегодня')
            ->set('tab', 'approved')
            ->assertSee('Пока пусто');
    }

    public function test_view_and_approve_creates_teacher(): void
    {
        $referrer = $this->user(User::ROLE_TUTOR, ['name' => 'Мария Соколова']);
        $subject = Subject::create(['name' => 'Английский язык']);
        $direct = Direct::create(['name' => 'ЕГЭ']);
        $pro = Tariff::where('slug', 'pro')->first();
        $app = $this->application([
            'subjects' => [$subject->id], 'directs' => [$direct->id], 'grade' => ['10', '11', 'adults'],
            'desired_tariff_id' => $pro->id, 'referred_by_id' => $referrer->id, 'whatsup' => '+7 915 227-63-10',
        ]);

        $page = Livewire::actingAs($this->admin)->test(Applications::class)
            ->call('open', $app->id)
            ->assertSee('Лебедева Екатерина Андреевна')
            ->assertSee('Английский язык')
            ->assertSee('ЕГЭ')
            ->assertSee('«Профи» · 2 990 ₽ в месяц')
            ->assertSee('Мария Соколова')
            ->assertSee('Одобрить')
            ->call('toApprove')
            ->assertSee('Одобрить заявку?')
            ->call('approve')
            ->assertSet('step', '')
            ->assertSee('Заявка одобрена — пароль отправлен на почту');

        $user = User::where('email', 'ekaterina.lebedeva@mail.ru')->firstOrFail();
        $this->assertSame(User::ROLE_TUTOR, $user->role);
        $this->assertSame([$subject->id], $user->subjects()->pluck('subjects.id')->all());
        $this->assertSame([$direct->id], $user->directs()->pluck('directs.id')->all());
        $this->assertSame(['10', '11', 'adults'], $user->grade);
        $this->assertSame($pro->id, (int) $user->desired_tariff_id);
        $this->assertSame($referrer->id, (int) $user->referred_by_id);
        $this->assertSame('lebedeva_de', $user->telegram);

        $app->refresh();
        $this->assertSame(TeacherApplication::STATUS_APPROVED, $app->status);
        $this->assertNotNull($app->decided_at);
        Mail::assertSent(TeacherApplicationApproved::class, fn ($m) => $m->hasTo('ekaterina.lebedeva@mail.ru'));

        // Решённую заявку нельзя одобрить повторно
        $page->call('open', $app->id)->assertSee('одобрена сегодня')->call('toApprove')->assertNotFound();
    }

    public function test_approve_with_taken_email_shows_error(): void
    {
        $this->user(User::ROLE_STUDENT, ['name' => 'Дмитрий Зайцев', 'email' => 'dz@yandex.ru']);
        $app = $this->application(['first_name' => 'Дмитрий', 'last_name' => 'Зайцев', 'email' => 'dz@yandex.ru']);

        Livewire::actingAs($this->admin)->test(Applications::class)
            ->call('open', $app->id)
            ->call('toApprove')
            ->call('approve')
            ->assertSet('step', 'error')
            ->assertSee('Эта почта уже зарегистрирована')
            ->assertSee('dz@yandex.ru — ученик Дмитрий Зайцев. Аккаунт учителя не создан.');

        $this->assertSame(TeacherApplication::STATUS_PENDING, $app->fresh()->status);
        $this->assertSame(1, User::where('email', 'dz@yandex.ru')->count());
        Mail::assertNotSent(TeacherApplicationApproved::class);
    }

    public function test_reject_with_reason_sends_letter(): void
    {
        $app = $this->application();

        Livewire::actingAs($this->admin)->test(Applications::class)
            ->call('open', $app->id)
            ->call('toReject')
            ->assertSee('Уйдёт в письме об отказе на ekaterina.lebedeva@mail.ru')
            ->set('reason', 'Расскажите подробнее об опыте')
            ->call('reject')
            ->assertSee('Заявка отклонена — письмо отправлено')
            ->set('tab', 'rejected')
            ->call('open', $app->id)
            ->assertSee('Причина отказа')
            ->assertSee('Расскажите подробнее об опыте');

        $app->refresh();
        $this->assertSame(TeacherApplication::STATUS_REJECTED, $app->status);
        $this->assertSame('Расскажите подробнее об опыте', $app->reject_reason);
        Mail::assertSent(TeacherApplicationRejected::class, fn ($m) => $m->hasTo('ekaterina.lebedeva@mail.ru') && $m->reason === 'Расскажите подробнее об опыте');
    }

    public function test_reject_without_reason(): void
    {
        $app = $this->application();

        Livewire::actingAs($this->admin)->test(Applications::class)
            ->call('open', $app->id)->call('toReject')->call('reject');

        $this->assertNull($app->fresh()->reject_reason);
        Mail::assertSent(TeacherApplicationRejected::class, fn ($m) => $m->reason === null);
        $this->assertStringNotContainsString('Причина:', (new TeacherApplicationRejected(null))->render());
        $this->assertStringContainsString('Причина:', (new TeacherApplicationRejected('Нет опыта'))->render());
    }
}
