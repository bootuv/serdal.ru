<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\Cabinet\Tour;
use App\Models\HelpCategory;
use App\Models\User;
use App\Services\CabinetTourService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Тур по кабинету учителя и ученика: сам открывается один раз, вернуться — «Тур по кабинету» в меню. */
class TourTest extends TestCase
{
    use RefreshDatabase, TeacherLessonFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        $this->fixNow();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_teacher_gets_tour_once_and_can_return_to_it_from_menu(): void
    {
        $teacher = $this->teacher();

        $this->actingAs($teacher)->get(route('cabinet.teacher.today'))
            ->assertOk()
            ->assertSee('cabinetTour', false)
            ->assertSee('Тур по кабинету')
            ->assertSee(route('cabinet.teacher.today', ['tour' => 1]), false)
            ->assertSee('data-tour="nav-schedule"', false)
            ->assertSee('data-tour="tab-schedule"', false)
            ->assertSee('data-tour="more"', false)
            ->assertSee('data-tour="help-menu"', false)
            ->assertSee('База знаний');

        Livewire::actingAs($teacher)->test(Tour::class)
            ->assertSet('auto', true)
            ->call('seen')
            ->assertSet('auto', false);

        $this->assertNotNull($teacher->refresh()->tour_seen_at);
        Livewire::actingAs($teacher)->test(Tour::class)->assertSet('auto', false);

        // Тур по-прежнему доступен из меню
        $this->actingAs($teacher)->get(route('cabinet.teacher.today'))
            ->assertSee('Тур по кабинету')
            ->assertSee('cabinetTour', false);
    }

    public function test_teacher_steps_follow_the_menu_and_link_to_help(): void
    {
        $teacher = $this->teacher();
        HelpCategory::create(['audience' => HelpCategory::AUDIENCE_TUTOR, 'name' => 'Расписание', 'is_published' => true]);

        $steps = app(CabinetTourService::class)->steps($teacher);
        $titles = array_column($steps, 'title');

        $this->assertSame('Покажем кабинет', $titles[0]);
        $this->assertSame('Вот и всё', end($titles));

        // Как меню в сайдбаре: пункт меню, следом — главное на его экране
        $this->assertSame([
            'Сегодня', 'Что сделать сегодня', 'Расписание', 'Запланировать занятие', 'Сообщения', 'Чат поддержки',
            'Ученики', 'Пригласить ученика', 'Задания', 'Выдать задание', 'Материалы', 'Загрузить',
            'Записи', 'Записи занятий', 'Отзывы', 'Отзывы учеников', 'Мои статьи', 'Написать статью',
        ], array_slice($titles, 1, 18));

        $menu = $steps[3];
        $this->assertNull($menu['url']);
        $this->assertSame(['nav-schedule', 'tab-schedule'], $menu['targets']);

        $action = $steps[4];
        $this->assertSame(route('cabinet.teacher.schedule'), $action['url']);
        $this->assertSame(['actions'], $action['targets']);
        $this->assertStringContainsString('/help/tutors/', (string) $action['help']);

        // Раздел не на нижней панели телефона — подсвечиваем «Ещё»
        $this->assertSame(['nav-materials', 'more'], $steps[11]['targets']);

        // Потом общее: уведомления, поддержка, профиль, сам тур
        $rest = array_slice($titles, 19, -1);
        $this->assertSame('Уведомления', $rest[0]);
        $this->assertSame(['Помощь', 'Профиль и тариф'], array_slice($rest, -2));
        $help = $steps[array_search('Помощь', $titles, true)];
        $this->assertSame('help', $help['reveal']);
        $this->assertSame(['help-menu', 'help', 'more'], $help['targets']);
    }

    public function test_student_gets_own_steps(): void
    {
        $student = $this->user(User::ROLE_STUDENT);

        $titles = array_column(app(CabinetTourService::class)->steps($student), 'title');
        $this->assertSame([
            'Главная', 'Ближайшее занятие', 'Расписание', 'Google Календарь', 'Задания', 'Сдать работу', 'Сообщения', 'Чат поддержки',
            'Материалы', 'Файлы от учителя', 'Записи', 'Записи занятий', 'Оплата', 'Сообщить об оплате',
            'Уведомления', 'Помощь', 'Профиль',
        ], array_slice($titles, 1, -1));

        $this->actingAs($student)->get(route('cabinet.student.home'))
            ->assertOk()
            ->assertSee('cabinetTour', false)
            ->assertSee('Тур по кабинету')
            ->assertSee(route('cabinet.student.home', ['tour' => 1]), false);
    }

    public function test_no_tour_before_onboarding_and_for_admin(): void
    {
        $newTeacher = $this->user(User::ROLE_TUTOR, ['is_profile_completed' => false]);
        $this->assertFalse(CabinetTourService::available($newTeacher));
        Livewire::actingAs($newTeacher)->test(Tour::class)->assertSet('auto', false)->assertDontSee('cabinetTour', false);

        $admin = $this->user(User::ROLE_ADMIN);
        $this->assertFalse(CabinetTourService::available($admin));
        $this->actingAs($admin)->get(route('cabinet.admin.today'))
            ->assertOk()
            ->assertDontSee('Тур по кабинету')
            ->assertDontSee('cabinetTour', false);
    }
}
