<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\EnsureCabinetRole;
use App\Livewire\Cabinet\Admin\Reviews;
use App\Models\Review;
use App\Models\User;
use App\Notifications\ReviewReportDecided;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Админка → Отзывы (/cabinet/admin/reviews): вкладки, окно отзыва, решения, письмо учителю, новый отзыв. */
class ReviewsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $maria;

    private User $ivan;

    private Review $reported;

    private Review $visible;

    private Review $hidden;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();

        $this->admin = $this->user(User::ROLE_ADMIN);
        $this->maria = $this->user(User::ROLE_TUTOR, ['first_name' => 'Мария', 'last_name' => 'Соколова']);
        $this->ivan = $this->user(User::ROLE_STUDENT, ['first_name' => 'Иван', 'last_name' => 'Петров']);
        $alina = $this->user(User::ROLE_STUDENT, ['first_name' => 'Алина', 'last_name' => 'Смирнова']);
        $artem = $this->user(User::ROLE_STUDENT, ['first_name' => 'Артём', 'last_name' => 'Козлов']);

        $this->reported = Review::create([
            'teacher_id' => $this->maria->id, 'user_id' => $this->ivan->id, 'rating' => 2,
            'text' => 'Учитель хамит, когда задаёшь вопросы.', 'is_reported' => true,
            'report_reason' => 'rude', 'report_note' => 'Я ни разу не грубила', 'reported_at' => now(),
        ]);
        $this->visible = Review::create(['teacher_id' => $this->maria->id, 'user_id' => $alina->id, 'rating' => 5, 'text' => 'Перестала бояться говорить.']);
        $this->hidden = Review::create([
            'teacher_id' => $this->maria->id, 'user_id' => $artem->id, 'rating' => 1, 'text' => 'Готовлю дешевле, пишите в личку.',
            'is_rejected' => true, 'hidden_at' => now()->subDays(3), 'report_reason' => 'ads',
        ]);
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

    public function test_access(): void
    {
        $this->get('/cabinet/admin/reviews')->assertRedirect(route('login'));
        $this->actingAs($this->maria)->get('/cabinet/admin/reviews')->assertRedirect(EnsureCabinetRole::homeFor($this->maria));
        $this->actingAs($this->ivan)->get('/cabinet/admin/reviews')->assertRedirect(route('cabinet.student.home'));

        // Первая вкладка — «Все отзывы», «Жалобы» — последняя
        $this->actingAs($this->admin)->get('/cabinet/admin/reviews')
            ->assertOk()
            ->assertSeeInOrder(['Отзывы', 'Новый отзыв', 'Все отзывы', 'Скрытые', 'О платформе', 'Жалобы'])
            ->assertSee('Перестала бояться говорить.')
            ->assertDontSee('Готовлю дешевле');

        $this->actingAs($this->admin)->get('/cabinet/admin/reviews?tab=reports')
            ->assertOk()
            ->assertSee('Петров Иван → Соколова Мария')
            ->assertSee('Оскорбления или грубость')
            ->assertDontSee('Перестала бояться говорить.');
    }

    public function test_tabs_and_search(): void
    {
        Livewire::actingAs($this->admin)->test(Reviews::class)
            ->set('tab', 'all')
            ->assertSee('Перестала бояться говорить.')
            ->assertSee('Учитель хамит')
            ->assertDontSee('Готовлю дешевле')
            ->set('q', 'Алина')
            ->assertSee('Перестала бояться говорить.')
            ->assertDontSee('Учитель хамит')
            ->set('q', 'Никто')
            ->assertSee('Ничего не нашли — проверьте имя ученика или учителя')
            ->set('q', '')
            ->set('tab', 'hidden')
            ->assertSee('Готовлю дешевле')
            ->assertSee('жалоба: реклама или посторонние ссылки');

        Review::query()->update(['is_reported' => false]);
        Livewire::actingAs($this->admin)->test(Reviews::class)->set('tab', 'reports')->assertSee('Жалоб нет — новые появятся здесь');
    }

    public function test_keep_review_notifies_teacher(): void
    {
        Livewire::actingAs($this->admin)->test(Reviews::class)
            ->call('open', $this->reported->id)
            ->assertSee('Жалоба учителя')
            ->assertSee('«Я ни разу не грубила»')
            ->assertSee('Сообщить учителю о решении')
            ->call('toKeep')
            ->assertSee('Жалоба будет снята')
            ->assertSee('Мария получит письмо о решении.')
            ->call('keep')
            ->assertSee('Жалоба снята, отзыв остаётся — Мария получит письмо');

        $this->reported->refresh();
        $this->assertFalse((bool) $this->reported->is_reported);
        $this->assertFalse((bool) $this->reported->is_rejected);
        Notification::assertSentTo($this->maria, ReviewReportDecided::class, fn ($n) => $n->decision === ReviewReportDecided::KEPT);
    }

    public function test_hide_with_notification_and_mail_channel(): void
    {
        Livewire::actingAs($this->admin)->test(Reviews::class)
            ->call('open', $this->reported->id)
            ->call('toHide')
            ->assertSee('Отзыв пропадёт со страницы учителя Соколова Мария')
            ->call('hide')
            ->assertSee('Отзыв скрыт — Мария получит письмо')
            ->assertSet('undo', null);

        $this->reported->refresh();
        $this->assertTrue((bool) $this->reported->is_rejected);
        $this->assertNotNull($this->reported->hidden_at);
        $this->assertSame('rude', $this->reported->report_reason);
        Notification::assertSentTo($this->maria, ReviewReportDecided::class, function ($n, $channels) {
            return $n->decision === ReviewReportDecided::HIDDEN && in_array('mail', $channels, true);
        });
    }

    public function test_hide_without_notification_can_be_undone(): void
    {
        $page = Livewire::actingAs($this->admin)->test(Reviews::class)
            ->call('open', $this->reported->id)
            ->set('notify', false)
            ->call('toHide')
            ->assertSee('Мария не получит письмо о решении.')
            ->call('hide')
            ->assertSee('Отзыв скрыт')
            ->assertSee('Отменить');

        Notification::assertNothingSent();
        $this->assertTrue((bool) $this->reported->fresh()->is_rejected);

        $page->call('undoHide')->assertSet('toast', null);
        $this->reported->refresh();
        $this->assertFalse((bool) $this->reported->is_rejected);
        $this->assertTrue((bool) $this->reported->is_reported);
    }

    public function test_hide_visible_and_restore_hidden(): void
    {
        Livewire::actingAs($this->admin)->test(Reviews::class)
            ->set('tab', 'all')
            ->call('open', $this->visible->id)
            ->assertDontSee('Сообщить учителю о решении')
            ->call('toHide')
            ->call('hide');
        $this->assertTrue((bool) $this->visible->fresh()->is_rejected);

        Livewire::actingAs($this->admin)->test(Reviews::class)
            ->set('tab', 'hidden')
            ->call('open', $this->hidden->id)
            ->assertSee('Была жалоба учителя')
            ->assertSee('не может оставить учителю новый отзыв')
            ->call('restore')
            ->assertSee('Отзыв снова виден на странице учителя Соколова Мария');
        $this->assertFalse((bool) $this->hidden->fresh()->is_rejected);
        $this->assertNull($this->hidden->fresh()->hidden_at);

        Notification::assertNothingSent();
    }

    public function test_keep_requires_complaint(): void
    {
        Livewire::actingAs($this->admin)->test(Reviews::class)
            ->set('tab', 'all')
            ->call('open', $this->visible->id)
            ->call('toKeep')
            ->assertNotFound();
    }

    public function test_share_visible_review(): void
    {
        $page = preg_replace('#^https?://#', '', route('tutors.show', $this->maria->username));

        Livewire::actingAs($this->admin)->test(Reviews::class)
            ->set('tab', 'all')
            ->call('open', $this->visible->id)
            ->assertSee('Поделиться')
            ->call('toShare')
            ->assertSee('Поделиться отзывом')
            ->assertSee('Картинка для сторис')
            ->assertSee(route('reviews.share-card', $this->visible), false)
            ->assertSee('Все отзывы: ' . $page)
            ->call('back')
            ->assertSet('step', 'view');

        $this->actingAs($this->admin)->get(route('reviews.share-card', $this->visible))
            ->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_share_platform_review_only_when_published(): void
    {
        $platform = Review::create([
            'teacher_id' => null, 'user_id' => $this->maria->id, 'rating' => 5, 'text' => 'Удобно вести занятия.',
            'show_on_site' => true, 'approved_at' => now(),
        ]);

        Livewire::actingAs($this->admin)->test(Reviews::class)
            ->set('tab', 'platform')
            ->call('open', $platform->id)
            ->call('toShare')
            ->assertSee('Все отзывы: ' . preg_replace('#^https?://#', '', route('reviews')));

        $platform->update(['approved_at' => null]);
        Livewire::actingAs($this->admin)->test(Reviews::class)
            ->set('tab', 'platform')
            ->call('open', $platform->id)
            ->call('toShare')
            ->assertNotFound();
    }

    public function test_share_needs_visible_review(): void
    {
        foreach ([$this->reported, $this->hidden] as $review) {
            Livewire::actingAs($this->admin)->test(Reviews::class)
                ->set('tab', 'all')
                ->call('open', $review->id)
                ->call('toShare')
                ->assertNotFound();
        }
    }

    public function test_notification_texts(): void
    {
        $n = new ReviewReportDecided($this->reported, ReviewReportDecided::HIDDEN);
        $data = $n->toDatabase($this->maria);
        $this->assertSame('Отзыв скрыт', $data['title']);
        $this->assertStringContainsString('Петров Иван', $data['body']);
        $this->assertStringContainsString('Отзыв скрыт', $n->toMail($this->maria)->subject);
    }

    public function test_letter_goes_to_teacher_email(): void
    {
        Livewire::actingAs($this->admin)->test(Reviews::class)
            ->call('open', $this->reported->id)->call('toKeep')->call('keep');

        // Учитель загружается целиком — письму нужна почта
        Notification::assertSentTo($this->maria, ReviewReportDecided::class, function ($n, $channels, $notifiable) {
            return $notifiable->email === $this->maria->email && $notifiable->routeNotificationFor('mail') === $this->maria->email;
        });
    }

    public function test_admin_creates_review(): void
    {
        $olga = $this->user(User::ROLE_STUDENT, ['first_name' => 'Ольга', 'last_name' => 'Белова']);

        Livewire::actingAs($this->admin)->test(Reviews::class)
            ->set('tab', 'hidden')
            ->call('toCreate')
            ->assertSee('Новый отзыв')
            ->call('create')
            ->assertHasErrors(['newStudentId', 'newTeacherId', 'newText'])
            // Учитель вместо ученика не подходит
            ->set('newStudentId', (string) $this->maria->id)
            ->set('newTeacherId', (string) $this->maria->id)
            ->set('newText', 'Отличный учитель')
            ->call('create')
            ->assertHasErrors(['newStudentId'])
            // У Ивана уже есть отзыв о Марии
            ->set('newStudentId', (string) $this->ivan->id)
            ->call('create')
            ->assertHasErrors(['newTeacherId'])
            ->assertSee('У ученика уже есть отзыв об этом учителе')
            ->set('newStudentId', (string) $olga->id)
            ->set('newRating', 4)
            ->call('create')
            ->assertHasNoErrors()
            ->assertSet('step', '')
            ->assertSet('tab', 'all')
            ->assertSee('Отзыв добавлен на страницу учителя Соколова Мария')
            ->assertSee('Отличный учитель');

        $review = Review::where('user_id', $olga->id)->where('teacher_id', $this->maria->id)->first();
        $this->assertNotNull($review);
        $this->assertSame(4, (int) $review->rating);
        $this->assertFalse((bool) $review->is_rejected);
        Notification::assertNothingSent();
    }
}
