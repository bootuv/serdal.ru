<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\Cabinet\Student\Payments as StudentPayments;
use App\Livewire\Cabinet\Teacher\Student as TeacherStudent;
use App\Livewire\Cabinet\Teacher\Today;
use App\Models\MeetingSession;
use App\Models\PaymentClaim;
use App\Models\PaymentRecord;
use App\Models\Room;
use App\Models\User;
use App\Notifications\PaymentClaimDecided;
use App\Notifications\PaymentClaimSubmitted;
use App\Services\PaymentClaimService;
use App\Services\PaymentRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** «Сообщить об оплате»: ученик → заявка с чеком → учитель подтверждает или отклоняет. */
class PaymentClaimTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('s3');
        Notification::fake();
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

    /** Занятие учителя, завершённое $endedAt; ученик был на нём, цена — $price. */
    private function lesson(User $teacher, User $student, $endedAt, int $price = 1500): MeetingSession
    {
        $room = Room::create([
            'user_id' => $teacher->id,
            'name' => 'Английский язык',
            'meeting_id' => 'meet-' . uniqid(),
            'moderator_pw' => 'mp',
            'attendee_pw' => 'ap',
        ]);
        $room->participants()->attach($student->id);

        return MeetingSession::create([
            'user_id' => $teacher->id,
            'room_id' => $room->id,
            'meeting_id' => $room->meeting_id,
            'status' => 'completed',
            'started_at' => $endedAt->copy()->subHour(),
            'ended_at' => $endedAt,
            'pricing_snapshot' => ['participants' => [['user_id' => $student->id, 'attended' => true, 'price' => $price]]],
        ]);
    }

    private function record(User $teacher, User $student, MeetingSession $session, array $attrs = []): PaymentRecord
    {
        return PaymentRecord::create(array_merge([
            'teacher_id' => $teacher->id,
            'student_id' => $student->id,
            'type' => PaymentRecord::TYPE_PER_LESSON,
            'status' => PaymentRecord::STATUS_UNPAID,
            'meeting_session_id' => $session->id,
            'due_date' => now()->addDays(3),
        ], $attrs));
    }

    /** Ученица с закрытым входом: долг просрочен 10 дней назад, после срока — 3 занятия. */
    private function blockedPair(): array
    {
        $teacher = $this->user(User::ROLE_TUTOR, ['name' => 'Мария Соколова']);
        $student = $this->user(User::ROLE_STUDENT, ['first_name' => 'Алина', 'last_name' => 'Смирнова']);
        $teacher->students()->attach($student->id);

        $first = $this->record($teacher, $student, $this->lesson($teacher, $student, now()->subDays(13)), ['due_date' => now()->subDays(10)]);
        $records = [$first];
        foreach ([8, 6, 4] as $daysAgo) {
            $records[] = $this->record($teacher, $student, $this->lesson($teacher, $student, now()->subDays($daysAgo)), ['due_date' => now()->subDays($daysAgo - 3)]);
        }

        return [$teacher, $student, $records];
    }

    public function test_student_reports_payment_teacher_confirms_and_access_opens(): void
    {
        [$teacher, $student, $records] = $this->blockedPair();
        $this->assertTrue(PaymentRecordService::isBlockedForTeacher($student->id, $teacher->id));

        // Ученик: окно по ?report=, суммы, чек и комментарий
        $this->actingAs($student)
            ->get(route('cabinet.student.payments'))
            ->assertOk()
            ->assertSee('Сообщить об оплате')
            ->assertSee("6 000 ₽")
            ->assertSee("1 500 ₽");

        $page = Livewire::actingAs($student)
            ->withQueryParams(['report' => $teacher->id])
            ->test(StudentPayments::class)
            ->assertSet('reportTeacherId', $teacher->id)
            ->assertSee('Что вы оплатили')
            ->assertSee('Итого')
            ->set('picked', [UploadedFile::fake()->image('check.jpg')])
            ->assertHasNoErrors()
            ->assertSee('check.jpg')
            ->set('reportComment', 'Перевела по номеру телефона')
            ->call('sendReport')
            ->assertHasNoErrors()
            ->assertSet('reportTeacherId', null)
            ->assertDispatched('toast')
            // Ученик видит, что и когда отправил: комментарий и чек
            ->assertSee('Отправлено учителю сегодня в')
            ->assertSee('Перевела по номеру телефона')
            ->assertSee('check.jpg');
        // Все занятия на проверке — статус одной строкой, без повтора в каждой строке
        $this->assertSame(1, substr_count($page->html(), 'ждёт подтверждения'));

        $claim = PaymentClaim::sole();
        $this->assertSame(PaymentClaim::STATUS_PENDING, $claim->status);
        $this->assertSame(6000, $claim->amount);
        $this->assertCount(4, $claim->records);
        $this->assertCount(1, $claim->files);
        Storage::disk('s3')->assertExists($claim->files[0]['path']);
        Notification::assertSentTo($teacher, PaymentClaimSubmitted::class);

        // Пока заявка на проверке — вход всё ещё закрыт
        $this->assertTrue(PaymentRecordService::isBlockedForTeacher($student->id, $teacher->id));

        // Учитель: пометка в карточке и на «Сегодня», окно с чеком, подтверждение
        Livewire::actingAs($teacher)->test(Today::class)
            ->assertSee('Ученик сообщил об оплате')
            ->assertSee('Проверить оплату');

        Livewire::actingAs($teacher)->test(TeacherStudent::class, ['student' => $student])
            ->assertSee('Ученик сообщил об оплате')
            ->call('openClaim', $claim->id)
            ->assertSee('Перевела по номеру телефона')
            ->assertSee('Чек: check.jpg')
            ->assertSee("6 000 ₽")
            ->call('confirmClaim')
            ->assertSet('claimId', null)
            ->assertDispatched('toast');

        foreach ($records as $record) {
            $this->assertSame(PaymentRecord::STATUS_PAID, $record->fresh()->status);
            $this->assertSame($teacher->id, (int) $record->fresh()->marked_by);
        }
        $this->assertSame(PaymentClaim::STATUS_CONFIRMED, $claim->fresh()->status);
        $this->assertFalse(PaymentRecordService::isBlockedForTeacher($student->id, $teacher->id));
        Notification::assertSentTo($student, PaymentClaimDecided::class, fn ($n) => $n->claim->status === PaymentClaim::STATUS_CONFIRMED);
    }

    public function test_teacher_rejects_with_reason_and_student_can_report_again(): void
    {
        [$teacher, $student, $records] = $this->blockedPair();
        $claim = app(PaymentClaimService::class)->submit($student, $teacher->id, [$records[0]->id], [], 'Оплатила наличными');

        Livewire::actingAs($teacher)->test(TeacherStudent::class, ['student' => $student])
            ->call('openClaim', $claim->id)
            ->set('claimRejecting', true)
            ->assertSee('Не подтверждать оплату?')
            ->set('claimReason', 'Денег не видно')
            ->call('rejectClaim')
            ->assertSet('claimId', null)
            ->assertDispatched('toast');

        $claim->refresh();
        $this->assertSame(PaymentClaim::STATUS_REJECTED, $claim->status);
        $this->assertSame('Денег не видно', $claim->reject_reason);
        $this->assertSame(PaymentRecord::STATUS_UNPAID, $records[0]->fresh()->status);
        $this->assertTrue(PaymentRecordService::isBlockedForTeacher($student->id, $teacher->id));
        Notification::assertSentTo($student, PaymentClaimDecided::class, fn ($n) => $n->claim->status === PaymentClaim::STATUS_REJECTED);

        // Ученик видит отказ и может сообщить снова
        $this->actingAs($student)
            ->get(route('cabinet.student.payments'))
            ->assertSee('Учитель не подтвердил оплату')
            ->assertSee('Денег не видно');

        app(PaymentClaimService::class)->submit($student, $teacher->id, [$records[0]->id], [], 'Ещё раз');
        $this->assertSame(1, PaymentClaim::pending()->count());
    }

    public function test_repeated_claim_for_same_lessons_is_refused(): void
    {
        [$teacher, $student, $records] = $this->blockedPair();
        $service = app(PaymentClaimService::class);
        $service->submit($student, $teacher->id, [$records[0]->id, $records[1]->id], [], 'Перевела');

        try {
            $service->submit($student, $teacher->id, [$records[1]->id], [], 'Ещё раз');
            $this->fail('Повторная заявка по тем же занятиям должна быть запрещена');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('уже сообщили', $e->getMessage());
        }

        // В окне остаются только ещё не отправленные занятия
        Livewire::actingAs($student)->test(StudentPayments::class)
            ->call('openReport', $teacher->id)
            ->assertSet('reportSelected', [$records[2]->id, $records[3]->id])
            ->set('reportSelected', [(string) $records[0]->id])
            ->set('reportComment', 'Опять')
            ->call('sendReport')
            ->assertHasErrors('reportSelected');

        $this->assertSame(1, PaymentClaim::count());

        // Всё отправлено — окно не открывается
        $service->submit($student, $teacher->id, [$records[2]->id, $records[3]->id], [], 'Остальное');
        Livewire::actingAs($student)->test(StudentPayments::class)
            ->call('openReport', $teacher->id)
            ->assertSet('reportTeacherId', null);
    }

    public function test_receipt_or_comment_is_required_and_file_types_are_checked(): void
    {
        [$teacher, $student] = $this->blockedPair();

        Livewire::actingAs($student)->test(StudentPayments::class)
            ->call('openReport', $teacher->id)
            ->call('sendReport')
            ->assertHasErrors('receipts')
            ->set('picked', [UploadedFile::fake()->create('virus.exe', 10)])
            ->assertHasErrors('picked.0')
            ->set('picked', [UploadedFile::fake()->create('big.pdf', PaymentClaimService::MAX_FILE_KB + 100, 'application/pdf')])
            ->assertHasErrors('picked.0');

        $this->assertSame(0, PaymentClaim::count());
    }

    public function test_other_teacher_cannot_see_receipt(): void
    {
        [$teacher, $student, $records] = $this->blockedPair();
        $claim = app(PaymentClaimService::class)->submit($student, $teacher->id, [$records[0]->id], [UploadedFile::fake()->image('check.png')], null);

        $stranger = $this->user(User::ROLE_TUTOR);
        $otherStudent = $this->user(User::ROLE_STUDENT);

        // Ссылки на чек — только учителю заявки и самому ученику
        $this->assertSame([], app(PaymentClaimService::class)->files($claim, $stranger));
        $this->assertSame([], app(PaymentClaimService::class)->files($claim, $otherStudent));
        $this->assertCount(1, app(PaymentClaimService::class)->files($claim, $teacher));
        $this->assertCount(1, app(PaymentClaimService::class)->files($claim, $student));

        // Чужой учитель не откроет заявку и не подтвердит её
        $stranger->students()->attach($otherStudent->id);
        Livewire::actingAs($stranger)->test(Today::class)
            ->call('openClaim', $claim->id)
            ->assertStatus(404);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(PaymentClaimService::class)->confirm($stranger, $claim);
    }

    public function test_manual_mark_paid_closes_pending_claim(): void
    {
        [$teacher, $student, $records] = $this->blockedPair();
        $claim = app(PaymentClaimService::class)->submit($student, $teacher->id, [$records[0]->id], [], 'Перевела');

        Livewire::actingAs($teacher)->test(TeacherStudent::class, ['student' => $student])
            ->call('openModal', 'mark')
            ->set('selected', [$records[0]->id])
            ->call('markPaid');

        $this->assertSame(PaymentClaim::STATUS_CONFIRMED, $claim->fresh()->status);
    }
}
