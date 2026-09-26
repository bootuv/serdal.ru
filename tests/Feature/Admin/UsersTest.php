<?php

namespace Tests\Feature\Admin;

use App\Livewire\Cabinet\Admin\Users;
use App\Models\Subject;
use App\Models\Tariff;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Админка → Пользователи (/cabinet/admin/users): вкладки, поиск, фильтры, «Показать ещё», «Добавить пользователя». */
class UsersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(\Database\Seeders\TariffSeeder::class);
    }

    private function user(string $role, array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'first_name' => 'Мария',
            'last_name' => 'Соколова',
            'username' => $role . uniqid(),
            'is_active' => true,
            'is_blocked' => false,
            'is_profile_completed' => true,
        ], $attrs));
    }

    public function test_access(): void
    {
        $this->get(route('cabinet.admin.users'))->assertRedirect(route('login'));

        $this->actingAs($this->user(User::ROLE_TUTOR))->get(route('cabinet.admin.users'))
            ->assertRedirect(route('cabinet.teacher.today'));
        $this->actingAs($this->user(User::ROLE_STUDENT))->get(route('cabinet.admin.users'))
            ->assertRedirect(route('cabinet.student.home'));

        $this->actingAs($this->user(User::ROLE_ADMIN))->get(route('cabinet.admin.users'))
            ->assertOk()
            ->assertSee('Пользователи')
            ->assertSee('Добавить пользователя');
    }

    public function test_teacher_rows_show_exceptions_and_tariff(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $math = Subject::create(['name' => 'Математика']);
        $blocked = $this->user(User::ROLE_TUTOR, ['first_name' => 'Сергей', 'last_name' => 'Никитин', 'is_blocked' => true]);
        $hidden = $this->user(User::ROLE_TUTOR, ['first_name' => 'Ольга', 'last_name' => 'Фёдорова', 'is_active' => false]);
        $new = $this->user(User::ROLE_TUTOR, ['first_name' => 'Кирилл', 'last_name' => 'Морозов', 'is_profile_completed' => false]);
        $new->subjects()->attach($math);
        $sub = SubscriptionService::activate($hidden, Tariff::where('slug', 'pro')->first());
        $sub->update(['ends_at' => now()->addDay()->setTime(12, 0)]);

        Livewire::actingAs($admin)->test(Users::class)
            ->assertSee('Никитин Сергей')
            ->assertSee('Заблокирован')
            ->assertSee('Скрыт из каталога')
            ->assertSee('Первые шаги не пройдены')
            ->assertSee('Профи')
            ->assertSee('закончится завтра')
            ->assertSee('Без тарифа')
            ->assertSee('математика')
            ->assertDontSee('Вход закрыт');
    }

    public function test_search_by_name_email_and_phone(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $this->user(User::ROLE_TUTOR, ['first_name' => 'Иван', 'last_name' => 'Орлов', 'email' => 'orlov@yandex.ru', 'phone' => '+7 (903) 118-40-52']);
        $this->user(User::ROLE_TUTOR, ['first_name' => 'Елена', 'last_name' => 'Васильева', 'email' => 'vas@mail.ru']);

        $page = Livewire::actingAs($admin)->test(Users::class);
        $page->set('search', 'орлов')->assertSee('Орлов Иван')->assertDontSee('Васильева Елена')->assertSee('Найдено: 1');
        $page->set('search', 'vas@')->assertSee('Васильева Елена')->assertDontSee('Орлов Иван');
        $page->set('search', '903 118')->assertSee('Орлов Иван')->assertDontSee('Васильева Елена');
        $page->set('search', 'никого-нет')->assertSee('Никого не нашли');
    }

    public function test_teacher_filters(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $math = Subject::create(['name' => 'Математика']);
        $a = $this->user(User::ROLE_TUTOR, ['first_name' => 'Иван', 'last_name' => 'Орлов', 'grade' => ['10', '11']]);
        $a->subjects()->attach($math);
        $b = $this->user(User::ROLE_TUTOR, ['first_name' => 'Елена', 'last_name' => 'Васильева', 'grade' => [5, 6], 'is_profile_completed' => false]);
        SubscriptionService::activate($a, Tariff::where('slug', 'basic')->first());

        $page = Livewire::actingAs($admin)->test(Users::class);
        $page->set('subject', (string) $math->id)->assertSee('Орлов Иван')->assertDontSee('Васильева Елена');
        $page->call('resetFilters')->call('toggleGrade', '5')->assertSee('Васильева Елена')->assertDontSee('Орлов Иван');
        $page->call('resetFilters')->set('onboarding', true)->assertSee('Васильева Елена')->assertDontSee('Орлов Иван');
        $page->call('resetFilters')->set('tariff', 'none')->assertSee('Васильева Елена')->assertDontSee('Орлов Иван');
        $page->call('resetFilters')->set('tariff', (string) Tariff::where('slug', 'basic')->value('id'))->assertSee('Орлов Иван')->assertDontSee('Васильева Елена');
    }

    public function test_students_without_teacher_filter_and_admins_tab(): void
    {
        $admin = $this->user(User::ROLE_ADMIN, ['first_name' => 'Анна', 'last_name' => 'Куликова']);
        $this->user(User::ROLE_ADMIN, ['first_name' => 'Роман', 'last_name' => 'Ткачёв', 'last_login_at' => now()->subDay()->setTime(19, 40)]);
        $teacher = $this->user(User::ROLE_TUTOR);
        $withTeacher = $this->user(User::ROLE_STUDENT, ['first_name' => 'Павел', 'last_name' => 'Ким', 'grade' => [11]]);
        $teacher->students()->attach($withTeacher->id);
        $this->user(User::ROLE_STUDENT, ['first_name' => 'Максим', 'last_name' => 'Егоров']);

        $page = Livewire::actingAs($admin)->test(Users::class)->set('tab', 'students');
        $page->assertSee('Ким Павел')->assertSee('11 класс')->assertSee('Соколова Мария')->assertSee('пока нет учителя');
        $page->set('noTeacher', true)->assertSee('Егоров Максим')->assertDontSee('Ким Павел');

        $page->set('tab', 'admins')->assertSee('Это вы')->assertSee('вчера в 19:40');
    }

    public function test_show_more(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        for ($i = 0; $i < 25; $i++) {
            $this->user(User::ROLE_STUDENT, ['first_name' => 'Ученик' . $i, 'last_name' => 'Тестов']);
        }

        Livewire::actingAs($admin)->test(Users::class)->set('tab', 'students')
            ->assertSee('Показаны 20 из 25')
            ->assertSee('Показать ещё')
            ->call('more')
            ->assertSee('Показаны 25 из 25')
            ->assertDontSee('Показать ещё');
    }

    public function test_add_teacher_with_login_link(): void
    {
        Notification::fake();
        $admin = $this->user(User::ROLE_ADMIN);

        Livewire::actingAs($admin)->test(Users::class)
            ->call('openAdd')
            ->assertSet('role', 'teacher')
            ->set('lastName', 'Белова')->set('firstName', 'Ольга')->set('middleName', 'Сергеевна')
            ->set('email', 'O.Belova@mail.ru')
            ->call('add')
            ->assertHasNoErrors()
            ->assertSet('adding', false)
            ->assertSet('toast', 'Учитель добавлен, ссылка для входа отправлена');

        $user = User::where('email', 'o.belova@mail.ru')->firstOrFail();
        $this->assertSame(User::ROLE_TUTOR, $user->role);
        $this->assertSame('Белова Ольга Сергеевна', $user->name);
        $this->assertFalse((bool) $user->is_profile_completed);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_add_student_with_password_and_errors(): void
    {
        Notification::fake();
        $admin = $this->user(User::ROLE_ADMIN);
        $this->user(User::ROLE_TUTOR, ['email' => 'm.sokolova@mail.ru']);

        $page = Livewire::actingAs($admin)->test(Users::class)->set('tab', 'students')->call('openAdd')
            ->assertSet('role', 'student')
            ->set('lastName', 'Ким')->set('firstName', 'Павел');

        $page->set('email', '')->call('add')->assertHasErrors('email')->assertSee('Укажите почту');
        $page->set('email', 'pkim.gmail.com')->call('add')->assertHasErrors('email');
        $page->set('email', 'M.Sokolova@mail.ru')->call('add')->assertHasErrors('email')->assertSee('Эта почта уже занята: Соколова Мария');

        $page->set('email', 'p.kim@gmail.com')->set('mode', 'pass')->set('password', 'short')->call('add')->assertHasErrors('password');
        $page->set('password', 'Sokol-2718')->call('add')->assertHasNoErrors()
            ->assertSet('toast', 'Ученик добавлен');

        $user = User::where('email', 'p.kim@gmail.com')->firstOrFail();
        $this->assertSame(User::ROLE_STUDENT, $user->role);
        $this->assertTrue(Hash::check('Sokol-2718', $user->password));
        Notification::assertNotSentTo($user, ResetPassword::class);
    }
}
