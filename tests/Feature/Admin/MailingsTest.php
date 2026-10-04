<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\EnsureCabinetRole;
use App\Livewire\Cabinet\Admin\Mailing;
use App\Livewire\Cabinet\Admin\MailingList;
use App\Livewire\Cabinet\Admin\Mailings;
use App\Mail\MailingLetter;
use App\Models\MailingCampaign;
use App\Models\MailingContact;
use App\Models\MailingDelivery;
use App\Models\MailingList as MailingListModel;
use App\Models\User;
use App\Services\MailingService;
use App\Support\MailingImport;
use Database\Seeders\TariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

/** Админка → Рассылки (/cabinet/admin/mailings): списки адресов, письма, отправка порциями, открытия, переходы, отписка. */
class MailingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(TariffSeeder::class);
        config(['mail.newsletter.per_minute' => 20]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'username' => 'admin' . uniqid(), 'is_active' => true, 'is_blocked' => false, 'is_profile_completed' => true]);
    }

    private function service(): MailingService
    {
        return app(MailingService::class);
    }

    /** Список с адресами: ['почта' => ['Школа', 'Город']]. */
    private function list(string $name, array $contacts): MailingListModel
    {
        $list = $this->service()->createList($name);
        $rows = [];
        foreach ($contacts as $email => [$school, $city]) {
            $rows[$email] = ['email' => $email, 'name' => $school, 'city' => $city];
        }
        $this->service()->import($list, ['contacts' => $rows, 'invalid' => []]);

        return $list;
    }

    private function campaign(array $lists, array $attrs = []): MailingCampaign
    {
        return $this->service()->save(null, array_merge([
            'subject' => 'Здравствуйте, {школа|коллеги}',
            'body' => '<p>Приглашаем {школа} из города {город|вашего города}. <a href="https://serdal.ru/tariffs?a=1&b=2">Тарифы</a></p>',
            'button_text' => 'Попробовать',
            'button_url' => 'https://serdal.ru/register',
        ], $attrs), array_map(fn ($l) => $l->id, $lists));
    }

    public function test_access(): void
    {
        $this->get('/cabinet/admin/mailings')->assertRedirect(route('login'));
        $tutor = User::factory()->create(['role' => User::ROLE_TUTOR, 'username' => 't' . uniqid(), 'is_active' => true, 'is_profile_completed' => true]);
        $this->actingAs($tutor)->get('/cabinet/admin/mailings')->assertRedirect(EnsureCabinetRole::homeFor($tutor));

        $this->actingAs($this->admin())->get('/cabinet/admin/mailings')->assertOk()->assertSee('Написать письмо')->assertSee('Писем пока нет');
        $this->get('/cabinet/admin/mailings?tab=lists')->assertOk()->assertSee('Новый список');
        $this->get('/cabinet/admin/mailings/new')->assertOk()->assertSee('Новое письмо')->assertSee('Пробное письмо');
    }

    /* ---------- Разбор адресов ---------- */

    public function test_import_parses_pasted_table_and_csv(): void
    {
        $pasted = "Почта\tШкола\tГород\n"
            . "Info@School5.ru\tШкола №5\tКазань\n"
            . "\n"
            . "mailto:sch7@mail.ru, director7@mail.ru\tГимназия 7\n"
            . "Школа без почты\tПермь\n"
            . "info@school5.ru\tДубль\tКазань\n";
        $r = MailingImport::fromText($pasted);

        $this->assertSame(['info@school5.ru', 'sch7@mail.ru', 'director7@mail.ru'], array_keys($r['contacts']));
        $this->assertSame(['email' => 'info@school5.ru', 'name' => 'Школа №5', 'city' => 'Казань'], $r['contacts']['info@school5.ru']);
        $this->assertSame('Гимназия 7', $r['contacts']['director7@mail.ru']['name']);
        $this->assertSame(['Школа без почты · Пермь'], $r['invalid']);

        // CSV из русского Excel: «;» и Windows-1251
        $csv = mb_convert_encoding("Школа;Почта;Город\n\"Лицей \"\"Ступени\"\"\";lyceum@yandex.ru;Уфа\n", 'Windows-1251', 'UTF-8');
        $r = MailingImport::fromText($csv);
        $this->assertSame(['email' => 'lyceum@yandex.ru', 'name' => 'Лицей "Ступени"', 'city' => 'Уфа'], $r['contacts']['lyceum@yandex.ru']);
    }

    public function test_import_reads_xlsx(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Школы" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="worksheet" Target="worksheets/data.xml"/></Relationships>');
        $zip->addFromString('xl/sharedStrings.xml', '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>Почта</t></si><si><t>Школа</t></si><si><t>school1@mail.ru</t></si><si><r><t>Школа </t></r><r><t>№1</t></r></si></sst>');
        $zip->addFromString('xl/worksheets/data.xml', '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            . '<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c></row>'
            . '<row r="2"><c r="A2" t="s"><v>2</v></c><c r="C2" t="s"><v>3</v></c></row>'
            . '<row r="3"><c r="B3" t="inlineStr"><is><t>school2@mail.ru</t></is></c></row>'
            . '</sheetData></worksheet>');
        $zip->close();

        $r = MailingImport::fromFile($path, 'xlsx');
        unlink($path);

        $this->assertSame(['email' => 'school1@mail.ru', 'name' => 'Школа №1', 'city' => null], $r['contacts']['school1@mail.ru']);
        $this->assertArrayHasKey('school2@mail.ru', $r['contacts']);
        $this->assertSame([], $r['invalid']);
    }

    public function test_import_into_list_skips_duplicates_and_keeps_unsubscribes(): void
    {
        $list = $this->list('Казань', ['a@school.ru' => ['Школа 1', 'Казань']]);
        MailingContact::create(['email' => 'gone@school.ru', 'unsubscribed_at' => now()]);

        $result = $this->service()->import($list, MailingImport::fromText("a@school.ru\nb@school.ru\tШкола 2\ngone@school.ru"));

        $this->assertSame(2, $result['added']);
        $this->assertSame(1, $result['existing']);
        $this->assertSame(1, $result['unsubscribed']);
        $this->assertSame(3, $list->contacts()->count());
        $this->assertSame(1, MailingContact::where('email', 'a@school.ru')->count());
    }

    public function test_list_screen_imports_pasted_addresses_and_shows_bad_lines(): void
    {
        $admin = $this->admin();
        $list = $this->service()->createList('Пермь');

        Livewire::actingAs($admin)->test(MailingList::class, ['list' => $list])
            ->assertSee('В списке пока нет адресов')
            ->call('openImport')
            ->set('importMode', 'paste')
            ->call('import')->assertHasErrors(['pasted' => 'required'])
            ->set('pasted', "Почта\tШкола\nperm1@mail.ru\tШкола 1\nбез почты\nperm2@mail.ru")
            ->call('import')
            ->assertSet('importResult.summary', 'Добавлено 2 адреса')
            ->assertSee('без почты')
            ->call('closeImport')
            ->assertSee('perm1@mail.ru')
            ->assertSee('Школа 1');

        // Файл без почты — понятная ошибка
        Livewire::actingAs($admin)->test(MailingList::class, ['list' => $list])
            ->call('openImport')
            ->set('file', UploadedFile::fake()->createWithContent('schools.csv', "Школа;Город\nШкола 3;Пермь\n"))
            ->call('import')
            ->assertHasErrors('file');
    }

    private function person(string $role, array $attrs = []): User
    {
        return User::factory()->create(array_merge(['role' => $role, 'username' => $role . uniqid(), 'is_active' => true, 'is_blocked' => false, 'is_profile_completed' => true], $attrs));
    }

    public function test_list_from_platform_users(): void
    {
        $paidTariff = \App\Models\Tariff::where('price', '>', 0)->firstOrFail();
        $paid = $this->person(User::ROLE_TUTOR, ['name' => 'Иванов Иван Петрович', 'first_name' => 'Иван', 'middle_name' => 'Петрович', 'email' => 'Paid@Tutor.ru']);
        \App\Models\Subscription::create(['user_id' => $paid->id, 'tariff_id' => $paidTariff->id, 'status' => \App\Models\Subscription::STATUS_ACTIVE, 'price' => $paidTariff->price, 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        $free = $this->person(User::ROLE_TUTOR, ['email' => 'free@tutor.ru']);
        $this->person(User::ROLE_TUTOR, ['email' => 'blocked@tutor.ru', 'is_blocked' => true]);
        $student = $this->person(User::ROLE_STUDENT, ['name' => 'Мария', 'email' => 'maria@student.ru']);

        $svc = $this->service();
        $this->assertSame(['free@tutor.ru', 'paid@tutor.ru'], $svc->users('tutors')->pluck('email')->map(fn ($e) => strtolower($e))->sort()->values()->all());
        $this->assertSame([$paid->id], $svc->users('tutors', 'paid')->pluck('id')->all());
        $this->assertSame([$free->id], $svc->users('tutors', 'free')->pluck('id')->all());
        $this->assertSame([$student->id], $svc->users('students')->pluck('id')->all());
        $this->assertSame([], $svc->users('people')->pluck('id')->all());

        $admin = $this->admin();
        $list = $svc->createList('Наши учителя');
        Livewire::actingAs($admin)->test(MailingList::class, ['list' => $list])
            ->call('openImport')
            ->set('importMode', 'users')
            ->assertSee('Добавим 2 адреса')
            ->set('tariff', 'paid')
            ->assertSee('Добавим 1 адрес')
            ->call('import')
            ->assertDispatched('toast', message: 'Добавлено 1 адрес')
            ->assertSee('paid@tutor.ru')
            ->assertSee('учитель')
            // Поштучно: ученица и снова тот же учитель — он уже в списке
            ->call('openImport')
            ->set('importMode', 'users')
            ->set('userGroup', 'people')
            ->call('import')->assertHasErrors('userIds')
            ->call('pickUser', $student->id)
            ->call('pickUser', $paid->id)
            ->assertSee('Добавим 2 адреса')
            ->call('import')
            ->assertDispatched('toast', message: 'Добавлено 1 адрес, 1 уже был в списке')
            ->assertSee('maria@student.ru');

        $contact = MailingContact::where('email', 'paid@tutor.ru')->sole();
        $this->assertSame($paid->id, $contact->user_id);

        // {имя} — имя и отчество из профиля, {школа} для пользователя — его имя из профиля
        $campaign = $this->campaign([$list], ['subject' => 'Здравствуйте, {имя|коллеги}', 'body' => '<p>{школа}</p>']);
        $letter = $svc->letter($campaign, $contact);
        $this->assertSame('Здравствуйте, Иван Петрович', $letter->subjectLine);
        $this->assertSame('Здравствуйте, Мария', $svc->letter($campaign, MailingContact::where('email', 'maria@student.ru')->sole())->subjectLine);
        $this->assertSame('Здравствуйте, коллеги', $svc->letter($campaign, null)->subjectLine);
    }

    public function test_deleting_list_keeps_unsubscribed_addresses(): void
    {
        $list = $this->list('Уфа', ['x@ufa.ru' => [null, null], 'y@ufa.ru' => [null, null]]);
        MailingContact::where('email', 'y@ufa.ru')->update(['unsubscribed_at' => now()]);

        $this->service()->deleteList($list);

        $this->assertNull(MailingContact::where('email', 'x@ufa.ru')->first());
        $this->assertNotNull(MailingContact::where('email', 'y@ufa.ru')->first()?->unsubscribed_at);
    }

    /* ---------- Письмо и отправка ---------- */

    public function test_start_sends_once_per_address_in_portions(): void
    {
        Mail::fake();
        $kazan = $this->list('Казань', ['a@school.ru' => ['Школа 1', 'Казань'], 'b@school.ru' => [null, null]]);
        $ufa = $this->list('Уфа', ['b@school.ru' => [null, null], 'c@school.ru' => ['Школа 3', 'Уфа'], 'gone@school.ru' => [null, null]]);
        MailingContact::where('email', 'gone@school.ru')->update(['unsubscribed_at' => now()]);

        $campaign = $this->campaign([$kazan, $ufa]);
        $this->assertSame(3, $this->service()->recipients([$kazan->id, $ufa->id])->count());

        $this->assertSame(3, $this->service()->start($campaign));
        $this->assertSame(0, $this->service()->start($campaign->fresh()), 'повторный запуск ничего не делает');
        $this->assertSame(MailingCampaign::SENDING, $campaign->fresh()->status);
        $this->assertSame(2, $campaign->links()->count());

        $this->assertSame(2, $this->service()->sendDue(2));
        Mail::mailer('newsletter')->assertSentCount(2);
        $this->assertSame(MailingCampaign::SENDING, $campaign->fresh()->status);

        $this->artisan('mailings:send')->assertSuccessful();
        Mail::mailer('newsletter')->assertSentCount(3);
        $this->assertSame(MailingCampaign::SENT, $campaign->fresh()->status);
        $this->assertSame(3, $campaign->deliveries()->where('status', MailingDelivery::SENT)->count());

        Mail::mailer('newsletter')->assertSent(MailingLetter::class, fn (MailingLetter $m) => $m->hasTo('a@school.ru') && $m->subjectLine === 'Здравствуйте, Школа 1');
        Mail::mailer('newsletter')->assertSent(MailingLetter::class, fn (MailingLetter $m) => $m->hasTo('b@school.ru') && $m->subjectLine === 'Здравствуйте, коллеги');
    }

    public function test_letter_fills_placeholders_and_tracks_links(): void
    {
        $list = $this->list('Казань', ['a@school.ru' => ['Школа «Ромашка»', 'Казань']]);
        $campaign = $this->campaign([$list]);
        $this->service()->start($campaign);
        $delivery = $campaign->deliveries()->with('contact')->sole();

        $letter = $this->service()->letter($campaign, $delivery->contact, $delivery);
        $html = $letter->render();

        $this->assertStringContainsString('Приглашаем Школа «Ромашка» из города Казань', $html);
        $this->assertStringContainsString('/m/c/' . $delivery->token . '/', $html);
        $this->assertStringNotContainsString('serdal.ru/tariffs', $html);
        $this->assertStringContainsString('/m/o/' . $delivery->token, $html);
        $this->assertStringContainsString('/m/u/' . $delivery->token, $html);
        $this->assertStringContainsString('Отписаться', $html);
        $this->assertStringNotContainsString('Отвечать на это письмо не нужно', $html);

        $letter->assertHasReplyTo(config('mail.newsletter.reply_to'));
        $this->assertSame('List-Unsubscribe=One-Click', $letter->headers()->text['List-Unsubscribe-Post']);

        // Пробное — без учёта переходов, с прямыми ссылками
        $test = $this->service()->letter($campaign, $delivery->contact)->render();
        $this->assertStringContainsString('serdal.ru/tariffs?a=1&amp;b=2', $test);
        $this->assertStringNotContainsString('/m/o/', $test);
    }

    public function test_open_click_and_unsubscribe(): void
    {
        $list = $this->list('Казань', ['a@school.ru' => [null, null]]);
        $campaign = $this->campaign([$list]);
        $this->service()->start($campaign);
        $delivery = $campaign->deliveries()->sole();
        $link = $campaign->links()->where('url', 'https://serdal.ru/tariffs?a=1&b=2')->sole();

        $this->get('/m/o/' . $delivery->token)->assertOk()->assertHeader('Content-Type', 'image/gif');
        $this->get('/m/o/' . $delivery->token);
        $this->assertSame(2, $delivery->fresh()->opens);

        $this->get('/m/c/' . $delivery->token . '/' . $link->id)->assertRedirect('https://serdal.ru/tariffs?a=1&b=2');
        $this->assertNotNull($delivery->fresh()->clicked_at);
        $this->assertSame(1, $link->fresh()->clicks);
        $this->get('/m/c/wrong/' . $link->id)->assertRedirect('/');

        // Ещё одно письмо этому адресу ждёт отправки — после отписки не уйдёт
        $second = $this->campaign([$list], ['subject' => 'Второе']);
        $this->service()->start($second);

        // Страница не отписывает сама (почтовые сканеры открывают ссылки)
        $this->get('/m/u/' . $delivery->token)->assertOk()->assertSee('Отписаться от рассылки?');
        $this->assertNull(MailingContact::where('email', 'a@school.ru')->value('unsubscribed_at'));

        $this->post('/m/u/' . $delivery->token)->assertOk()->assertSee('Вы отписались');
        $this->assertNotNull(MailingContact::where('email', 'a@school.ru')->value('unsubscribed_at'));
        $this->assertNotNull($delivery->fresh()->unsubscribed_at);
        $this->assertSame(MailingDelivery::SKIPPED, $second->deliveries()->sole()->status);
        $this->get('/m/u/' . $delivery->token)->assertSee('Вы отписались');

        // «Отписка в один клик» из почтовой программы
        $this->post('/m/u/unknown', ['List-Unsubscribe' => 'One-Click'])->assertNotFound();
        $this->get('/m/u/test')->assertSee('Это пробное письмо');
        $this->assertSame(0, $this->service()->recipients([$list->id])->count());
    }

    public function test_server_rejecting_everything_pauses_sending(): void
    {
        $this->failingTransport(550, '550 Message rejected: Email address is not verified');
        $list = $this->list('Казань', ['a@s.ru' => [null, null], 'b@s.ru' => [null, null], 'c@s.ru' => [null, null], 'd@s.ru' => [null, null]]);
        $campaign = $this->campaign([$list]);
        $this->service()->start($campaign);

        $this->assertSame(0, $this->service()->sendDue());

        $campaign->refresh();
        $this->assertSame(MailingCampaign::SENDING, $campaign->status);
        $this->assertStringContainsString('не подтвержден', $campaign->error);
        $this->assertSame(4, $campaign->deliveries()->where('status', MailingDelivery::QUEUED)->count(), 'письма остаются в очереди');
    }

    public function test_temporary_failure_keeps_letters_queued(): void
    {
        $this->failingTransport(454, '454 Throttling failure: Maximum sending rate exceeded');
        $list = $this->list('Казань', ['a@s.ru' => [null, null]]);
        $campaign = $this->campaign([$list]);
        $this->service()->start($campaign);

        $this->service()->sendDue();

        $this->assertSame(MailingDelivery::QUEUED, $campaign->deliveries()->sole()->status);
        $this->assertStringContainsString('медленнее', $campaign->fresh()->error);
    }

    public function test_daily_quota_pauses_until_later(): void
    {
        // Первое письмо уходит, на втором Postbox сообщает, что дневной лимит исчерпан
        $calls = new \ArrayObject();
        Mail::extend('quota', fn () => new class($calls) extends AbstractTransport {
            public function __construct(private \ArrayObject $calls)
            {
                parent::__construct();
            }

            protected function doSend(SentMessage $message): void
            {
                $this->calls->append(1);
                if (count($this->calls) > 1) {
                    throw new UnexpectedResponseException('Expected response code "250" but got code "550", with message "550 5.4.5 Daily sending quota exceeded."', 550);
                }
            }

            public function __toString(): string
            {
                return 'quota://';
            }
        });
        config(['mail.mailers.newsletter' => ['transport' => 'quota']]);
        Mail::purge('newsletter');

        $list = $this->list('Казань', ['a@s.ru' => [null, null], 'b@s.ru' => [null, null], 'c@s.ru' => [null, null]]);
        $campaign = $this->campaign([$list]);
        $other = $this->campaign([$this->list('Пермь', ['p@s.ru' => [null, null]])]);
        $this->service()->start($campaign);
        $this->service()->start($other);

        $this->freezeSecond();
        $this->assertSame(1, $this->service()->sendDue());
        $campaign->refresh();
        $this->assertSame('исчерпан дневной лимит писем в Yandex Cloud Postbox', $campaign->error);
        $this->assertTrue($campaign->resume_at->eq(now()->addDay()), 'пауза — сутки с момента отказа, письмо до лимита её не сбрасывает');
        // Второе письмо рассылки тоже стоит и к серверу не обращалось
        $this->assertTrue($other->fresh()->resume_at->eq(now()->addDay()));

        // Новое письмо, запущенное во время паузы, ждёт вместе с остальными
        $late = $this->campaign([$this->list('Уфа', ['u@s.ru' => [null, null]])]);
        $this->service()->start($late);
        $this->assertTrue($late->fresh()->resume_at->eq(now()->addDay()));
        $this->assertSame(2, $campaign->deliveries()->where('status', MailingDelivery::QUEUED)->count());
        $this->assertSame(0, $campaign->deliveries()->where('status', MailingDelivery::FAILED)->count());

        // Пока пауза — к серверу не обращаемся, даже через 23 часа
        $this->assertSame(0, $this->service()->sendDue());
        $this->travel(23)->hours();
        $this->assertSame(0, $this->service()->sendDue());
        $this->assertCount(2, $calls);

        Livewire::actingAs($this->admin())->test(Mailing::class, ['campaign' => (string) $campaign->id])
            ->assertSee('Отправка стоит:')
            ->assertSee('через сутки после того, как сработал лимит');

        // Через сутки лимит обновился
        Mail::extend('quota', fn () => new class extends AbstractTransport {
            protected function doSend(SentMessage $message): void {}

            public function __toString(): string
            {
                return 'ok://';
            }
        });
        Mail::purge('newsletter');
        $this->travel(1)->hours();
        $this->assertSame(4, $this->service()->sendDue());
        $campaign->refresh();
        $this->assertSame(MailingCampaign::SENT, $campaign->status);
        $this->assertNull($campaign->error);
        $this->assertNull($campaign->resume_at);
    }

    public function test_single_bad_address_is_marked_failed(): void
    {
        $this->failingTransport(550, '550 Mailbox not found', onlyFor: 'bad@s.ru');
        $list = $this->list('Казань', ['bad@s.ru' => [null, null], 'ok@s.ru' => [null, null]]);
        $campaign = $this->campaign([$list]);
        $this->service()->start($campaign);

        $this->assertSame(1, $this->service()->sendDue());

        $this->assertSame(MailingDelivery::FAILED, $campaign->deliveries()->where('email', 'bad@s.ru')->value('status'));
        $this->assertSame(MailingCampaign::SENT, $campaign->fresh()->status);
        $this->assertSame(1, $this->service()->stats($campaign)['failed']);
    }

    /** Канал newsletter, который отклоняет письма (все или на один адрес) с кодом SMTP. */
    private function failingTransport(int $code, string $message, ?string $onlyFor = null): void
    {
        Mail::extend('failing', fn () => new class($code, $message, $onlyFor) extends AbstractTransport {
            public function __construct(private int $code, private string $text, private ?string $onlyFor)
            {
                parent::__construct();
            }

            protected function doSend(SentMessage $message): void
            {
                $to = $message->getEnvelope()->getRecipients()[0]->getAddress();
                if ($this->onlyFor === null || $this->onlyFor === $to) {
                    throw new UnexpectedResponseException($this->text, $this->code);
                }
            }

            public function __toString(): string
            {
                return 'failing://';
            }
        });
        config(['mail.mailers.newsletter' => ['transport' => 'failing']]);
        Mail::purge('newsletter');
    }

    /* ---------- Экраны ---------- */

    public function test_send_from_screen_with_confirmation(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $list = $this->list('Казань', ['a@school.ru' => ['Школа 1', null], 'b@school.ru' => [null, null]]);

        $c = Livewire::actingAs($admin)->test(Mailing::class, ['campaign' => 'new'])
            ->call('submit')->assertHasErrors(['subject' => 'required'])
            ->set('subject', 'Онлайн-занятия для учителей')
            ->set('body', '<p>Здравствуйте, {школа|коллеги}!</p>')
            ->call('submit')->assertHasErrors('listIds')
            ->set('listIds', [(string) $list->id])
            ->assertSee('Получат 2 адреса')
            ->set('buttonText', 'Попробовать')
            ->call('submit')->assertHasErrors(['buttonUrl'])
            ->set('buttonText', '')
            ->call('openPreview')
            ->assertSee('Как выглядит письмо')
            ->assertSee('Здравствуйте, Школа 1!')
            ->call('closePreview')
            ->call('sendTest')
            ->assertDispatched('toast', message: 'Пробное письмо отправлено на ' . $admin->email);

        Mail::mailer('newsletter')->assertSent(MailingLetter::class, fn (MailingLetter $m) => $m->hasTo($admin->email) && str_contains($m->bodyHtml, 'Школа 1'));
        $campaign = MailingCampaign::sole();
        $this->assertSame(MailingCampaign::DRAFT, $campaign->status);

        $c->call('submit')->assertSet('confirmSend', true)->assertSee('займёт около 1 минуты')
            ->call('send')
            ->assertRedirect(route('cabinet.admin.mailing', ['campaign' => $campaign->id]));

        $this->assertSame(MailingCampaign::SENDING, $campaign->fresh()->status);
        $this->assertSame(2, $campaign->deliveries()->count());

        $this->service()->sendDue();
        Livewire::actingAs($admin)->test(Mailing::class, ['campaign' => (string) $campaign->id])
            ->assertSee('Итоги')
            ->assertSee('a@school.ru')
            ->assertSee('Сделать копию')
            ->set('filter', 'failed')
            ->assertDontSee('a@school.ru');

        Livewire::actingAs($admin)->test(Mailings::class)->assertSee('Онлайн-занятия для учителей')->assertSee('Отправлено');
    }

    public function test_schedule_and_stop(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $list = $this->list('Казань', ['a@school.ru' => [null, null], 'b@school.ru' => [null, null]]);

        Livewire::actingAs($admin)->test(Mailing::class, ['campaign' => 'new'])
            ->set('subject', 'Позже')
            ->set('listIds', [(string) $list->id])
            ->set('when', 'later')
            ->set('sendAt', now()->subHour()->format('Y-m-d\TH:i'))
            ->call('submit')->assertHasErrors('sendAt')
            ->set('sendAt', now()->addHour()->format('Y-m-d\TH:i'))
            ->call('submit')
            ->assertRedirect();

        $campaign = MailingCampaign::sole();
        $this->assertSame(MailingCampaign::SCHEDULED, $campaign->status);

        $this->service()->sendDue();
        Mail::mailer('newsletter')->assertNothingSent();

        $this->travel(61)->minutes();
        $this->assertSame(2, $this->service()->sendDue(1) + $this->service()->sendDue(1));
        $this->assertSame(MailingCampaign::SENT, $campaign->fresh()->status);

        // Остановка: неотправленные не уйдут
        $copy = $this->service()->duplicate($campaign, $admin);
        $this->assertSame([$list->id], $copy->lists()->pluck('mailing_lists.id')->all());
        $this->service()->start($copy);
        $this->service()->sendDue(1);
        Livewire::actingAs($admin)->test(Mailing::class, ['campaign' => (string) $copy->id])
            ->call('askStop')->call('stop');
        $this->assertSame(MailingCampaign::STOPPED, $copy->fresh()->status);
        $this->assertSame(1, $copy->deliveries()->where('status', MailingDelivery::SKIPPED)->count());
        $this->assertSame(0, $this->service()->sendDue());
    }

    public function test_create_list_from_screen(): void
    {
        Livewire::actingAs($this->admin())->test(Mailings::class)
            ->set('tab', 'lists')
            ->assertSee('Списков пока нет')
            ->call('newList')
            ->call('createList')->assertHasErrors(['listName' => 'required'])
            ->set('listName', 'Школы Казани')
            ->call('createList')
            ->assertRedirect(route('cabinet.admin.mailing-list', ['list' => MailingListModel::sole()->id]));
    }
}
