<?php

namespace Tests\Feature\Admin;

use App\Livewire\Cabinet\Admin\Settings;
use App\Models\Direct;
use App\Models\Subject;
use App\Models\TeacherApplication;
use App\Models\User;
use App\Services\DictionaryService;
use App\Support\SubjectIcons;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Админка → Настройки → Справочники: предметы и направления. */
class DictionariesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
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

    private function application(array $subjects, array $directs = [], string $status = TeacherApplication::STATUS_PENDING): TeacherApplication
    {
        return TeacherApplication::create([
            'last_name' => 'Иванов', 'first_name' => 'Иван', 'email' => uniqid() . '@example.ru',
            'subjects' => $subjects, 'directs' => $directs, 'status' => $status,
        ]);
    }

    private function screen()
    {
        return Livewire::actingAs($this->user(User::ROLE_ADMIN))->test(Settings::class, ['tab' => 'dictionaries']);
    }

    public function test_list_usage_filter_and_similar_pairs(): void
    {
        $en = Subject::create(['name' => 'Английский язык']);
        $en2 = Subject::create(['name' => 'Английский']);
        $math = Subject::create(['name' => 'Математика']);
        $typo = Subject::create(['name' => 'Матиматика']);
        Subject::create(['name' => 'Черчение']);
        $t1 = $this->user(User::ROLE_TUTOR);
        $t2 = $this->user(User::ROLE_TUTOR);
        $t1->subjects()->attach([$en->id, $math->id]);
        $t2->subjects()->attach([$en->id]);
        $this->application([$en2->id]);

        $this->screen()
            ->assertSee('5 предметов · 0 направлений')
            ->assertSee('учителей: 2')
            ->assertSee('учителей: 0 · заявок: 1')
            ->assertSee('Английский и Английский язык')
            ->assertSee('Матиматика и Математика')
            ->set('dictFilter', 'unused')
            ->assertSee('Черчение')
            ->assertDontSee('Действия: Английский язык')
            ->set('dictQ', 'нет такого')
            ->assertSee('Ничего не нашлось');
    }

    public function test_similar_names_rules(): void
    {
        $this->assertTrue(DictionaryService::similarNames('Английский', 'английский  язык'));
        $this->assertTrue(DictionaryService::similarNames('ЕГЭ', 'Подготовка к ЕГЭ'));
        $this->assertTrue(DictionaryService::similarNames('Математика', 'Матиматика'));
        $this->assertTrue(DictionaryService::similarNames('Химия ', 'химия'));
        $this->assertFalse(DictionaryService::similarNames('ОГЭ', 'ЕГЭ'));
        $this->assertFalse(DictionaryService::similarNames('Химия', 'Биохимия'));
        $this->assertFalse(DictionaryService::similarNames('Физика', 'Химия'));
    }

    public function test_add_and_rename_with_duplicate_check(): void
    {
        Subject::create(['name' => 'Физика']);
        $chem = Subject::create(['name' => 'Химия']);

        $this->screen()
            ->call('startAdd')
            ->call('addItem')
            ->assertHasErrors(['addName'])
            ->set('addName', ' физика ')
            ->call('addItem')
            ->assertHasErrors(['addName'])
            ->assertSee('Такой предмет уже есть')
            ->set('addName', 'Астрономия')
            ->call('addItem')
            ->assertDispatched('toast', message: 'Добавлено')
            ->call('startRename', $chem->id)
            ->assertSet('editName', 'Химия')
            ->set('editName', 'Физика')
            ->call('saveRename')
            ->assertSee('Такой предмет уже есть — объедините их')
            ->set('editName', 'Химия  органическая')
            ->call('saveRename')
            ->assertDispatched('toast', message: 'Переименовано');

        $this->assertDatabaseHas('subjects', ['name' => 'Астрономия']);
        $this->assertSame('Химия органическая', $chem->fresh()->name);
    }

    public function test_delete_only_unused(): void
    {
        $used = Subject::create(['name' => 'Физика']);
        $byApp = Subject::create(['name' => 'Химия']);
        $unused = Subject::create(['name' => 'Черчение']);
        $this->user(User::ROLE_TUTOR)->subjects()->attach($used);
        $this->application([$byApp->id]);
        $rejected = $this->application([$unused->id, $used->id], [], TeacherApplication::STATUS_REJECTED);

        $this->screen()
            ->call('deleteItem', $used->id)
            ->assertDispatched('toast', tone: 'danger')
            ->call('deleteItem', $byApp->id)
            ->call('deleteItem', $unused->id)
            ->assertDispatched('toast', message: 'Предмет удалён');

        $this->assertDatabaseHas('subjects', ['id' => $used->id]);
        $this->assertDatabaseHas('subjects', ['id' => $byApp->id]);
        $this->assertDatabaseMissing('subjects', ['id' => $unused->id]);
        // Из рассмотренной заявки ссылка на удалённый предмет убрана
        $this->assertSame([$used->id], $rejected->fresh()->subjects);
    }

    public function test_merge_moves_teachers_and_applications(): void
    {
        $src = Subject::create(['name' => 'Английский']);
        $target = Subject::create(['name' => 'Английский язык']);
        $both = $this->user(User::ROLE_TUTOR);
        $only = $this->user(User::ROLE_TUTOR);
        $both->subjects()->attach([$src->id, $target->id]);
        $only->subjects()->attach([$src->id]);
        $app = $this->application([(string) $src->id, $target->id]);

        $this->screen()
            ->call('openMerge', $src->id)
            ->assertSee('Объединить «Английский»')
            ->assertSee('С чем объединить')
            ->set('mergeTarget', (string) $target->id)
            ->assertSee('2 учителя и 1 заявка перейдут в')
            ->call('merge')
            ->assertDispatched('toast', message: '«Английский» объединён с «Английский язык»');

        $this->assertDatabaseMissing('subjects', ['id' => $src->id]);
        $this->assertSame([$target->id], $both->subjects()->pluck('subjects.id')->all());
        $this->assertSame([$target->id], $only->subjects()->pluck('subjects.id')->all());
        $this->assertSame([$target->id], $app->fresh()->subjects);
    }

    public function test_directs_tab_and_merge_from_similar_pair(): void
    {
        $ege = Direct::create(['name' => 'ЕГЭ']);
        $prep = Direct::create(['name' => 'Подготовка к ЕГЭ']);
        $this->user(User::ROLE_TUTOR)->directs()->attach([$ege->id]);
        $this->application([], [$prep->id]);

        $this->screen()
            ->set('dict', 'directs')
            ->assertSee('Подготовка к ЕГЭ и ЕГЭ')
            ->call('openMerge', $prep->id, $ege->id)
            ->assertSet('mergeTarget', (string) $ege->id)
            ->assertSee('1 заявка перейдёт в')
            ->call('merge');

        $this->assertDatabaseMissing('directs', ['id' => $prep->id]);
        $this->assertSame(1, $ege->users()->count());
        $this->assertSame([$ege->id], TeacherApplication::first()->directs);
    }

    public function test_subject_icon_and_color_are_set_manually_and_reset_to_auto(): void
    {
        $bio = Subject::create(['name' => 'Биология']);

        $this->screen()
            ->call('openIcon', $bio->id)
            ->assertSee('Значок «Биология»')
            ->assertSee('Подобран по названию')
            ->call('pickIcon', 'microscope')
            ->call('pickColor', 'violet')
            ->call('saveIcon')
            ->assertDispatched('toast', message: 'Значок сохранён')
            ->assertSet('iconId', null);

        $this->assertSame(['microscope', 'violet'], [$bio->fresh()->icon, $bio->fresh()->color]);

        // Своя буква: пустая не сохраняется, введённая — с префиксом text:
        $this->screen()
            ->call('openIcon', $bio->id)
            ->assertSet('iconPick', 'microscope')
            ->assertSet('iconColor', 'violet')
            ->call('pickIcon', 'text')
            ->call('saveIcon')
            ->assertHasErrors('iconText')
            ->set('iconText', 'Бю')
            ->call('saveIcon')
            ->assertHasNoErrors();
        $this->assertSame('text:Бю', $bio->fresh()->icon);

        // «Авто» — поля очищаются, значок снова подбирается по названию
        $this->screen()
            ->call('openIcon', $bio->id)
            ->assertSet('iconText', 'Бю')
            ->call('pickIcon', '')
            ->call('pickColor', '')
            ->call('saveIcon');
        $this->assertSame([null, null], [$bio->fresh()->icon, $bio->fresh()->color]);
    }

    public function test_subject_icon_ignores_unknown_values_and_directs(): void
    {
        $bio = Subject::create(['name' => 'Биология']);
        $ege = Direct::create(['name' => 'ЕГЭ']);

        app(DictionaryService::class)->setSubjectIcon($bio->id, 'not-an-icon', 'black');
        $this->assertSame([null, null], [$bio->fresh()->icon, $bio->fresh()->color]);

        $this->screen()
            ->set('dict', 'directs')
            ->call('openIcon', $ege->id)
            ->assertSet('iconId', null);
    }

    public function test_badge_uses_manual_choice_over_name_rules(): void
    {
        $auto = SubjectIcons::resolve('Биология');
        $this->assertSame(['icon' => 'dna', 'text' => null, 'color' => 'green'], $auto);
        $this->assertSame('ع', SubjectIcons::resolve('Арабский язык')['text']);
        $this->assertSame(['icon' => 'flask', 'text' => null, 'color' => 'red'], SubjectIcons::resolve('Биология', 'flask', 'red'));
        // Незнакомый предмет — первая буква
        $this->assertSame('Ш', SubjectIcons::resolve('Шашки')['text']);
        $this->assertStringContainsString('<circle', SubjectIcons::badge('Биология'));
    }
}
