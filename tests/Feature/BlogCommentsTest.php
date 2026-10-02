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
            ->assertSee(route('blog.author', $teacher->username), false)
            ->assertSee('Мой блог')->assertSee('Личный кабинет')
            ->assertSee('Выйти')
            ->assertSee('Перейти в кабинет');

        $student = $this->user(User::ROLE_STUDENT);
        $this->actingAs($student)->get('/blog')->assertOk()->assertSee('Ученик')->assertSee('Личный кабинет')->assertDontSee('>Мой блог</a>', false);

        // «Мой блог» учителя без статей открывается ему самому — с «Написать статью»; чужим — нет такой страницы
        $this->actingAs($teacher)->get('/blog/author/' . $teacher->username)->assertOk()
            ->assertSee('Написать статью')->assertSee(route('cabinet.teacher.blog-article', ['post' => 'new']), false)
            ->assertSee('У вас пока нет опубликованных статей')
            ->assertSee('noindex, nofollow', false);
        $this->actingAs($student)->get('/blog/author/' . $teacher->username)->assertNotFound();
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

    public function test_follow_authors_feed_and_notifications(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR, ['name' => 'Евлоева Мадина Ахмедовна']);
        $other = $this->user(User::ROLE_TUTOR);
        $reader = $this->user(User::ROLE_STUDENT);
        $colleague = $this->user(User::ROLE_TUTOR);
        $mineStudent = $this->user(User::ROLE_STUDENT);
        $teacher->students()->attach($mineStudent->id);

        $old = $this->article($teacher, ['title' => 'Старая статья Мадины', 'published_at' => now()->subDays(3)]);
        $this->article($other, ['title' => 'Чужая статья', 'published_at' => now()->subDay()]);

        // Гостя кнопка ведет на вход
        Livewire::test(\App\Livewire\Blog\FollowButton::class, ['authorId' => $teacher->id, 'returnUrl' => '/blog'])->call('toggle')->assertRedirect();

        // Без подписок — «Моей ленты» нет, по умолчанию «Популярные»
        $this->actingAs($reader)->get('/blog')->assertOk()->assertDontSee('Моя лента');

        // Подписка — со страницы автора; гостя ведем на вход; на себя подписаться нельзя
        Livewire::actingAs($teacher)->test(\App\Livewire\Blog\FollowButton::class, ['authorId' => $teacher->id])->assertDontSee('Подписаться');
        Livewire::actingAs($reader)->test(\App\Livewire\Blog\FollowButton::class, ['authorId' => $teacher->id, 'showCount' => true])
            ->assertSee('Подписаться')->assertSee('0 подписчиков')
            ->call('toggle')->assertSee('Вы подписаны')->assertSee('1 подписчик');
        Livewire::actingAs($colleague)->test(\App\Livewire\Blog\FollowButton::class, ['authorId' => $teacher->id])->call('toggle');
        Livewire::actingAs($mineStudent)->test(\App\Livewire\Blog\FollowButton::class, ['authorId' => $teacher->id])->call('toggle');
        auth()->logout();
        $this->get('/blog/author/' . $teacher->username)->assertOk()->assertSee('3 подписчика');

        // «Моя лента» первая и по умолчанию: только статьи авторов из подписок
        $this->actingAs($reader)->get('/blog')->assertOk()
            ->assertSeeInOrder(['Моя лента', 'Популярные', 'Новые'])
            ->assertSee('Старая статья Мадины')->assertDontSee('Чужая статья');
        $this->actingAs($reader)->get('/blog?sort=popular')->assertOk()->assertSee('Чужая статья');

        // Новая статья — подписчикам «в вашей ленте», ученику-подписчику — одно уведомление как ученику
        $new = $this->article($teacher, ['title' => 'Новая статья Мадины']);
        Notification::assertSentTo($reader, \App\Notifications\TeacherPublishedBlogPost::class, fn ($n) => $n->follower && $n->post->is($new));
        Notification::assertSentTo($colleague, \App\Notifications\TeacherPublishedBlogPost::class, fn ($n) => $n->follower);
        $this->assertCount(1, Notification::sent($mineStudent, \App\Notifications\TeacherPublishedBlogPost::class)->filter(fn ($n) => $n->post->is($new)));
        Notification::assertSentTo($mineStudent, \App\Notifications\TeacherPublishedBlogPost::class, fn ($n) => ! $n->follower);
        Notification::assertNotSentTo($teacher, \App\Notifications\TeacherPublishedBlogPost::class);

        // В кабинете учителя видно подписчиков
        $this->actingAs($teacher)->get('/cabinet/teacher/blog')->assertOk()->assertSee('3 подписчика');

        // Личный блок в колонке блога: у учителя — статьи, подписчики, «Написать статью»; у читателя — его подписки
        $this->actingAs($teacher)->get('/blog')->assertOk()
            ->assertSee('Написать статью')->assertSee('<b>3</b><span>подписчика</span>', false)->assertSee(route('cabinet.teacher.blog-article', ['post' => 'new']), false);
        $this->actingAs($reader)->get('/blog')->assertOk()
            ->assertSee('<b>1</b><span>подписка</span>', false)->assertDontSee('<span>подписчик', false)->assertDontSee('Написать статью');

        // Отписка — лента пропадает
        Livewire::actingAs($reader)->test(\App\Livewire\Blog\FollowButton::class, ['authorId' => $teacher->id])->call('toggle')->assertSee('Подписаться');
        $this->actingAs($reader)->get('/blog')->assertDontSee('Моя лента');
    }

    public function test_author_page_sidebar_and_feed_default(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $other = $this->user(User::ROLE_TUTOR, ['name' => 'Другой Автор']);
        $this->article($teacher, ['title' => 'Про ЕГЭ', 'tags' => ['ЕГЭ'], 'published_at' => now()->subDays(30)]);
        $this->article($other, ['title' => 'Про химию', 'tags' => ['химия']]);

        // На странице автора — его темы и без чужих авторов
        $this->get('/blog/author/' . $teacher->username)->assertOk()
            ->assertSee('Темы автора')->assertSee('/blog/tag/ege', false)->assertDontSee('/blog/tag/himiya', false)
            ->assertDontSee('Другой Автор');
        $this->get('/blog')->assertSee('Популярные темы')->assertSee('Другой Автор');

        // Подписан, но у автора давно ничего — по умолчанию «Популярные», «Моя лента» — вкладкой
        $reader = $this->user(User::ROLE_STUDENT);
        app(BlogService::class)->toggleFollow($teacher, $reader);
        $this->actingAs($reader)->get('/blog')->assertOk()
            ->assertSee('Моя лента')->assertSee('Про химию')
            ->assertSee('href="' . url('/blog') . '?sort=feed"', false);

        // Вышла свежая статья — лента открывается сама
        $this->article($teacher, ['title' => 'Свежая про ОГЭ']);
        $this->actingAs($reader)->get('/blog')->assertOk()->assertSee('Свежая про ОГЭ')->assertDontSee('Про химию');
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

    public function test_comment_notifications_reach_article_author_and_whoever_was_answered(): void
    {
        $teacher = User::factory()->create(['role' => User::ROLE_TUTOR, 'username' => 't' . uniqid(), 'is_active' => true, 'is_blocked' => false]);
        $colleague = User::factory()->create(['role' => User::ROLE_TUTOR, 'username' => 'c' . uniqid(), 'is_active' => true, 'is_blocked' => false]);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT, 'username' => 's' . uniqid(), 'is_active' => true, 'is_blocked' => false]);
        $other = User::factory()->create(['role' => User::ROLE_STUDENT, 'username' => 'o' . uniqid(), 'is_active' => true, 'is_blocked' => false]);
        $post = app(BlogService::class)->save(null, ['title' => 'Статья учителя', 'body' => '<p>Текст</p>', 'author_id' => $teacher->id, 'published_at' => now()->subHour()]);
        $service = app(\App\Services\BlogCommentService::class);

        // Ученик комментирует — учителю «Новый комментарий к вашей статье»
        $root = $service->add($post, $student, 'Вопрос к автору');
        Notification::assertSentTo($teacher, BlogCommentAdded::class, fn ($n) => ! $n->reply && $n->comment->is($root));
        $this->assertSame('Новый комментарий к вашей статье', (new BlogCommentAdded($root, false))->toDatabase($teacher)['title']);

        // Другой ученик отвечает ученику — ученику «Ответ на ваш комментарий», учителю — о новом комментарии под статьей
        $reply = $service->add($post, $other, 'Я тоже хотел спросить', $root);
        Notification::assertSentTo($student, BlogCommentAdded::class, fn ($n) => $n->reply && $n->comment->is($reply));
        Notification::assertSentTo($teacher, BlogCommentAdded::class, fn ($n) => ! $n->reply && $n->comment->is($reply));

        // Учитель пишет под чужой статьей, ему отвечают — учителю «Ответ на ваш комментарий»
        $foreign = app(BlogService::class)->save(null, ['title' => 'Статья коллеги', 'body' => '<p>Текст</p>', 'author_id' => $colleague->id, 'published_at' => now()->subHour()]);
        $mine = $service->add($foreign, $teacher, 'Согласен с коллегой');
        $answer = $service->add($foreign, $student, 'А как у вас?', $mine);
        Notification::assertSentTo($teacher, BlogCommentAdded::class, fn ($n) => $n->reply && $n->comment->is($answer));

        // Себе — ничего: автор отвечает в своей статье, уведомление только ученику
        $own = $service->add($post, $teacher, 'Отвечаю', $root);
        Notification::assertNotSentTo($teacher, BlogCommentAdded::class, fn ($n) => $n->comment->is($own));
        Notification::assertSentTo($student, BlogCommentAdded::class, fn ($n) => $n->reply && $n->comment->is($own));
    }
}
