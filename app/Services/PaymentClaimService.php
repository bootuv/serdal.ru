<?php

namespace App\Services;

use App\Models\PaymentClaim;
use App\Models\PaymentRecord;
use App\Models\User;
use App\Notifications\PaymentClaimDecided;
use App\Notifications\PaymentClaimSubmitted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * «Сообщить об оплате» (решение владельца вместо онлайн-оплаты ученика): ученик выбирает неоплаченные занятия
 * у учителя, прикладывает чек и комментарий — учитель подтверждает (начисления становятся оплаченными,
 * блокировка снимается сама) или отклоняет. Чеки лежат на s3 без публичного доступа, ссылки на них — временные
 * и выдаются только учителю заявки и самому ученику.
 */
class PaymentClaimService
{
    /** Каталог чеков на s3: payment-claims/{id ученика}/… */
    public const DIRECTORY = 'payment-claims';

    /** Чек: фото или PDF до 10 МБ, до 5 файлов. */
    public const MAX_FILE_KB = 10240;

    public const MAX_FILES = 5;

    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'heic', 'heif', 'pdf'];

    /** Картинки, которые браузер покажет превью (HEIC открываем файлом). */
    private const PREVIEW_EXTENSIONS = ['jpg', 'jpeg', 'png'];

    /** Правила одного файла чека (для Livewire-валидации). */
    public static function fileRules(): array
    {
        return ['file', 'max:' . self::MAX_FILE_KB, 'extensions:' . implode(',', self::EXTENSIONS), 'mimes:' . implode(',', self::EXTENSIONS)];
    }

    /*
     |--------------------------------------------------------------------------
     | Ученик
     |--------------------------------------------------------------------------
     */

    /** ID начислений, по которым уже есть заявка на проверке у учителя. */
    public function pendingRecordIds(int $studentId, ?int $teacherId = null): array
    {
        return DB::table('payment_claim_record')
            ->join('payment_claims', 'payment_claims.id', '=', 'payment_claim_record.payment_claim_id')
            ->where('payment_claims.status', PaymentClaim::STATUS_PENDING)
            ->where('payment_claims.student_id', $studentId)
            ->when($teacherId, fn ($q) => $q->where('payment_claims.teacher_id', $teacherId))
            ->pluck('payment_claim_record.payment_record_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /** Неоплаченные начисления ученика у учителя, о которых ещё можно сообщить (не на проверке), по сроку. */
    public function claimableRecords(int $studentId, int $teacherId): Collection
    {
        return PaymentRecord::unpaid()
            ->where('student_id', $studentId)
            ->where('teacher_id', $teacherId)
            ->whereNotIn('id', $this->pendingRecordIds($studentId, $teacherId))
            ->with('meetingSession.room')
            ->orderBy('due_date')
            ->get();
    }

    /**
     * Ученик сообщает об оплате. Берутся только его неоплаченные начисления у этого учителя,
     * по которым нет заявки на проверке. Файлы — UploadedFile (в т.ч. временные файлы Livewire).
     *
     * @throws \DomainException понятная ученику причина, почему заявку не создать
     */
    public function submit(User $student, int $teacherId, array $recordIds, array $files = [], ?string $comment = null): PaymentClaim
    {
        $ids = array_map('intval', $recordIds);
        $pending = $this->pendingRecordIds($student->id, $teacherId);

        if (array_intersect($ids, $pending)) {
            throw new \DomainException('Об этих занятиях вы уже сообщили — учитель ещё не ответил.');
        }

        $records = $this->claimableRecords($student->id, $teacherId)->whereIn('id', $ids)->values();

        if ($records->isEmpty()) {
            throw new \DomainException('Отметьте хотя бы одно неоплаченное занятие.');
        }

        $comment = trim((string) $comment);
        $stored = $this->storeFiles($student, $files);

        $claim = DB::transaction(function () use ($student, $teacherId, $records, $comment, $stored) {
            $claim = PaymentClaim::create([
                'teacher_id' => $teacherId,
                'student_id' => $student->id,
                'status' => PaymentClaim::STATUS_PENDING,
                'comment' => $comment !== '' ? $comment : null,
                'files' => $stored,
                'amount' => self::knownSum($records),
            ]);
            $claim->records()->attach($records->pluck('id')->all());

            return $claim;
        });

        try {
            $claim->teacher?->notify(new PaymentClaimSubmitted($claim));
        } catch (\Throwable $e) {
            Log::error("[PaymentClaim] Failed to notify teacher {$teacherId} about claim {$claim->id}: " . $e->getMessage());
        }

        return $claim;
    }

    /** Последняя заявка ученика у учителя — чтобы показать «ждёт подтверждения» или причину отказа. */
    public function latestForStudent(int $studentId, int $teacherId): ?PaymentClaim
    {
        return PaymentClaim::where('student_id', $studentId)
            ->where('teacher_id', $teacherId)
            ->latest('id')
            ->first();
    }

    /*
     |--------------------------------------------------------------------------
     | Учитель
     |--------------------------------------------------------------------------
     */

    /** Заявки на проверке у учителя (по ученику или все), старые сверху. */
    public function pendingForTeacher(User $teacher, ?int $studentId = null): Collection
    {
        return PaymentClaim::pending()
            ->where('teacher_id', $teacher->id)
            ->when($studentId, fn ($q) => $q->where('student_id', $studentId))
            ->with(['student:id,name,username', 'records.meetingSession.room'])
            ->orderBy('id')
            ->get();
    }

    /** Заявка учителя на проверке, иначе 404. */
    public function findPending(User $teacher, int $claimId): PaymentClaim
    {
        $claim = PaymentClaim::pending()->where('teacher_id', $teacher->id)->whereKey($claimId)->first();
        abort_unless($claim, 404);

        return $claim;
    }

    /**
     * Подтвердить: неоплаченные начисления заявки отмечаются оплаченными (как «Отметить оплату»),
     * блокировка снимается сама. Ученик получает уведомление.
     *
     * @return Collection<int, PaymentRecord> отмеченные начисления
     */
    public function confirm(User $teacher, PaymentClaim $claim): Collection
    {
        abort_unless((int) $claim->teacher_id === (int) $teacher->id && $claim->isPending(), 404);

        $marked = app(TeacherStudentsService::class)->markRecords(
            $teacher,
            $claim->student_id,
            $claim->records()->pluck('payment_records.id')->all(),
            PaymentRecord::STATUS_PAID,
        );

        // markRecords закрывает заявки, в которых не осталось неоплаченного; на случай, если отмечать было нечего
        if ($claim->fresh()->isPending()) {
            $claim->update(['status' => PaymentClaim::STATUS_CONFIRMED, 'decided_at' => now()]);
        }

        $this->notifyStudent($claim->fresh());

        return $marked;
    }

    /** Отклонить (причина необязательна): начисления остаются неоплаченными, ученик получает уведомление. */
    public function reject(User $teacher, PaymentClaim $claim, ?string $reason = null): void
    {
        abort_unless((int) $claim->teacher_id === (int) $teacher->id && $claim->isPending(), 404);

        $reason = trim((string) $reason);
        $claim->update([
            'status' => PaymentClaim::STATUS_REJECTED,
            'reject_reason' => $reason !== '' ? Str::limit($reason, 1000, '') : null,
            'decided_at' => now(),
        ]);

        $this->notifyStudent($claim);
    }

    /**
     * Учитель сам отметил оплату (или отменил начисления) — заявки, где не осталось неоплаченного, закрываются.
     * Вызывается из TeacherStudentsService::markRecords.
     */
    public function settle(int $teacherId, int $studentId): void
    {
        PaymentClaim::pending()
            ->where('teacher_id', $teacherId)
            ->where('student_id', $studentId)
            ->whereDoesntHave('records', fn ($q) => $q->where('status', PaymentRecord::STATUS_UNPAID))
            ->update(['status' => PaymentClaim::STATUS_CONFIRMED, 'decided_at' => now()]);
    }

    /*
     |--------------------------------------------------------------------------
     | Чеки
     |--------------------------------------------------------------------------
     */

    /**
     * Файлы заявки для показа: имя, временная ссылка (30 минут), можно ли показать превью.
     * Ссылки получают только учитель заявки и сам ученик — остальным пустой список.
     *
     * @return array<int, array{name:string, url:?string, image:bool}>
     */
    public function files(PaymentClaim $claim, User $viewer): array
    {
        if (! in_array((int) $viewer->id, [(int) $claim->teacher_id, (int) $claim->student_id], true)) {
            return [];
        }

        return collect($claim->files ?? [])
            ->map(function (array $file) {
                $ext = mb_strtolower(pathinfo($file['name'] ?? $file['path'], PATHINFO_EXTENSION));

                return [
                    'name' => $file['name'] ?? basename($file['path']),
                    'url' => $this->temporaryUrl($file['path']),
                    'image' => in_array($ext, self::PREVIEW_EXTENSIONS, true),
                ];
            })
            ->values()
            ->all();
    }

    private function temporaryUrl(string $path): ?string
    {
        try {
            return Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(30));
        } catch (\Throwable $e) {
            Log::warning('[PaymentClaim] temporaryUrl failed: ' . $e->getMessage(), ['path' => $path]);

            return null;
        }
    }

    /** Сохраняет чеки на s3 (без публичного доступа). */
    private function storeFiles(User $student, array $files): array
    {
        $stored = [];

        foreach (array_slice($files, 0, self::MAX_FILES) as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $ext = mb_strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
            $dir = self::DIRECTORY . '/' . $student->id;
            $path = Storage::disk('s3')->putFileAs($dir, $file, Str::uuid() . '.' . $ext);

            if ($path) {
                $stored[] = ['path' => $path, 'name' => $file->getClientOriginalName() ?: basename($path), 'size' => $file->getSize()];
            }
        }

        return $stored;
    }

    private function notifyStudent(PaymentClaim $claim): void
    {
        try {
            $claim->student?->notify(new PaymentClaimDecided($claim));
        } catch (\Throwable $e) {
            Log::error("[PaymentClaim] Failed to notify student {$claim->student_id} about claim {$claim->id}: " . $e->getMessage());
        }
    }

    /** Сумма, если известна у всех начислений (PaymentRecord::amount(); у старых помесячных её может не быть). */
    public static function knownSum(Collection $records): ?int
    {
        $amounts = $records->map(fn (PaymentRecord $r) => $r->amount());

        return $amounts->isEmpty() || $amounts->contains(null) || $amounts->sum() <= 0 ? null : (int) $amounts->sum();
    }
}
