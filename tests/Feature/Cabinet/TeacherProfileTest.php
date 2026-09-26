<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\Cabinet\Teacher\Profile;
use App\Models\Direct;
use App\Models\LessonType;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Профиль и цены учителя в новом кабинете (/cabinet/teacher/profile). */
class TeacherProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('s3');
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
        $this->get(route('cabinet.teacher.profile'))->assertRedirect();

        $this->actingAs($this->user(User::ROLE_STUDENT))
            ->get(route('cabinet.teacher.profile'))
            ->assertForbidden();
    }

    public function test_incomplete_profile_goes_to_onboarding(): void
    {
        $this->actingAs($this->user(User::ROLE_TUTOR, ['is_profile_completed' => false]))
            ->get(route('cabinet.teacher.profile'))
            ->assertRedirect(route('cabinet.teacher.onboarding'));
    }

    public function test_page_shows_profile_and_catalog_preview(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR, ['about' => '<p>Преподаю <b>английский</b> восемь лет</p>', 'grade' => ['5', '6', '7', 'adults']]);
        $math = Subject::create(['name' => 'Математика']);
        $teacher->subjects()->attach($math);
        LessonType::create(['user_id' => $teacher->id, 'type' => LessonType::TYPE_INDIVIDUAL, 'payment_type' => 'per_lesson', 'price' => 1500, 'duration' => 60, 'payment_due_days' => 3]);

        $this->actingAs($teacher)->get(route('cabinet.teacher.profile'))
            ->assertOk()
            ->assertSee('Профиль и цены')
            ->assertSee('Так вас видят в каталоге')
            ->assertSee('Соколова Мария')
            ->assertSee('от 1 500 ₽')
            ->assertSee('5-7 классы, взрослые')
            ->assertSee(route('tutors.show', ['username' => $teacher->username]));

        // Текст из редактора старого кабинета — в поле простым текстом
        Livewire::actingAs($teacher)->test(Profile::class)->assertSet('about', 'Преподаю английский восемь лет');
    }

    public function test_saves_profile_like_old_cabinet(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR, ['about' => '<ul><li>списки</li></ul>']);
        $math = Subject::create(['name' => 'Математика']);
        $ege = Direct::create(['name' => 'ЕГЭ']);

        Livewire::actingAs($teacher)->test(Profile::class)
            ->set('last_name', 'Иванова')
            ->set('addSubject', (string) $math->id)
            ->set('addDirect', (string) $ege->id)
            ->call('toggleGrade', '10')
            ->call('toggleGrade', 'adults')
            ->set('extra_info', "МПГУ, 2016\n\nCELTA")
            ->set('whatsup', '+7 916 245-18-73')
            ->set('telegram', '@sokolova')
            ->set('password', 'new-secret-1')
            ->set('photo', UploadedFile::fake()->image('me.jpg'))
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('saved', true)
            ->assertSee('Сохранено');

        $teacher->refresh();
        $this->assertSame('Иванова', $teacher->last_name);
        $this->assertSame('Иванова Мария', $teacher->name);
        $this->assertSame([$math->id], $teacher->subjects()->pluck('subjects.id')->all());
        $this->assertSame([$ege->id], $teacher->directs()->pluck('directs.id')->all());
        $this->assertSame(['10', 'adults'], $teacher->grade);
        $this->assertSame('<p>МПГУ, 2016</p><p>CELTA</p>', $teacher->extra_info);
        // Текст из старого редактора не меняли — HTML сохранён как был
        $this->assertSame('<ul><li>списки</li></ul>', $teacher->about);
        $this->assertSame('sokolova', $teacher->telegram);
        $this->assertTrue(Hash::check('new-secret-1', $teacher->password));
        $this->assertNotNull($teacher->avatar);
        Storage::disk('s3')->assertExists($teacher->avatar);
    }

    public function test_validation_like_old_cabinet(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);

        Livewire::actingAs($teacher)->test(Profile::class)
            ->set('first_name', '')
            ->set('phone', 'позвоните мне')
            ->call('save')
            ->assertHasErrors(['first_name' => 'required', 'phone' => 'regex']);
    }

    public function test_prices_crud_one_per_type(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);

        $c = Livewire::actingAs($teacher)->test(Profile::class)
            ->set('tab', 'prices')
            ->assertSee('Добавить тип занятия')
            ->call('createPrice')
            ->assertSet('priceType', LessonType::TYPE_INDIVIDUAL)
            ->set('price', '1500')
            ->set('priceDuration', '60')
            ->set('priceDueDays', '3')
            ->call('savePrice')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        // Второй — только групповой, по умолчанию помесячно
        $c->call('createPrice')
            ->assertSet('priceType', LessonType::TYPE_GROUP)
            ->assertSet('pricePayment', LessonType::PAYMENT_MONTHLY)
            ->set('price', '6000')
            ->set('priceDuration', '90')
            ->call('savePrice')
            ->assertHasErrors(['priceCount' => 'required'])
            ->set('priceCount', '2')
            ->set('priceDueDay', '5')
            ->call('savePrice')
            ->assertHasNoErrors()
            ->assertDontSee('Добавить тип занятия')
            ->assertSee('≈ 750 ₽ за занятие');

        $this->assertSame(2, $teacher->lessonTypes()->count());
        $group = $teacher->lessonTypes()->where('type', LessonType::TYPE_GROUP)->first();
        $this->assertSame(5, (int) $group->payment_due_day);

        // Больше двух нельзя
        Livewire::actingAs($teacher)->test(Profile::class)->call('createPrice')->assertForbidden();

        // Изменить и удалить
        $individual = $teacher->lessonTypes()->where('type', LessonType::TYPE_INDIVIDUAL)->first();
        $c->call('editPrice', $individual->id)
            ->set('price', '1800')
            ->call('savePrice');
        $this->assertSame(1800, (int) $individual->fresh()->price);

        $c->call('editPrice', $individual->id)
            ->call('confirmDeletePrice')
            ->call('deletePrice');
        $this->assertNull($individual->fresh());
    }

    public function test_cannot_touch_foreign_price(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);
        $other = $this->user(User::ROLE_TUTOR);
        $foreign = LessonType::create(['user_id' => $other->id, 'type' => LessonType::TYPE_INDIVIDUAL, 'payment_type' => 'per_lesson', 'price' => 1000, 'duration' => 60]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Livewire::actingAs($teacher)->test(Profile::class)->call('editPrice', $foreign->id);
    }

    public function test_notifications_tab(): void
    {
        $teacher = $this->user(User::ROLE_TUTOR);

        Livewire::actingAs($teacher)->test(Profile::class)
            ->set('tab', 'notify')
            ->assertSee('О чём сообщаем')
            ->assertSee('Работа сдана');
    }
}
