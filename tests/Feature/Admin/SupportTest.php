<?php

namespace Tests\Feature\Admin;

use App\Events\SupportMessagesRead;
use App\Http\Middleware\EnsureCabinetRole;
use App\Jobs\SendUnreadSupportMessageNotification;
use App\Livewire\Cabinet\Admin\Support;
use App\Models\PaymentRecord;
use App\Models\Subject;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SupportChat;
use App\Models\SupportMessage;
use App\Models\Tariff;
use App\Models\User;
use App\Services\MessengerService;
use Database\Seeders\TariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Админка → Поддержка (/cabinet/admin/support): обращения, переписка от имени поддержки, карточка человека. */
class SupportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $ivan;

    private User $alina;

    private SupportChat $ivanChat;

    private SupportChat $alinaChat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Queue::fake();
        Event::fake([SupportMessagesRead::class, \App\Events\SupportMessageSent::class]);
        Storage::fake('s3');
        $this->seed(TariffSeeder::class);

        $this->admin = $this->user(User::ROLE_ADMIN, ['name' => 'Анна Куликова']);
        $this->ivan = $this->user(User::ROLE_TUTOR, ['name' => 'Иван Орлов', 'email' => 'ivan.orlov@yandex.ru', 'phone' => '+7 903 512-44-18', 'telegram' => 'orlov_history']);
        $this->alina = $this->user(User::ROLE_STUDENT, ['name' => 'Алина Смирнова', 'email' => 'alina@mail.ru']);

        $this->ivanChat = SupportChat::create(['user_id' => $this->ivan->id]);
        $this->alinaChat = SupportChat::create(['user_id' => $this->alina->id]);
        $this->msg($this->alinaChat, $this->alina, 'Не могу войти в класс', now()->subHours(2), now()->subHour());
        $this->msg($this->alinaChat, $this->admin, 'Сейчас проверим', now()->subHour(), now());
        $this->msg($this->ivanChat, $this->ivan, 'Оплатил, а тариф не поменялся', now()->subMinutes(10));
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

    private function msg(SupportChat $chat, User $from, string $text, $at = null, $readAt = null): SupportMessage
    {
        $m = SupportMessage::create(['support_chat_id' => $chat->id, 'user_id' => $from->id, 'content' => $text, 'read_at' => $readAt]);
        if ($at) {
            $m->forceFill(['created_at' => $at])->save();
        }

        return $m;
    }

    public function test_access(): void
    {
        $this->get('/cabinet/admin/support')->assertRedirect(route('login'));
        $this->actingAs($this->ivan)->get('/cabinet/admin/support')->assertRedirect(EnsureCabinetRole::homeFor($this->ivan));
        $this->actingAs($this->alina)->get('/cabinet/admin/support')->assertRedirect(route('cabinet.student.home'));

        $this->actingAs($this->admin)->get('/cabinet/admin/support')
            ->assertOk()
            ->assertSee('Поддержка')
            ->assertSee('Иван Орлов')
            ->assertSee('Алина Смирнова')
            ->assertSee('Вы: Сейчас проверим')
            ->assertSee('Непрочитанные · 1');
    }

    public function test_search_and_unread_filter(): void
    {
        Livewire::actingAs($this->admin)->test(Support::class)
            ->set('q', 'alina@')
            ->assertSee('Алина Смирнова')
            ->assertDontSee('Иван Орлов')
            ->set('q', 'нет такого')
            ->assertSee('Никого не нашли — проверьте имя или почту')
            ->set('q', '')
            ->set('filter', 'unread')
            ->assertSee('Иван Орлов')
            ->assertDontSee('Алина Смирнова');

        SupportMessage::query()->update(['read_at' => now()]);
        Livewire::actingAs($this->admin)->test(Support::class)->set('filter', 'unread')
            ->assertSee('Непрочитанных нет — все обращения разобраны');
    }

    public function test_opening_marks_owner_messages_read_only(): void
    {
        $other = $this->user(User::ROLE_ADMIN);
        $fromOtherAdmin = $this->msg($this->ivanChat, $other, 'Ответ коллеги');

        Livewire::actingAs($this->admin)->test(Support::class)
            ->call('open', $this->ivanChat->id)
            ->assertSee('Оплатил, а тариф не поменялся')
            ->assertSee('Ответ коллеги')
            ->assertSee('Учитель');

        $this->assertSame(0, SupportMessage::where('support_chat_id', $this->ivanChat->id)->where('user_id', $this->ivan->id)->whereNull('read_at')->count());
        $this->assertNull($fromOtherAdmin->fresh()->read_at);
        Event::assertDispatched(SupportMessagesRead::class);
    }

    public function test_send_reply_with_attachment_notifies_owner(): void
    {
        $page = Livewire::actingAs($this->admin)->test(Support::class)
            ->call('open', $this->ivanChat->id)
            ->set('picked', [UploadedFile::fake()->create('инструкция.pdf', 120, 'application/pdf')])
            ->set('draft', 'Иван, проверили — тариф уже включён')
            ->call('send')
            ->assertSet('draft', '')
            ->assertSee('Иван, проверили — тариф уже включён');

        $message = SupportMessage::where('user_id', $this->admin->id)->where('support_chat_id', $this->ivanChat->id)->latest('id')->first();
        $this->assertSame('Иван, проверили — тариф уже включён', $message->content);
        $this->assertSame('инструкция.pdf', $message->attachments[0]['name']);
        Queue::assertPushed(SendUnreadSupportMessageNotification::class, fn ($job) => true);

        // Пользователь видит ответ как сообщение поддержки
        $thread = app(MessengerService::class)->thread($this->ivan, $this->ivanChat, 30);
        $last = collect($thread['items'])->last();
        $this->assertFalse($last['own']);
        $this->assertSame('Поддержка Serdal', $last['who']);

        // А у администратора — справа, как своё
        $adminThread = app(MessengerService::class)->thread($this->admin, $this->ivanChat, 30);
        $this->assertTrue(collect($adminThread['items'])->last()['own']);
        $page->assertSet('files', []);
    }

    public function test_edit_and_delete_own_only(): void
    {
        $mine = $this->msg($this->ivanChat, $this->admin, 'Черновой ответ');
        $colleague = $this->msg($this->ivanChat, $this->user(User::ROLE_ADMIN), 'Ответ коллеги');

        $page = Livewire::actingAs($this->admin)->test(Support::class)
            ->call('open', $this->ivanChat->id)
            ->call('edit', $mine->id)
            ->assertSet('draft', 'Черновой ответ')
            ->set('draft', 'Исправленный ответ')
            ->call('send');
        $this->assertSame('Исправленный ответ', $mine->fresh()->content);

        $page->call('confirmDelete', $mine->id)->call('delete');
        $this->assertNull(SupportMessage::find($mine->id));

        $page->call('edit', $colleague->id)->assertForbidden();
        Livewire::actingAs($this->admin)->test(Support::class)
            ->call('open', $this->ivanChat->id)
            ->call('confirmDelete', $colleague->id)->assertForbidden();
        $this->assertNotNull(SupportMessage::find($colleague->id));
    }

    public function test_person_card_without_lesson_payments(): void
    {
        $this->ivan->subjects()->attach(Subject::create(['name' => 'История'])->id);
        $pro = Tariff::where('slug', 'pro')->first();
        Subscription::create(['user_id' => $this->ivan->id, 'tariff_id' => Tariff::where('slug', 'basic')->first()->id, 'status' => 'active', 'price' => 1490, 'starts_at' => now()->subDays(5), 'ends_at' => now()->addDays(25)]);
        $p = SubscriptionPayment::create(['user_id' => $this->ivan->id, 'tariff_id' => $pro->id, 'amount' => 2990, 'period_days' => 30, 'status' => 'pending']);
        $p->forceFill(['created_at' => now()->subDays(2)])->save();

        Livewire::actingAs($this->admin)->test(Support::class)
            ->call('open', $this->ivanChat->id)
            ->assertSee('Учитель · история')
            ->call('openCard')
            ->assertSee('Карточка человека')
            ->assertSee('ivan.orlov@yandex.ru')
            ->assertSee('+7 903 512-44-18')
            ->assertSee('Telegram · @orlov_history')
            ->assertSee('«Базовый» до')
            ->assertSee('Платёж 2 990 ₽ за «Профи»')
            ->assertSee('ждёт оплаты с')
            ->assertSee('Учеников')
            ->assertSee('Регистрация')
            ->assertDontSee('Долг')
            ->assertDontSee('Не оплачено')
            ->call('closeCard')
            ->assertSet('cardOpen', false);
    }

    public function test_cannot_open_admin_chat_or_missing(): void
    {
        $adminChat = SupportChat::create(['user_id' => $this->user(User::ROLE_ADMIN)->id]);

        Livewire::actingAs($this->admin)->test(Support::class)
            ->call('open', $adminChat->id)->assertNotFound();
        Livewire::actingAs($this->admin)->test(Support::class)
            ->call('open', 999999)->assertNotFound();
    }

    public function test_user_side_messages_screen_still_works(): void
    {
        // Кабинет пользователя: прочтение ответа поддержки не трогает его собственные сообщения
        $reply = $this->msg($this->ivanChat, $this->admin, 'Ответ поддержки');
        app(MessengerService::class)->markRead($this->ivan, $this->ivanChat);

        $this->assertNotNull($reply->fresh()->read_at);
        $this->assertNull(SupportMessage::where('user_id', $this->ivan->id)->first()->read_at);
    }
}
