<?php

namespace Tests\Feature;

use App\Livewire\Blog\Comments;
use App\Livewire\Blog\LikeButton;
use App\Livewire\Cabinet\Admin\Blog;
use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\User;
use App\Notifications\BlogCommentAdded;
use App\Services\BlogService;
use Database\Seeders\TariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Блог: обсуждения под статьями, лайки, «Популярные» и «Новые». */
class BlogCommentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(TariffSeeder::class);
        Notification::fake();
    }

    private function user(string $role, array $attrs = []): User
    {
        return User::factory()->create(array_merge(['role' => $role, 'username' => $role . uniqid(), 'is_active' => true, 'is_blocked' => false, 'is_profile_completed' => true], $attrs));
    }

    private function article(?User $author = null, array $attrs = []): BlogPost
    {
        return app(BlogService::class)->save(null, array_merge([
            'title' => 'Статья ' . uniqid(),
            'body' => '<p>Текст</p>',
            'author_id' => $author?->id,
            'published_at' => now()->subHour(),
        ], $attrs));
    }

    public function test_guest_reads_and_is_invited_to_log_in(): void
    {
        $post = $this->article();
        $this->get('/blog/' . $post->slug)->assertOk()
            ->assertSee('Обсуждение')
            ->assertSee('Войдите, чтобы участвовать в обсуждении')
            ->assertSee('next=', false);

        Livewire::test(LikeButton::class, ['postId' => $post->id])->call('toggle')->assertRedirect();
        $this->assertSame(0, $post->fresh()->likes_count);
    }

    public function test_comment_reply_edit_and_notifications(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $student = $this->user(User::ROLE_STUDENT, ['name' => 'Аминат Ученица']);
        $other = $this->user(User::ROLE_STUDENT);
        $post = $this->article($teacher);

        Livewire::actingAs($student)->test(Comments::class, ['postId' => $post->id])
            ->call('send')->assertHasErrors('body')
            ->set('body', "Спасибо!\nЕсть вопрос: https://example.com")
            ->call('send')
            ->assertSee('Аминат Ученица')
            ->assertSee('nofollow ugc', false);
        $root = BlogComment::sole();
        $this->assertSame(1, $post->fresh()->comments_count);
        Notification::assertSentTo($teacher, BlogCommentAdded::class, fn ($n) => ! $n->reply);

        // Автор статьи отвечает — ученице уведомление об ответе, отметка «Автор статьи»
        Livewire::actingAs($teacher)->test(Comments::class, ['postId' => $post->id])
            ->call('startReply', $root->id)->set('replyBody', 'Отвечаю')->call('sendReply')
            ->assertSee('Автор статьи');
        $reply = BlogComment::where('parent_id', $root->id)->sole();
        $this->assertSame($student->id, $reply->reply_to_user_id);
        Notification::assertSentTo($student, BlogCommentAdded::class, fn ($n) => $n->reply);

        // Ответ на ответ — в ту же ветку
        Livewire::actingAs($other)->test(Comments::class, ['postId' => $post->id])
            ->call('startReply', $reply->id)->set('replyBody', 'И я')->call('sendReply');
        $this->assertSame(2, BlogComment::where('parent_id', $root->id)->count());

        // Править можно только свое
        Livewire::actingAs($student)->test(Comments::class, ['postId' => $post->id])
            ->call('startEdit', $root->id)->set('editBody', 'Спасибо, исправила')->call('saveEdit')
            ->assertSee('изменено');
        $this->assertNotNull($root->fresh()->edited_at);
        Livewire::actingAs($other)->test(Comments::class, ['postId' => $post->id])
            ->call('startEdit', $root->id)->assertForbidden();
    }

    public function test_delete_keeps_stub_when_thread_has_replies(): void
    {
        $student = $this->user(User::ROLE_STUDENT);
        $other = $this->user(User::ROLE_STUDENT);
        $post = $this->article();
        $svc = app(\App\Services\BlogCommentService::class);
        $root = $svc->add($post, $student, 'Корень');
        $reply = $svc->add($post, $other, 'Ответ', $root);

        Livewire::actingAs($student)->test(Comments::class, ['postId' => $post->id])
            ->call('askDelete', $root->id)->call('delete')
            ->assertSee('Комментарий удален автором')->assertSee('Ответ');
        $this->assertTrue($root->fresh()->isDeleted());
        $this->assertSame(1, $post->fresh()->comments_count);

        // Последний ответ ушел — заглушка тоже
        Livewire::actingAs($other)->test(Comments::class, ['postId' => $post->id])
            ->call('askDelete', $reply->id)->call('delete');
        $this->assertSame(0, BlogComment::count());
        $this->assertSame(0, $post->fresh()->comments_count);

        // Чужое удалить нельзя
        $c = $svc->add($post, $student, 'Еще');
        Livewire::actingAs($other)->test(Comments::class, ['postId' => $post->id])
            ->call('askDelete', $c->id)->call('delete')->assertForbidden();
    }

    public function test_post_author_moderates_and_admin_bans_everywhere(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $troll = $this->user(User::ROLE_STUDENT);
        $admin = $this->user(User::ROLE_ADMIN);
        $post = $this->article($teacher);
        $otherPost = $this->article();
        $svc = app(\App\Services\BlogCommentService::class);
        $c = $svc->add($post, $troll, 'Плохой комментарий');

        // Автор статьи удаляет чужой комментарий — «удален модератором», если есть ответы; запрещает писать в своих статьях
        Livewire::actingAs($teacher)->test(Comments::class, ['postId' => $post->id])
            ->assertSee('Запретить писать')
            ->call('ban', $troll->id)
            ->assertSee('не может писать')
            ->call('askDelete', $c->id)->call('delete');
        $this->assertSame(0, BlogComment::count());
        $this->assertSame('banned', $svc->whyCannotComment($troll, $post));
        $this->assertNull($svc->whyCannotComment($troll, $otherPost), 'запрет автора — только в его статьях');

        // Закрыть обсуждение: писать может только модератор
        Livewire::actingAs($teacher)->test(Comments::class, ['postId' => $post->id])->call('toggleClosed');
        $this->assertTrue($post->fresh()->comments_closed);
        $student = $this->user(User::ROLE_STUDENT);
        Livewire::actingAs($student)->test(Comments::class, ['postId' => $post->id])
            ->assertSee('Обсуждение закрыто')
            ->set('body', 'Можно?')->call('send')->assertHasErrors('body');

        // Админ запрещает писать во всем блоге
        $svc->add($otherPost, $student, 'Привет');
        Livewire::actingAs($admin)->test(Comments::class, ['postId' => $otherPost->id])->call('ban', $student->id);
        $this->assertSame('banned', $svc->whyCannotComment($student, $this->article()));

        // Обычный читатель модерировать не может
        Livewire::actingAs($troll)->test(Comments::class, ['postId' => $otherPost->id])
            ->assertDontSee('Запретить писать')->call('toggleClosed')->assertForbidden();
    }

    public function test_reports_reach_admin(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $a = $this->user(User::ROLE_STUDENT);
        $b = $this->user(User::ROLE_STUDENT);
        $post = $this->article();
        $c = app(\App\Services\BlogCommentService::class)->add($post, $a, 'Спам спам');

        Livewire::actingAs($b)->test(Comments::class, ['postId' => $post->id])
            ->call('startReport', $c->id)->set('reportReason', 'Реклама')->call('sendReport')
            ->assertSee('Жалоба отправлена.')
            ->assertDontSee('>Пожаловаться</button>', false);
        $this->assertSame(1, app(\App\Services\AdminInboxService::class)->counts()['blog']);

        Livewire::actingAs($admin)->test(Blog::class)->set('tab', 'reports')
            ->assertSee('Спам спам')->assertSee('Реклама')
            ->call('dismissReports', $c->id)
            ->assertSee('Жалоб нет');
        $this->assertNotNull(BlogComment::find($c->id));
    }

    public function test_rate_limit(): void
    {
        $student = $this->user(User::ROLE_STUDENT);
        $post = $this->article();
        $c = Livewire::actingAs($student)->test(Comments::class, ['postId' => $post->id]);
        foreach (range(1, 5) as $n) {
            $c->set('body', 'Комментарий ' . $n)->call('send')->assertHasNoErrors();
        }
        $c->set('body', 'Шестой')->call('send')->assertHasErrors('body');
        $this->assertSame(5, BlogComment::count());
    }

    public function test_likes_and_popular_order(): void
    {
        $users = collect(range(1, 3))->map(fn () => $this->user(User::ROLE_STUDENT));
        $fresh = $this->article(null, ['title' => 'Свежая без реакций', 'published_at' => now()->subHour()]);
        $liked = $this->article(null, ['title' => 'Неделю назад, но обсуждаемая', 'published_at' => now()->subDays(7)]);
        $old = $this->article(null, ['title' => 'Старая', 'published_at' => now()->subDays(60)]);

        Livewire::actingAs($users[0])->test(LikeButton::class, ['postId' => $liked->id])
            ->call('toggle')->assertSee('1')
            ->call('toggle');
        $this->assertSame(0, $liked->fresh()->likes_count);

        foreach ($users as $u) {
            app(BlogService::class)->toggleLike($liked->fresh(), $u);
            app(\App\Services\BlogCommentService::class)->add($liked->fresh(), $u, 'Интересно');
        }
        $liked->refresh();
        $this->assertSame(3, $liked->likes_count);
        $this->assertSame(3, $liked->comments_count);
        // (3 + 6 + 1) / 9^1.5 ≈ 0.37 больше, чем (0 + 0 + 1) / 2^1.5 ≈ 0.35
        $this->assertGreaterThan($fresh->fresh()->hot_score, $liked->hot_score);
        $this->assertLessThan($fresh->fresh()->hot_score, $old->fresh()->hot_score);

        $this->get('/blog')->assertOk()->assertSeeInOrder(['Популярные', 'Новые', 'Неделю назад, но обсуждаемая', 'Свежая без реакций', 'Старая']);
        $this->get('/blog?sort=new')->assertOk()->assertSeeInOrder(['Свежая без реакций', 'Неделю назад, но обсуждаемая', 'Старая'])
            ->assertSee('<link rel="canonical" href="' . url('/blog') . '">', false);

        // Вес падает с возрастом — пересчет по расписанию
        $before = $liked->hot_score;
        $this->travel(5)->days();
        $this->artisan('blog:hot')->assertSuccessful();
        $this->assertLessThan($before, $liked->fresh()->hot_score);
    }

    public function test_site_header_shows_user_menu_when_logged_in(): void
    {
        $this->get('/blog')->assertOk()->assertSee('>Войти</a>', false)->assertDontSee('Меню профиля');

        $teacher = $this->user(User::ROLE_TUTOR, ['name' => 'Зарема Хашагульгова']);
        $this->actingAs($teacher)->get('/blog')->assertOk()
            ->assertDontSee('>Войти</a>', false)
            ->assertSee('Меню профиля')
            ->assertSee('Зарема Хашагульгова')
            ->assertSee('Учитель')
            ->assertSee(route('cabinet.teacher.blog'), false)
            ->assertSee('Мои статьи')
            ->assertSee('Выйти')
            ->assertSee('Перейти в кабинет');

        $student = $this->user(User::ROLE_STUDENT);
        $this->actingAs($student)->get('/blog')->assertOk()->assertSee('Ученик')->assertDontSee('Мои статьи');
    }

    public function test_comment_actions_menu_and_emoji(): void
    {
        $student = $this->user(User::ROLE_STUDENT);
        $post = $this->article();
        app(\App\Services\BlogCommentService::class)->add($post, $student, 'Привет 👋');

        Livewire::actingAs($student)->test(Comments::class, ['postId' => $post->id])
            ->assertSee('Привет 👋')
            ->assertSee('Действия с комментарием')
            ->assertSee('blog-comment-reply', false)
            ->assertSee('Эмодзи')
            ->set('body', 'Супер 🔥🎉')->call('send')
            ->assertSee('Супер 🔥🎉');
    }

    public function test_hot_score_formula(): void
    {
        $now = now();
        $this->assertEqualsWithDelta(1 / (2 ** 1.5), BlogService::hotScore(0, 0, $now), 1e-6);
        $this->assertGreaterThan(BlogService::hotScore(0, 1, $now), BlogService::hotScore(0, 2, $now));
        $this->assertGreaterThan(BlogService::hotScore(2, 0, $now), BlogService::hotScore(0, 2, $now), 'комментарий весит больше лайка');
        $this->assertGreaterThan(BlogService::hotScore(5, 5, $now->copy()->subDays(10)), BlogService::hotScore(5, 5, $now->copy()->subDay()));
        $this->assertSame(0.0, BlogService::hotScore(10, 10, null));
    }
}
