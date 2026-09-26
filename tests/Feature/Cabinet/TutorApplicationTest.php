<?php

namespace Tests\Feature\Cabinet;

use App\Jobs\SendTeacherApplicationTelegramNotification;
use App\Livewire\BecomeTutorPage;
use App\Mail\NewTeacherApplicationMail;
use App\Models\Direct;
use App\Models\Subject;
use App\Models\Tariff;
use App\Models\TeacherApplication;
use App\Models\User;
use App\Notifications\TeacherApplicationReceived;
use Database\Seeders\TariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/** Заявка учителя (/application): анкета → «Заявка отправлена». */
class TutorApplicationTest extends TestCase
{
    use RefreshDatabase;

    private Subject $math;
    private Subject $russian;
    private Direct $ege;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        Mail::fake();
        Bus::fake([SendTeacherApplicationTelegramNotification::class]);

        $this->math = Subject::create(['name' => 'Математика']);
        $this->russian = Subject::create(['name' => 'Русский язык']);
        $this->ege = Direct::create(['name' => 'ЕГЭ']);
    }

    private function fill(Testable $c, array $overrides = []): Testable
    {
        $values = $overrides + [
            'last_name' => 'Соколова',
            'first_name' => 'Мария',
            'middle_name' => 'Андреевна',
            'email' => 'maria@mail.ru',
            'phone' => '+7 900 123-45-67',
            'about' => 'Готовлю к ЕГЭ восемь лет',
        ];
        foreach ($values as $key => $value) {
            $c->set('data.' . $key, $value);
        }

        return $c;
    }

    public function test_page_opens(): void
    {
        $this->get(route('become-tutor'))
            ->assertOk()
            ->assertSee('Станьте репетитором на')
            ->assertSee('Что вы преподаёте')
            ->assertSee('Математика')
            ->assertSee('ЕГЭ')
            ->assertSee('Отправить заявку')
            ->assertDontSee('fi-');
    }

    public function test_submit_application(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'username' => 'adm' . uniqid()]);

        $c = $this->fill(Livewire::test(BecomeTutorPage::class))
            ->call('toggle', 'subjects', $this->math->id)
            ->call('toggle', 'subjects', $this->russian->id)
            ->call('toggle', 'subjects', $this->russian->id) // снять выбор
            ->call('toggle', 'directs', $this->ege->id)
            ->call('toggleGradeGroup', 'senior')
            ->call('create')
            ->assertHasNoErrors()
            ->assertSet('isSubmitted', true)
            ->assertSee('Заявка отправлена')
            ->assertSee('Спасибо, Мария!')
            ->assertSee('maria@mail.ru')
            ->assertDontSee('[СРОК]');

        $application = TeacherApplication::where('email', 'maria@mail.ru')->firstOrFail();
        $this->assertSame('Соколова', $application->last_name);
        $this->assertSame('+7 900 123-45-67', $application->phone);
        $this->assertSame([$this->math->id], $application->subjects);
        $this->assertSame([$this->ege->id], $application->directs);
        $this->assertSame(['10', '11'], $application->grade);
        $this->assertSame('Готовлю к ЕГЭ восемь лет', $application->about);

        Bus::assertDispatched(SendTeacherApplicationTelegramNotification::class);
        Notification::assertSentTo($admin, TeacherApplicationReceived::class);
        Mail::assertSent(NewTeacherApplicationMail::class, fn ($mail) => $mail->hasTo($admin->email));

        // Анкета очищена
        $c->assertSet('data.email', '')->assertSet('data.subjects', []);
    }

    public function test_validation_errors(): void
    {
        Livewire::test(BecomeTutorPage::class)
            ->set('data.email', 'не почта')
            ->set('data.phone', 'позвоните мне')
            ->call('create')
            ->assertHasErrors([
                'data.last_name' => 'required',
                'data.first_name' => 'required',
                'data.middle_name' => 'required',
                'data.email' => 'email',
                'data.phone' => 'regex',
                'data.subjects' => 'required',
                'data.directs' => 'required',
                'data.grade' => 'required',
                'data.about' => 'required',
            ])
            ->assertSet('isSubmitted', false)
            ->assertSee('Выберите хотя бы один предмет')
            ->assertSee('Расскажите пару слов о себе');

        $this->assertSame(0, TeacherApplication::count());
        Bus::assertNotDispatched(SendTeacherApplicationTelegramNotification::class);
    }

    public function test_registered_email_and_pending_application(): void
    {
        User::factory()->create(['email' => 'maria@mail.ru', 'role' => User::ROLE_STUDENT, 'username' => 'st' . uniqid()]);

        $this->fill(Livewire::test(BecomeTutorPage::class))
            ->call('toggle', 'subjects', $this->math->id)
            ->call('toggle', 'directs', $this->ege->id)
            ->call('toggleGradeGroup', 'adults')
            ->call('create')
            ->assertHasErrors(['data.email' => 'unique'])
            ->assertSet('emailTaken', true)
            ->assertSee('Войти в кабинет');

        TeacherApplication::create(['last_name' => 'Иванов', 'first_name' => 'Иван', 'email' => 'ivan@mail.ru', 'status' => 'pending']);

        $this->fill(Livewire::test(BecomeTutorPage::class), ['email' => 'ivan@mail.ru'])
            ->call('toggle', 'subjects', $this->math->id)
            ->call('toggle', 'directs', $this->ege->id)
            ->call('toggleGradeGroup', 'adults')
            ->call('create')
            ->assertHasErrors('data.email')
            ->assertSet('emailTaken', false)
            ->assertSee('Ваша заявка уже отправлена и находится на рассмотрении.');

        $this->assertSame(1, TeacherApplication::count());
    }

    public function test_desired_tariff_is_saved(): void
    {
        $this->seed(TariffSeeder::class);
        $tariff = Tariff::active()->whereNotNull('slug')->where('price', '>', 0)->firstOrFail();

        $this->fill(Livewire::withQueryParams(['tariff' => $tariff->slug])->test(BecomeTutorPage::class))
            ->assertSee('Вы выбрали тариф «' . $tariff->name . '»')
            ->call('toggle', 'subjects', $this->math->id)
            ->call('toggle', 'directs', $this->ege->id)
            ->call('toggleGradeGroup', 'primary')
            ->call('create')
            ->assertHasNoErrors();

        $this->assertSame($tariff->id, TeacherApplication::where('email', 'maria@mail.ru')->value('desired_tariff_id'));
    }
}
