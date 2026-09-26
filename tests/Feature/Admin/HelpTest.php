<?php

namespace Tests\Feature\Admin;

use App\Livewire\Cabinet\Admin\Help;
use App\Models\HelpArticle;
use App\Models\HelpCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Админка → База знаний (/cabinet/admin/help): категории, порядок, черновики, публичная справка с иконками. */
class HelpTest extends TestCase
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

    private function category(array $attrs = []): HelpCategory
    {
        return HelpCategory::create(array_merge(['audience' => 'student', 'name' => 'Занятия', 'icon' => 'video', 'sort_order' => 1], $attrs));
    }

    private function article(HelpCategory $c, array $attrs = []): HelpArticle
    {
        return HelpArticle::create(array_merge(['help_category_id' => $c->id, 'title' => 'Как войти в класс', 'sort_order' => 1, 'is_published' => true], $attrs));
    }

    public function test_access(): void
    {
        $this->get(route('cabinet.admin.help'))->assertRedirect(route('login'));
        $this->actingAs($this->user(User::ROLE_TUTOR))->get(route('cabinet.admin.help'))->assertRedirect(route('cabinet.teacher.today'));
        $this->actingAs($this->user(User::ROLE_STUDENT))->get(route('cabinet.admin.help'))->assertRedirect(route('cabinet.student.home'));
        $this->actingAs($this->user(User::ROLE_ADMIN))->get(route('cabinet.admin.help'))
            ->assertOk()->assertSee('База знаний')->assertSee('Новая статья')->assertSee('Черновики');
    }

    public function test_groups_tabs_drafts_and_search(): void
    {
        $lessons = $this->category();
        $pay = $this->category(['name' => 'Оплата', 'icon' => 'wallet', 'sort_order' => 2, 'is_published' => false]);
        $tutors = $this->category(['audience' => 'tutor', 'name' => 'Первые шаги']);
        $this->article($lessons, ['views_count' => 2480, 'video_url' => 'https://youtu.be/abcdefgh']);
        $this->article($lessons, ['title' => 'Не работает микрофон', 'sort_order' => 2]);
        $this->article($pay, ['title' => 'Где найти чек', 'is_published' => false]);
        $this->article($tutors, ['title' => 'Как пригласить ученика']);

        Livewire::actingAs($this->user(User::ROLE_ADMIN))->test(Help::class)
            ->assertSee('4 статьи в 3 категориях')
            ->assertSee('Как войти в класс')
            ->assertSee('2 480 просмотров')
            ->assertSee('с видео')
            ->assertSee('Скрыта')
            ->assertDontSee('Как пригласить ученика')
            // Черновики — из обоих разделов
            ->assertSee('Где найти чек')
            ->assertSee('Для учеников · Оплата · изменена сегодня')
            ->set('q', 'микро')
            ->assertSee('Не работает микрофон')
            ->assertDontSee('Как войти в класс')
            ->set('q', 'нет такого')
            ->assertSee('Ничего не нашлось')
            ->set('q', '')
            ->set('tab', 'tutors')
            ->assertSee('Как пригласить ученика');
    }

    public function test_publish_draft(): void
    {
        $a = $this->article($this->category(), ['is_published' => false]);

        Livewire::actingAs($this->user(User::ROLE_ADMIN))->test(Help::class)
            ->call('publish', $a->id)
            ->assertDispatched('toast', message: 'Статья опубликована')
            ->assertSee('Черновиков нет');

        $this->assertTrue($a->fresh()->is_published);
    }

    public function test_create_and_edit_category_with_line_icon(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        Livewire::actingAs($admin)->test(Help::class)
            ->call('newCategory')
            ->assertSee('Новая категория')
            ->call('saveCategory')
            ->assertHasErrors(['catName' => 'required'])
            ->set('catName', 'Поддержка')
            ->set('catAudience', 'tutor')
            ->call('pickIcon', 'help')
            ->call('pickIcon', 'не-иконка')
            ->set('catShow', false)
            ->call('saveCategory')
            ->assertHasNoErrors()
            ->assertSet('tab', 'tutors')
            ->assertDispatched('toast', message: 'Категория создана');

        $c = HelpCategory::firstWhere('name', 'Поддержка');
        $this->assertSame('tutor', $c->audience);
        $this->assertSame('help', $c->icon);
        $this->assertFalse($c->is_published);
        $this->assertNotEmpty($c->slug);

        Livewire::actingAs($admin)->test(Help::class, ['tab' => 'tutors'])
            ->call('editCategory', $c->id)
            ->assertSet('catName', 'Поддержка')
            ->set('catName', 'Помощь')
            ->set('catShow', true)
            ->call('saveCategory')
            ->assertDispatched('toast', message: 'Категория сохранена');

        $this->assertSame('Помощь', $c->fresh()->name);
        $this->assertTrue($c->fresh()->is_published);
    }

    public function test_hide_and_delete_category_with_articles(): void
    {
        Storage::disk('s3')->put('help-videos/a.mp4', 'x');
        $c = $this->category();
        $a = $this->article($c, ['video_file' => 'help-videos/a.mp4']);
        $empty = $this->category(['name' => 'Пустая', 'sort_order' => 2]);

        Livewire::actingAs($this->user(User::ROLE_ADMIN))->test(Help::class)
            ->call('toggleCategory', $c->id)
            ->assertDispatched('toast', message: 'Категория скрыта с сайта')
            ->call('toggleCategory', $c->id)
            ->call('askDeleteCategory', $c->id)
            ->assertSee('Удалить категорию?')
            ->assertSee('Вместе с категорией удалятся 1 статья. Вернуть их нельзя.')
            ->assertSee('Скройте категорию с сайта')
            ->assertSee('Удалить со статьями')
            ->call('hideInstead')
            ->assertSet('deleteId', null)
            ->call('askDeleteCategory', $empty->id)
            ->assertSee('В категории нет статей')
            ->call('deleteCategory')
            ->call('askDeleteCategory', $c->id)
            ->call('deleteCategory')
            ->assertDispatched('toast', message: 'Категория удалена');

        $this->assertDatabaseCount('help_categories', 0);
        $this->assertDatabaseMissing('help_articles', ['id' => $a->id]);
        Storage::disk('s3')->assertMissing('help-videos/a.mp4');
    }

    public function test_reorder_categories_and_articles_by_drag(): void
    {
        $c1 = $this->category(['name' => 'Первая', 'sort_order' => 1]);
        $c2 = $this->category(['name' => 'Вторая', 'sort_order' => 2]);
        $c3 = $this->category(['name' => 'Третья', 'sort_order' => 3]);
        $a1 = $this->article($c1, ['title' => 'А1', 'sort_order' => 1]);
        $a2 = $this->article($c1, ['title' => 'А2', 'sort_order' => 2]);
        $b1 = $this->article($c2, ['title' => 'Б1', 'sort_order' => 1]);

        $test = Livewire::actingAs($this->user(User::ROLE_ADMIN))->test(Help::class)
            ->call('moveCategory', $c3->id, $c1->id, true)
            ->assertDispatched('toast', message: 'Порядок сохранён');
        $this->assertSame([$c3->id, $c1->id, $c2->id], HelpCategory::orderBy('sort_order')->pluck('id')->all());

        // Статья к соседней статье
        $test->call('moveArticle', $a1->id, $a2->id, false);
        $this->assertSame([$a2->id, $a1->id], HelpArticle::where('help_category_id', $c1->id)->orderBy('sort_order')->pluck('id')->all());

        // Статья в другую категорию, перед статьёй
        $test->call('moveArticle', $a1->id, $b1->id, true);
        $this->assertSame([$a1->id, $b1->id], HelpArticle::where('help_category_id', $c2->id)->orderBy('sort_order')->pluck('id')->all());

        // Статья на шапку категории — первой
        $test->call('moveArticleToCategory', $b1->id, $c1->id);
        $this->assertSame([$b1->id, $a2->id], HelpArticle::where('help_category_id', $c1->id)->orderBy('sort_order')->pluck('id')->all());

        // Во время поиска порядок не меняется
        $test->set('q', 'А')->call('moveCategory', $c2->id, $c3->id, true);
        $this->assertSame([$c3->id, $c1->id, $c2->id], HelpCategory::orderBy('sort_order')->pluck('id')->all());
    }

    public function test_public_help_shows_line_icon_or_old_emoji(): void
    {
        $c = $this->category(['name' => 'Занятия', 'slug' => 'zanyatiya', 'icon' => 'video']);
        $old = $this->category(['name' => 'Оплата', 'slug' => 'oplata', 'icon' => '💳', 'sort_order' => 2]);
        $this->article($c, ['slug' => 'kak-voyti']);

        $this->get(route('help.section', 'students'))->assertOk()
            ->assertSee('help-cat-ic', false)
            ->assertSee('💳', false)
            ->assertSee('Оплата');
        $this->get(route('help.category', ['students', 'zanyatiya']))->assertOk()->assertSee('help-cat-ic', false);
    }

    public function test_migration_maps_emoji_to_icon_keys(): void
    {
        $a = $this->category(['icon' => '💳']);
        $b = $this->category(['icon' => '🦄', 'name' => 'Другое']);
        $migration = require database_path('migrations/2026_09_29_100000_help_category_icons_to_line_icons.php');

        $migration->up();
        $this->assertSame('wallet', $a->fresh()->icon);
        $this->assertSame('🦄', $b->fresh()->icon);

        $migration->down();
        $this->assertSame('💳', $a->fresh()->icon);
    }

    public function test_admin_can_preview_draft_on_site(): void
    {
        $c = $this->category(['slug' => 'zanyatiya']);
        $a = $this->article($c, ['slug' => 'chernovik', 'title' => 'Черновик статьи', 'is_published' => false]);
        $url = route('help.article', ['students', 'zanyatiya', 'chernovik']);

        $this->get($url)->assertNotFound();
        $this->actingAs($this->user(User::ROLE_ADMIN))->get($url)->assertOk()->assertSee('Черновик статьи');
        $this->assertSame(0, $a->fresh()->views_count);
    }
}
