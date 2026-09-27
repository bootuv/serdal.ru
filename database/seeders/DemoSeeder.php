<?php

namespace Database\Seeders;

use App\Models\Direct;
use App\Models\Homework;
use App\Models\HomeworkActivity;
use App\Models\HomeworkSubmission;
use App\Models\LessonPlan;
use App\Models\LessonType;
use App\Models\MaterialFolder;
use App\Models\MeetingSession;
use App\Models\Message;
use App\Models\PaymentClaim;
use App\Models\PaymentRecord;
use App\Models\Recording;
use App\Models\ReferralReward;
use App\Models\Review;
use App\Models\Room;
use App\Models\RoomSchedule;
use App\Models\RoomScheduleException;
use App\Models\Subject;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SupportChat;
use App\Models\SupportMessage;
use App\Models\Tariff;
use App\Models\TeacherApplication;
use App\Models\TeacherMaterial;
use App\Models\User;
use App\Notifications\Messages\CabinetMessage;
use App\Services\PaymentRecordService;
use App\Services\TeacherLessonService;
use App\Services\TeacherStudentsService;
use App\Support\HumanDate;
use App\Support\Money;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Демо-кабинеты: учитель химии с полной историей за шесть недель, учитель биологии и их ученики.
 * Запуск: php artisan db:seed --class=DemoSeeder (повторный запуск пересоздаёт всё с нуля). В production не запускается.
 *
 * Логины (пароль у всех: password):
 *   Учитель химии:    pay-teacher@demo.ru      — тариф «Профи», 16 учеников, две группы
 *   Учитель биологии: pay-teacher-bio@demo.ru  — у Хавы, Адама и Марем по два учителя
 *   Ученики:  pay-paid@demo.ru            Хава — всё оплачено, чек подтверждён, одно начисление отменено
 *             pay-pending@demo.ru         Адам — свежие начисления, срок не прошёл; ближайшее занятие отменено
 *             pay-overdue@demo.ru         Ибрагим — просрочка, одно занятие с долгом (предупреждение), чек на проверке, занятие перенесено
 *             pay-blocked@demo.ru         Муса — долг за три занятия: занятия закрыты, чек отклонён
 *             pay-monthly-paid@demo.ru    Марем — помесячная оплата, всё оплачено
 *             pay-monthly-overdue@demo.ru Иса — помесячная, месяц не оплачен (предупреждение)
 *             pay-free@demo.ru            Аминат — бесплатный ученик
 *             pay-override@demo.ru        Мадина — персональная помесячная оплата, счёт за месяц ждёт оплаты
 *             pay-extra-1..6@demo.ru      обычная история: оплачено + свежее начисление
 *             pay-new@demo.ru             Рамзан — новый ученик, первое (пробное) занятие на следующей неделе
 *
 * Что есть в кабинетах: занятия по расписанию с посещаемостью и активностью, переносы и отмены,
 * записи занятий, планы ближайших занятий, задания во всех статусах с файлами, материалы в папках,
 * чаты (личные и групповые, с вложениями), уведомления, отзывы, заявки «Я оплатил», тариф и история
 * платежей, приглашённые учителя, чат с поддержкой (если в базе есть админ).
 *
 * Файлы (материалы, вложения, чеки) загружаются в S3, только если он настроен; иначе в базе остаются пути,
 * а ссылки на файлы не открываются. Уведомления и письма при заполнении не отправляются.
 */
class DemoSeeder extends Seeder
{
    private const EMAILS = 'pay-%@demo.ru';

    private const PRICE_INDIVIDUAL = 1500;

    private const PRICE_GROUP = 900;

    private User $teacher;

    private User $bio;

    private ?User $admin = null;

    /** @var array<string, User> ученики по ключу: hawa, adam, … */
    private array $s = [];

    /** @var array<string, Room> комнаты по ключу */
    private array $rooms = [];

    /** @var array<string, RoomSchedule[]> правила расписания по ключу комнаты */
    private array $schedules = [];

    /** @var array<int, bool> ученики учителя химии с помесячной оплатой */
    private array $monthly = [];

    /** @var array<int, bool> бесплатные ученики учителя химии */
    private array $free = [];

    /** @var array<int, array<int, PaymentRecord[]>> поурочные начисления: [учитель][ученик][] */
    private array $records = [];

    /** @var array<string, bool> комнаты, где занятия записываются (в таблице комнат такой настройки нет — она в settings_snapshot занятия) */
    private array $recorded = ['hawa' => true, 'ibragim' => true, 'madina' => true, 'ege' => true, 'oge' => true, 'consult' => true, 'bio-hawa' => true];

    /** @var array<string, MeetingSession[]> проведённые занятия по ключу комнаты */
    private array $sessions = [];

    /** @var array<string, Homework> */
    private array $hw = [];

    /** @var array<string, HomeworkSubmission> */
    private array $works = [];

    private array $claims = [];

    private Carbon $historyFrom;

    private bool $s3;

    /** Строятся ли ссылки на файлы S3. Без AWS_URL и бакета материалы, презентации и вложения чата роняют экраны — их не создаём */
    private bool $fileLinks;

    public function run(): void
    {
        // Демо-учителя и ученики не должны появиться на проде даже при случайном запуске
        if (app()->isProduction()) {
            $this->command?->error('Демо-данные не создаются в production.');

            return;
        }

        // Сидер не должен рассылать уведомления, пуши и письма реальным адресам на demo.ru
        Notification::fake();
        Mail::fake();
        mt_srand(2026);

        $this->s3 = filled(config('filesystems.disks.s3.bucket')) && filled(config('filesystems.disks.s3.key'));
        try {
            Storage::disk('s3')->url('demo');
            $this->fileLinks = true;
        } catch (\Throwable) {
            $this->fileLinks = false;
        }
        $this->historyFrom = today()->subWeeks(6)->startOfWeek();
        $this->admin = User::where('role', User::ROLE_ADMIN)->orderBy('id')->first();

        $this->cleanup();
        $this->ensureTariffs();

        $this->teacher = $this->createChemistryTeacher();
        $this->createStudents();
        $this->createChemistryRooms();
        $this->createChemistrySchedules();
        $this->createExceptions();
        $this->createHistory($this->teacher, array_keys($this->schedules));

        $this->bio = $this->createBiologyTeacher();
        $this->createBiologyClasses();

        $this->applyPaymentCases();
        $this->createMonthlyRecords();
        $this->createClaims();

        $this->createHomeworks();
        $this->createMaterials();
        $this->createLessonPlans();
        $this->createRecordings();
        $this->createDeletionRequest();
        $this->createMessages();
        $this->createSupportChat();
        $this->createReviews();
        $this->createSubscriptions();
        $this->createReferrals();
        $this->createNotifications();

        $this->report();
    }

    // ───────────────────────── Очистка ─────────────────────────

    /**
     * Удаляем прошлые демо-данные запросами, без событий моделей: события удаляют файлы из S3
     * (а без настроенного S3 падают), а демо-файлы всё равно перезапишутся по тем же путям.
     */
    private function cleanup(): void
    {
        $ids = User::where('email', 'like', self::EMAILS)->pluck('id');

        DB::table('teacher_applications')->where('email', 'like', self::EMAILS)->delete();

        if ($ids->isEmpty()) {
            return;
        }

        $roomIds = DB::table('rooms')->whereIn('user_id', $ids)->pluck('id');
        $meetingIds = DB::table('rooms')->whereIn('id', $roomIds)->pluck('meeting_id');
        $homeworkIds = DB::table('homeworks')->whereIn('teacher_id', $ids)->pluck('id');
        $submissionIds = DB::table('homework_submissions')
            ->whereIn('homework_id', $homeworkIds)->orWhereIn('student_id', $ids)->pluck('id');
        $chatIds = DB::table('support_chats')->whereIn('user_id', $ids)->pluck('id');

        DB::table('recordings')->whereIn('meeting_id', $meetingIds)->delete();
        DB::table('messages')->whereIn('room_id', $roomIds)->orWhereIn('user_id', $ids)->delete();
        DB::table('rooms')->whereIn('id', $roomIds)->update(['presentations' => null]);
        DB::table('homework_activities')->whereIn('submission_id', $submissionIds)->delete();
        DB::table('homework_submissions')->whereIn('id', $submissionIds)->delete();
        DB::table('homework_student')->whereIn('homework_id', $homeworkIds)->delete();
        DB::table('homeworks')->whereIn('id', $homeworkIds)->delete();
        DB::table('material_room')->whereIn('room_id', $roomIds)->delete();
        DB::table('teacher_materials')->whereIn('teacher_id', $ids)->delete();
        DB::table('material_folders')->whereIn('teacher_id', $ids)->orderByDesc('id')->delete();
        DB::table('notifications')->where('notifiable_type', User::class)->whereIn('notifiable_id', $ids)->delete();
        DB::table('support_messages')->whereIn('support_chat_id', $chatIds)->orWhereIn('user_id', $ids)->delete();
        DB::table('support_chats')->whereIn('id', $chatIds)->delete();
        DB::table('reviews')->whereIn('user_id', $ids)->orWhereIn('teacher_id', $ids)->delete();
        DB::table('payment_claims')->whereIn('teacher_id', $ids)->orWhereIn('student_id', $ids)->delete();
        DB::table('referral_rewards')->whereIn('referrer_id', $ids)->orWhereIn('referred_id', $ids)->delete();
        DB::table('subscription_payments')->whereIn('user_id', $ids)->delete();
        DB::table('subscriptions')->whereIn('user_id', $ids)->delete();
        DB::table('teacher_applications')->whereIn('referred_by_id', $ids)->delete();
        DB::table('users')->whereIn('referred_by_id', $ids)->update(['referred_by_id' => null]);

        // Удаление пользователя каскадом уносит комнаты, расписание, занятия и начисления
        User::whereIn('id', $ids)->get()->each->delete();
    }

    private function ensureTariffs(): void
    {
        if (Tariff::where('slug', 'pro')->doesntExist()) {
            $this->call(TariffSeeder::class);
        }
    }

    // ───────────────────────── Люди ─────────────────────────

    private function createChemistryTeacher(): User
    {
        $teacher = $this->createUser('pay-teacher@demo.ru', 'Евлоев', 'Магомед', 'Ахмедович', User::ROLE_TUTOR, [
            'about' => '<p>Преподаю химию 12 лет: 8 лет в школе, последние 4 года — только частные занятия. Готовлю к ЕГЭ и ОГЭ, веду олимпиадников.</p>'
                . '<p>Занятия строю от задач: сначала разбираем механизм реакции, потом закрепляем на вариантах. После каждого занятия ученик получает задание с проверкой и разбором ошибок.</p>'
                . '<p>Средний балл моих учеников на ЕГЭ в 2026 году — 84, пятеро написали на 90+.</p>',
            'extra_info' => '<p>Окончил химический факультет КБГУ с отличием, магистратура по органической химии.</p>'
                . '<p>Эксперт ЕГЭ по химии с 2021 года. Призёр всероссийского конкурса «Учитель года» в номинации «Естественные науки».</p>',
            'grade' => [8, 9, 10, 11],
            'phone' => '+79287140512',
            'whatsup' => '79287140512',
            'telegram' => 'evloev_himiya',
            'referral_code' => 'himiya' . mt_rand(100, 999),
        ], registeredDaysAgo: 240);

        $teacher->subjects()->sync([Subject::firstOrCreate(['name' => 'Химия'])->id]);
        $teacher->directs()->sync($this->directs(['ЕГЭ', 'ОГЭ', 'Олимпиады', 'ДВИ']));

        // И индивидуальные, и групповые занятия — поурочные: помесячным ученикам тип переопределяется лично
        $this->lessonTypes($teacher, 60, 90);

        return $teacher;
    }

    private function createBiologyTeacher(): User
    {
        $teacher = $this->createUser('pay-teacher-bio@demo.ru', 'Цурова', 'Лиана', 'Руслановна', User::ROLE_TUTOR, [
            'about' => '<p>Учитель биологии. Готовлю к ЕГЭ и поступлению в медицинские вузы: учим не определения, а логику живых систем.</p>'
                . '<p>На каждом занятии — задачи по генетике и работа с рисунками из второй части ЕГЭ.</p>',
            'extra_info' => '<p>Окончила Первый МГМУ им. Сеченова, 6 лет стажа.</p>',
            'grade' => [9, 10, 11],
            'phone' => '+79287140977',
            'whatsup' => '79287140977',
            'telegram' => 'tsurova_bio',
            'is_active' => false, // демо-учитель не нужен в каталоге сайта
        ], registeredDaysAgo: 120);

        $teacher->subjects()->sync([Subject::firstOrCreate(['name' => 'Биология'])->id]);
        $teacher->directs()->sync($this->directs(['ЕГЭ', 'ОГЭ']));
        $this->lessonTypes($teacher, 60, 90, individual: 1800, group: 1000);

        return $teacher;
    }

    private function lessonTypes(User $teacher, int $individualMinutes, int $groupMinutes, int $individual = self::PRICE_INDIVIDUAL, int $group = self::PRICE_GROUP): void
    {
        foreach ([[LessonType::TYPE_INDIVIDUAL, $individual, $individualMinutes, 2], [LessonType::TYPE_GROUP, $group, $groupMinutes, 2]] as [$type, $price, $minutes, $perWeek]) {
            LessonType::create([
                'user_id' => $teacher->id,
                'type' => $type,
                'price' => $price,
                'payment_type' => PaymentRecord::TYPE_PER_LESSON,
                'payment_due_days' => PaymentRecordService::PER_LESSON_DUE_DAYS,
                'payment_due_day' => PaymentRecordService::MONTHLY_DUE_DAY,
                'count_per_week' => $perWeek,
                'duration' => $minutes,
            ]);
        }
    }

    private function directs(array $names): array
    {
        return collect($names)->map(fn (string $name) => Direct::firstOrCreate(['name' => $name])->id)->all();
    }

    private function createStudents(): void
    {
        // ключ => [почта, фамилия, имя, класс, в списке с (недель назад)]
        $list = [
            'hawa' => ['pay-paid@demo.ru', 'Оздоева', 'Хава', 11, 30],
            'adam' => ['pay-pending@demo.ru', 'Мальсагов', 'Адам', 11, 28],
            'ibragim' => ['pay-overdue@demo.ru', 'Костоев', 'Ибрагим', 9, 20],
            'musa' => ['pay-blocked@demo.ru', 'Плиев', 'Муса', 9, 18],
            'marem' => ['pay-monthly-paid@demo.ru', 'Аушева', 'Марем', 11, 26],
            'isa' => ['pay-monthly-overdue@demo.ru', 'Цечоев', 'Иса', 9, 16],
            'aminat' => ['pay-free@demo.ru', 'Котиева', 'Аминат', 8, 3],
            'madina' => ['pay-override@demo.ru', 'Барахоева', 'Мадина', 11, 24],
            'tanzila' => ['pay-extra-1@demo.ru', 'Хамхоева', 'Танзила', 11, 22],
            'ahmed' => ['pay-extra-2@demo.ru', 'Точиев', 'Ахмед', 11, 22],
            'zaira' => ['pay-extra-3@demo.ru', 'Медова', 'Заира', 11, 14],
            'alihan' => ['pay-extra-4@demo.ru', 'Албаков', 'Алихан', 9, 12],
            'luiza' => ['pay-extra-5@demo.ru', 'Гагиева', 'Луиза', 9, 10],
            'daud' => ['pay-extra-6@demo.ru', 'Ужахов', 'Дауд', 10, 8],
            'ramzan' => ['pay-new@demo.ru', 'Гадаборшев', 'Рамзан', 10, 0],
        ];

        $i = 0;
        foreach ($list as $key => [$email, $last, $first, $grade, $weeks]) {
            $phone = '+7928' . str_pad((string) (3104400 + ++$i * 137), 7, '0', STR_PAD_LEFT);
            $student = $this->createUser($email, $last, $first, null, User::ROLE_STUDENT, [
                'grade' => [$grade],
                'phone' => $phone,
                'whatsup' => ltrim($phone, '+'),
                'telegram' => $i % 3 === 0 ? null : Str::slug(Str::transliterate($first . '_' . $last), '_'),
            ], registeredDaysAgo: $weeks * 7 + 2);

            $this->s[$key] = $student;
            $this->attachStudent($this->teacher, $student, $key === 'ramzan' ? now()->subDays(2) : now()->subWeeks($weeks));
        }

        // Помесячные (личное переопределение) и бесплатный ученик
        foreach (['marem', 'isa', 'madina'] as $key) {
            $this->teacher->students()->updateExistingPivot($this->s[$key]->id, ['payment_type_override' => PaymentRecord::TYPE_MONTHLY]);
            $this->monthly[$this->s[$key]->id] = true;
        }
        $this->teacher->students()->updateExistingPivot($this->s['aminat']->id, ['is_free' => true]);
        $this->free[$this->s['aminat']->id] = true;
    }

    private function createUser(string $email, string $last, string $first, ?string $middle, string $role, array $attrs = [], int $registeredDaysAgo = 30): User
    {
        $user = User::create($attrs + [
            'last_name' => $last,
            'first_name' => $first,
            'middle_name' => $middle,
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'is_profile_completed' => true,
        ]);

        $registered = now()->subDays($registeredDaysAgo)->setTime(mt_rand(9, 21), mt_rand(0, 59));
        $user->forceFill([
            'email_verified_at' => $registered,
            'created_at' => $registered,
            'last_login_at' => now()->subMinutes(mt_rand(20, 60 * 30)),
        ])->saveQuietly();

        return $user;
    }

    /** В списке учеников «с» — дата из teacher_student.created_at */
    private function attachStudent(User $teacher, User $student, Carbon $since): void
    {
        $teacher->students()->attach($student->id, ['created_at' => $since, 'updated_at' => $since]);
    }

    // ───────────────────────── Комнаты и расписание ─────────────────────────

    private function createChemistryRooms(): void
    {
        $individual = [
            'hawa' => 'Органическая химия — Хава',
            'adam' => 'Неорганическая химия — Адам',
            'ibragim' => 'Подготовка к ОГЭ — Ибрагим',
            'musa' => 'Химия ОГЭ — Муса',
            'madina' => 'Химия ЕГЭ — Мадина',
            'aminat' => 'Химия с нуля — Аминат',
            'tanzila' => 'Задачи на растворы — Танзила',
            'ahmed' => 'Окислительно-восстановительные реакции — Ахмед',
            'zaira' => 'Химическая кинетика — Заира',
            'alihan' => 'Термохимия — Алихан',
            'luiza' => 'Электролиз — Луиза',
            'daud' => 'Качественные реакции — Дауд',
            'ramzan' => 'Химия ЕГЭ — Рамзан',
        ];

        foreach ($individual as $key => $name) {
            $this->rooms[$key] = $this->createRoom($this->teacher, $name, [$this->s[$key]]);
        }

        // Брату Дауда — скидка: персональная цена в его комнате
        $this->rooms['daud']->participants()->updateExistingPivot($this->s['daud']->id, [
            'custom_price' => 1200,
            'price_note' => 'Скидка: занимается и старший брат',
        ]);

        $ege = ['hawa', 'adam', 'marem', 'madina', 'tanzila', 'ahmed', 'zaira'];
        $oge = ['ibragim', 'musa', 'isa', 'alihan', 'luiza'];

        $this->rooms['ege'] = $this->createRoom($this->teacher, 'Химия ЕГЭ — группа 11 класса', $this->pick($ege), [
            'welcome_msg' => 'Добрый вечер! Включите камеры и откройте таблицу растворимости.',
        ]);
        $this->rooms['oge'] = $this->createRoom($this->teacher, 'Химия ОГЭ — группа 9 класса', $this->pick($oge));
        // Консультация бесплатная: цена 0 — начислений по ней нет
        $this->rooms['consult'] = $this->createRoom($this->teacher, 'Консультация перед ОГЭ', $this->pick($oge), ['base_price' => 0]);
        $this->rooms['trial'] = $this->createRoom($this->teacher, 'Пробный ЕГЭ по химии', $this->pick($ege));

        // Презентация, загруженная в класс группы ЕГЭ
        if (! $this->fileLinks) {
            return;
        }

        $path = 'demo/presentations/ege-rastvory.pdf';
        $this->demoFile($path, 'Растворы и электролитическая диссоциация', [
            'Слайд 1. Растворы: массовая доля и молярная концентрация',
            'Слайд 2. Сильные и слабые электролиты',
            'Слайд 3. Реакции ионного обмена',
            'Слайд 4. Задачи с разбором',
        ]);
        $this->rooms['ege']->updateQuietly([
            'presentations' => [$path],
            'presentation_names' => [$path => 'Растворы — презентация.pdf'],
        ]);
    }

    /** @return User[] */
    private function pick(array $keys): array
    {
        return array_map(fn (string $key) => $this->s[$key], $keys);
    }

    /** @param User[] $students */
    private function createRoom(User $teacher, string $name, array $students, array $attrs = []): Room
    {
        $room = Room::create($attrs + [
            'user_id' => $teacher->id,
            'name' => $name,
            'type' => 'individual',
            'meeting_id' => (string) Str::uuid(),
            'moderator_pw' => Str::random(8),
            'attendee_pw' => Str::random(8),
        ]);

        $since = $this->historyFrom->copy()->subDays(3);
        $room->participants()->attach(collect($students)->mapWithKeys(fn (User $s) => [
            $s->id => ['created_at' => $since, 'updated_at' => $since],
        ])->all());
        $room->forceFill(['created_at' => $since])->save(); // RoomObserver пересчитает тип по числу участников

        return $room->refresh();
    }

    private function createChemistrySchedules(): void
    {
        // [комната, дни недели (1 = пн … 6 = сб), время, минут, с какой даты]
        $timetable = [
            ['ege', [1, 4], '17:00', 90],
            ['oge', [2, 5], '16:00', 90],
            ['hawa', [1], '14:00', 60],
            ['hawa', [6], '11:00', 60],
            ['adam', [1], '15:00', 60],
            ['ibragim', [2], '14:00', 60],
            ['ibragim', [6], '12:00', 60],
            ['musa', [2], '15:00', 60],
            ['madina', [3], '14:00', 60],
            ['aminat', [3], '15:00', 60, today()->subWeeks(3)->startOfWeek()],
            ['tanzila', [3], '16:00', 60],
            ['ahmed', [3], '17:00', 60],
            ['zaira', [4], '14:00', 60],
            ['alihan', [4], '15:00', 60],
            ['luiza', [5], '14:00', 60],
            ['daud', [5], '15:00', 60],
            // Новый ученик: первое занятие на следующей неделе — в расписании оно помечается как пробное
            ['ramzan', [4], '18:30', 60, today()->next(Carbon::MONDAY)],
        ];

        foreach ($timetable as $row) {
            [$key, $days, $time, $minutes] = $row;
            $this->schedules[$key][] = $this->weekly($this->rooms[$key], $days, $time, $minutes, $row[4] ?? $this->historyFrom);
        }

        // Разовые: прошедшая консультация (воскресенье — день без занятий) и предстоящий пробный экзамен
        $this->schedules['consult'][] = $this->once($this->rooms['consult'], today()->subDays(7)->previous(Carbon::SUNDAY)->setTime(12, 0), 90);
        $this->schedules['trial'][] = $this->once($this->rooms['trial'], today()->addDays(7)->next(Carbon::SUNDAY)->setTime(10, 0), 180);
    }

    private function weekly(Room $room, array $days, string $time, int $minutes, Carbon $from): RoomSchedule
    {
        return RoomSchedule::create([
            'room_id' => $room->id,
            'type' => 'recurring',
            'recurrence_type' => 'weekly',
            'recurrence_days' => $days,
            'recurrence_time' => $time,
            'start_date' => $from->toDateString(),
            'end_date' => null,
            'duration_minutes' => $minutes,
            'is_active' => true,
        ]);
    }

    private function once(Room $room, Carbon $at, int $minutes): RoomSchedule
    {
        return RoomSchedule::create([
            'room_id' => $room->id,
            'type' => 'once',
            'scheduled_at' => $at,
            'duration_minutes' => $minutes,
            'is_active' => true,
        ]);
    }

    /** Отмены и переносы: ближайшие (видны в расписании и у ученика) и прошедшие (видны в истории) */
    private function createExceptions(): void
    {
        // Ближайшее занятие Адама отменено
        $this->exception('adam', 0, 1, RoomScheduleException::STATUS_CANCELLED, 'Ученик уезжает на региональную олимпиаду');

        // Ближайшее вторничное занятие Ибрагима перенесено на среду, 18:00
        $this->exception('ibragim', 0, 1, RoomScheduleException::STATUS_MOVED, 'Ученик попросил перенести: во вторник пробник в школе', addDays: 1, time: '18:00');

        // В прошлом: Муса болел, Луиза пропустила, занятие Танзилы переносили на четверг
        $this->exception('musa', 0, -2, RoomScheduleException::STATUS_CANCELLED, 'Ученик заболел');
        $this->exception('luiza', 0, -3, RoomScheduleException::STATUS_CANCELLED, 'Ученица уезжала на соревнования');
        $this->exception('tanzila', 0, -2, RoomScheduleException::STATUS_MOVED, 'Попросила перенести из-за школьной олимпиады', addDays: 1, time: '18:00');
    }

    /**
     * $nth > 0 — n-е предстоящее вхождение правила, $nth < 0 — n-е с конца прошедшее.
     */
    private function exception(string $key, int $scheduleIndex, int $nth, string $status, string $reason, int $addDays = 0, ?string $time = null): void
    {
        $schedule = $this->schedules[$key][$scheduleIndex];
        $occurrences = collect($schedule->rawOccurrences($this->historyFrom, now()->addWeeks(3)));

        $original = $nth > 0
            ? $occurrences->filter(fn (Carbon $at) => $at->isFuture())->values()->get($nth - 1)
            : $occurrences->filter(fn (Carbon $at) => $at->copy()->addMinutes($schedule->minutes())->isPast())->values()->reverse()->values()->get(-$nth - 1);

        if (! $original) {
            return;
        }

        $moved = $status === RoomScheduleException::STATUS_MOVED
            ? $original->copy()->addDays($addDays)->setTimeFromTimeString($time)
            : null;

        RoomScheduleException::create([
            'room_id' => $schedule->room_id,
            'room_schedule_id' => $schedule->id,
            'original_date' => $original->toDateString(),
            'original_starts_at' => $original,
            'status' => $status,
            'starts_at' => $moved,
            'duration_minutes' => $moved ? $schedule->minutes() : null,
            'reason' => $reason,
            'notified' => true,
            'created_by' => $this->teacher->id,
        ]);
    }

    // ───────────────────────── Проведённые занятия и начисления ─────────────────────────

    /**
     * Все прошедшие вхождения расписания становятся проведёнными занятиями — как в жизни:
     * отменённые пропускаются, перенесённые проходят в новое время. Одно занятие Дауда
     * намеренно не проведено (в расписании «Не состоялось»).
     */
    private function createHistory(User $teacher, array $roomKeys): void
    {
        $skipped = false;

        foreach ($roomKeys as $key) {
            foreach ($this->schedules[$key] ?? [] as $schedule) {
                $exceptions = $schedule->exceptions()->get()->keyBy(fn (RoomScheduleException $e) => $e->original_date->toDateString());

                $past = collect($schedule->rawOccurrences($this->historyFrom, now()))
                    ->map(function (Carbon $original) use ($exceptions, $schedule) {
                        $e = $exceptions->get($original->toDateString());

                        return match (true) {
                            $e?->isCancelled() => null,
                            $e?->isMoved() => [$e->starts_at->copy(), $e->duration_minutes ?: $schedule->minutes()],
                            default => [$original, $schedule->minutes()],
                        };
                    })
                    ->filter(fn ($o) => $o && $o[0]->copy()->addMinutes($o[1])->isPast())
                    ->values();

                foreach ($past as $i => [$start, $minutes]) {
                    if ($key === 'daud' && ! $skipped && $i === $past->count() - 3) {
                        $skipped = true;

                        continue;
                    }

                    $this->holdLesson($teacher, $key, $start, $minutes);
                }
            }
        }
    }

    private function holdLesson(User $teacher, string $key, Carbon $scheduled, int $minutes): MeetingSession
    {
        $room = $this->rooms[$key];
        $isRecorded = isset($this->recorded[$key]);
        $students = $room->participants()->get();
        $isGroup = $students->count() > 1;

        $start = $scheduled->copy()->addMinutes(mt_rand(0, 3));
        $end = $start->copy()->addMinutes($minutes + mt_rand(-3, 6));

        $attended = $students->filter(function (User $s) use ($isGroup) {
            $missChance = match (true) {
                $s->id === ($this->s['zaira']->id ?? 0) => 20,
                $isGroup => 8,
                default => 0,
            };

            return mt_rand(1, 100) > $missChance;
        });

        $participants = [[
            'user_id' => (string) $teacher->id,
            'full_name' => $teacher->name,
            'role' => 'MODERATOR',
            'joined_at' => $start->copy()->subMinutes(2)->toIso8601String(),
            'last_joined_at' => $start->copy()->subMinutes(2)->toIso8601String(),
            'left_at' => $end->toIso8601String(),
            'join_count' => 1,
            'talking_time' => (int) ($minutes * 60 * 0.55),
            'webcam_time' => $minutes * 60,
            'message_count' => mt_rand(1, 6),
            'emoji_count' => 0,
            'raise_hand_count' => 0,
        ]];

        foreach ($attended as $s) {
            $joined = $start->copy()->addMinutes(mt_rand(-1, $isGroup ? 7 : 3));
            $left = $end->copy()->subMinutes(mt_rand(0, 100) > 85 ? mt_rand(10, 25) : 0);
            $inClass = max(60, $joined->diffInSeconds($left));
            $rejoined = mt_rand(1, 100) > 88;

            $participants[] = [
                'user_id' => (string) $s->id,
                'full_name' => $s->name,
                'role' => 'VIEWER',
                'joined_at' => $joined->toIso8601String(),
                'last_joined_at' => ($rejoined ? $joined->copy()->addMinutes(mt_rand(15, 30)) : $joined)->toIso8601String(),
                'left_at' => $left->toIso8601String(),
                'join_count' => $rejoined ? 2 : 1,
                'talking_time' => (int) ($inClass * mt_rand($isGroup ? 3 : 15, $isGroup ? 18 : 40) / 100),
                'webcam_time' => mt_rand(1, 100) > 20 ? (int) ($inClass * mt_rand(60, 100) / 100) : 0,
                'message_count' => mt_rand(0, $isGroup ? 6 : 3),
                'emoji_count' => mt_rand(0, 3),
                'raise_hand_count' => $isGroup ? mt_rand(0, 3) : 0,
            ];
        }

        $prices = $students->mapWithKeys(fn (User $s) => [$s->id => $room->getEffectivePrice($s->id) ?? 0]);
        $attendedIds = $attended->pluck('id')->flip();

        $session = MeetingSession::create([
            'user_id' => $teacher->id,
            'room_id' => $room->id,
            'meeting_id' => $room->meeting_id,
            'internal_meeting_id' => sha1($room->meeting_id) . '-' . ($start->getTimestamp() * 1000),
            'started_at' => $start,
            'ended_at' => $end,
            'status' => 'completed',
            'participant_count' => $attended->count() + 1,
            'analytics_data' => [
                'participants' => $participants,
                'poll_count' => $isGroup ? mt_rand(0, 3) : 0,
                'record_prop' => $isRecorded,
            ],
            'settings_snapshot' => ['record' => $isRecorded, 'autoStartRecording' => $isRecorded],
            'pricing_snapshot' => [
                'payment_type' => PaymentRecord::TYPE_PER_LESSON,
                'base_price' => $room->base_price,
                'room_type' => $room->type,
                'participants' => $students->map(fn (User $s) => [
                    'user_id' => $s->id,
                    'name' => $s->name,
                    'price' => $prices[$s->id],
                    'attended' => isset($attendedIds[$s->id]),
                ])->values()->all(),
                'total_cost' => $attended->sum(fn (User $s) => $prices[$s->id]),
            ],
        ]);
        $session->forceFill(['created_at' => $start, 'updated_at' => $end])->saveQuietly();
        $session->setRelation('room', $room);

        $this->sessions[$key][] = $session;

        // Поурочные начисления посетившим — кроме бесплатных и помесячных, как при завершении реального занятия.
        // Пока все «оплачено»: сценарии долгов расставляет applyPaymentCases()
        foreach ($attended as $s) {
            if ($prices[$s->id] === 0 || ($teacher->is($this->teacher) && (isset($this->free[$s->id]) || isset($this->monthly[$s->id])))) {
                continue;
            }

            $record = PaymentRecord::create([
                'teacher_id' => $teacher->id,
                'student_id' => $s->id,
                'type' => PaymentRecord::TYPE_PER_LESSON,
                'meeting_session_id' => $session->id,
                'status' => PaymentRecord::STATUS_PAID,
                'due_date' => $start->copy()->addDays(PaymentRecordService::PER_LESSON_DUE_DAYS)->toDateString(),
                'paid_at' => $this->notFuture($start->copy()->addDays(mt_rand(0, 2))->setTime(mt_rand(9, 22), mt_rand(0, 59))),
                'marked_by' => $teacher->id,
            ]);
            $record->forceFill(['created_at' => $end, 'updated_at' => $end])->saveQuietly();
            $record->setRelation('meetingSession', $session);

            $this->records[$teacher->id][$s->id][] = $record;
        }

        return $session;
    }

    private function notFuture(Carbon $at): Carbon
    {
        return $at->isFuture() ? now()->subMinutes(mt_rand(5, 90)) : $at;
    }

    /** Сценарии оплаты поверх истории, где всё оплачено */
    private function applyPaymentCases(): void
    {
        // Начисления копились по комнатам — дальше нужен порядок занятий
        foreach ($this->records as $teacherId => $byStudent) {
            foreach ($byStudent as $studentId => $records) {
                usort($records, fn (PaymentRecord $a, PaymentRecord $b) => $a->meetingSession->started_at <=> $b->meetingSession->started_at);
                $this->records[$teacherId][$studentId] = $records;
            }
        }

        foreach ($this->records as $teacherId => $byStudent) {
            foreach ($byStudent as $studentId => $records) {
                // Свежие начисления (срок ещё не наступил) не оплачены — кроме Хавы, у которой оплачено всё
                if ($studentId === $this->s['hawa']->id && $teacherId === $this->teacher->id) {
                    continue;
                }

                foreach ($records as $record) {
                    if ($record->due_date->gte(today())) {
                        $this->unpay($record);
                    }
                }
            }
        }

        $chem = $this->records[$this->teacher->id];

        // Хава: одно начисление отменено учителем (занятие закончили раньше из-за связи)
        $hawa = $chem[$this->s['hawa']->id];
        $cancelled = $hawa[count($hawa) - 3] ?? null;
        $cancelled?->forceFill(['status' => PaymentRecord::STATUS_CANCELLED, 'paid_at' => null])->saveQuietly();

        // Ибрагим: просрочка и одно-два занятия после срока — предупреждение, занятия ещё открыты
        $this->debtFrom($chem[$this->s['ibragim']->id], 1);

        // Муса: после срока посетил три занятия — занятия учителя закрыты
        $this->debtFrom($chem[$this->s['musa']->id], PaymentRecordService::BLOCK_AFTER_LESSONS);

        // Луиза однажды оплатила позже срока
        $luiza = $chem[$this->s['luiza']->id];
        $late = $luiza[count($luiza) - 4] ?? null;
        $late?->forceFill(['paid_at' => $this->notFuture($late->due_date->copy()->addDays(2)->setTime(19, 40))])->saveQuietly();
    }

    /**
     * Долг с того начисления, после срока которого ученик посетил не меньше $lessons занятий
     * (так считает PaymentRecordService::debtStatus). Все начисления с него — не оплачены.
     *
     * @param PaymentRecord[] $records в порядке занятий
     */
    private function debtFrom(array $records, int $lessons): void
    {
        $ends = collect($records)->map(fn (PaymentRecord $r) => $r->meetingSession->ended_at);

        for ($i = count($records) - 1; $i >= 0; $i--) {
            $due = $records[$i]->due_date;
            if ($due->gte(today())) {
                continue;
            }

            $after = $ends->filter(fn (Carbon $end) => $end->gt($due->copy()->endOfDay()))->count();
            if ($after >= $lessons) {
                foreach (array_slice($records, $i) as $record) {
                    $this->unpay($record, reminded: $record->due_date->lt(today()));
                }

                return;
            }
        }
    }

    private function unpay(PaymentRecord $record, bool $reminded = false): void
    {
        $record->forceFill([
            'status' => PaymentRecord::STATUS_UNPAID,
            'paid_at' => null,
            'marked_by' => null,
            'reminded_at' => $reminded ? $record->due_date->copy()->addDay()->setTime(10, 0) : null,
        ])->saveQuietly();
    }

    /** Помесячные счета: Марем всё оплатила, у Исы просрочен текущий месяц, у Мадины счёт ждёт оплаты */
    private function createMonthlyRecords(): void
    {
        $amounts = [
            'marem' => self::PRICE_GROUP * 2 * LessonType::WEEKS_PER_MONTH,
            'isa' => self::PRICE_GROUP * 2 * LessonType::WEEKS_PER_MONTH,
            'madina' => (self::PRICE_INDIVIDUAL + self::PRICE_GROUP * 2) * LessonType::WEEKS_PER_MONTH,
        ];

        foreach ($amounts as $key => $amount) {
            $student = $this->s[$key];
            $month = $this->historyFrom->copy()->startOfMonth();

            while ($month->lte(today())) {
                $isCurrent = $month->isSameMonth(today());
                $due = $month->copy()->addDays(PaymentRecordService::MONTHLY_DUE_DAY - 1);
                $status = PaymentRecord::STATUS_PAID;

                if ($isCurrent && $key === 'isa') {
                    // Срок — перед последним посещённым занятием: одно занятие с долгом, предупреждение без блокировки
                    $visits = collect($this->sessions['oge'] ?? [])
                        ->filter(fn (MeetingSession $s) => $s->attendedBy($student->id))
                        ->map(fn (MeetingSession $s) => $s->started_at->copy()->startOfDay())
                        ->unique()->values();
                    $due = $visits->count() >= 2 ? $visits[$visits->count() - 2] : today()->subDay();
                    $status = PaymentRecord::STATUS_UNPAID;
                } elseif ($isCurrent && $key === 'madina') {
                    $due = today()->addDays(2);
                    $status = PaymentRecord::STATUS_UNPAID;
                }

                $record = PaymentRecord::create([
                    'teacher_id' => $this->teacher->id,
                    'student_id' => $student->id,
                    'type' => PaymentRecord::TYPE_MONTHLY,
                    'period' => $month->format('Y-m'),
                    'amount' => $amount,
                    'status' => $status,
                    'due_date' => $due->toDateString(),
                    'paid_at' => $status === PaymentRecord::STATUS_PAID ? $this->notFuture($month->copy()->addDays(mt_rand(1, 3))->setTime(20, 10)) : null,
                    'marked_by' => $status === PaymentRecord::STATUS_PAID ? $this->teacher->id : null,
                    'reminded_at' => $status === PaymentRecord::STATUS_UNPAID && $due->lt(today()) ? now()->subDay() : null,
                ]);
                $created = $this->notFuture($due->copy()->subDays(PaymentRecordService::MONTHLY_MIN_DUE_DAYS)->setTime(9, 0));
                $record->forceFill(['created_at' => $created, 'updated_at' => $created])->saveQuietly();

                $month->addMonth();
            }
        }
    }

    /** Заявки «Я оплатил»: на проверке (Ибрагим), отклонённая (Муса), подтверждённые (Хава, Танзила) */
    private function createClaims(): void
    {
        $chem = fn (string $key) => collect($this->records[$this->teacher->id][$this->s[$key]->id] ?? []);

        $unpaid = $chem('ibragim')->filter(fn (PaymentRecord $r) => $r->status === PaymentRecord::STATUS_UNPAID);
        if ($unpaid->isNotEmpty()) {
            $this->claims['ibragim'] = $this->claim('ibragim', $unpaid, PaymentClaim::STATUS_PENDING, now()->subHours(20),
                'Перевёл на карту по номеру телефона, чек прикладываю.', 'Чек Сбербанк.jpg');
        }

        $musa = $chem('musa')->filter(fn (PaymentRecord $r) => $r->status === PaymentRecord::STATUS_UNPAID && $r->due_date->lt(today()));
        if ($musa->isNotEmpty()) {
            $this->claims['musa'] = $this->claim('musa', $musa, PaymentClaim::STATUS_REJECTED, now()->subDays(3)->setTime(21, 5),
                'Мама перевела, вот скриншот.', 'Скриншот перевода.png',
                reject: 'Перевод пока не пришёл. Пришлите, пожалуйста, чек из приложения банка.');
        }

        $paid = $chem('hawa')->filter(fn (PaymentRecord $r) => $r->status === PaymentRecord::STATUS_PAID)->slice(-2);
        $this->claim('hawa', $paid, PaymentClaim::STATUS_CONFIRMED, $paid->last()?->paid_at?->copy()->subHours(3) ?? now()->subDays(2),
            'Оплатила два последних занятия.', 'Чек Т-Банк.jpg');

        $paid = $chem('tanzila')->filter(fn (PaymentRecord $r) => $r->status === PaymentRecord::STATUS_PAID)->slice(0, 3);
        $this->claim('tanzila', $paid, PaymentClaim::STATUS_CONFIRMED, $paid->last()?->paid_at?->copy()->subHours(5) ?? now()->subWeeks(4),
            null, 'Перевод.jpg');
    }

    private function claim(string $key, $records, string $status, Carbon $at, ?string $comment, string $fileName, ?string $reject = null): ?PaymentClaim
    {
        if ($records->isEmpty()) {
            return null;
        }

        $student = $this->s[$key];
        $amount = (int) $records->sum(fn (PaymentRecord $r) => $r->amount());
        $path = "demo/payment-claims/{$key}-" . $at->format('Ymd') . '.' . pathinfo($fileName, PATHINFO_EXTENSION);
        $size = $this->demoFile($path, 'Перевод выполнен', [
            'Сумма: ' . Money::format($amount),
            'Получатель: Магомед Ахмедович Е.',
            'Отправитель: ' . $student->first_name . ' ' . mb_substr($student->last_name, 0, 1) . '.',
            'Дата: ' . $at->format('d.m.Y H:i'),
            'Статус: исполнен',
        ], receipt: true, public: false);

        $claim = PaymentClaim::create([
            'teacher_id' => $this->teacher->id,
            'student_id' => $student->id,
            'status' => $status,
            'comment' => $comment,
            'files' => [['path' => $path, 'name' => $fileName, 'size' => $size]],
            'amount' => $amount,
            'reject_reason' => $reject,
            'decided_at' => $status === PaymentClaim::STATUS_PENDING ? null : $this->notFuture($at->copy()->addHours(mt_rand(2, 10))),
        ]);
        $claim->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();
        $claim->records()->attach($records->pluck('id')->all());

        return $claim;
    }

    // ───────────────────────── Учитель биологии ─────────────────────────

    private function createBiologyClasses(): void
    {
        foreach (['hawa' => 10, 'adam' => 6, 'marem' => 6] as $key => $weeks) {
            $this->attachStudent($this->bio, $this->s[$key], now()->subWeeks($weeks));
        }

        $this->rooms['bio-hawa'] = $this->createRoom($this->bio, 'Биология ЕГЭ — Хава', [$this->s['hawa']]);
        $this->rooms['bio-group'] = $this->createRoom($this->bio, 'Биология ЕГЭ — мини-группа', $this->pick(['hawa', 'adam', 'marem']));

        $this->schedules['bio-hawa'][] = $this->weekly($this->rooms['bio-hawa'], [4], '19:00', 60, today()->subWeeks(4)->startOfWeek());
        $this->schedules['bio-group'][] = $this->weekly($this->rooms['bio-group'], [6], '14:00', 90, today()->subWeeks(4)->startOfWeek());

        $this->createHistory($this->bio, ['bio-hawa', 'bio-group']);
    }

    // ───────────────────────── Задания ─────────────────────────

    private function createHomeworks(): void
    {
        $s = $this->s;
        $ege = $this->rooms['ege'];
        $oge = $this->rooms['oge'];

        // 1. Групповое ДЗ, срок прошёл 5 дней назад — полный набор статусов
        $hw = $this->hw['rastvory'] = $this->homework($this->teacher, $ege, [
            'type' => Homework::TYPE_HOMEWORK,
            'title' => 'Задачи на растворы: массовая доля и молярная концентрация',
            'description' => '<p>Решите задачи 1–8 из файла. В каждой задаче запишите дано, формулы и ответ с единицами измерения.</p><p>Задачи 7 и 8 — повышенного уровня, за них даётся по 2 балла.</p>',
            'max_score' => 10,
        ], 12, -5, $ege->participants, files: ['Задачи на растворы.pdf']);

        $this->submit($hw, $s['hawa'], HomeworkSubmission::STATUS_GRADED, ['days_ago' => 7, 'grade' => 9, 'photos' => 2,
            'content' => 'Решения во вложении. В задаче 8 не уверена в округлении.',
            'feedback' => 'Отлично! В задаче 8 округление верное, но потеряна единица измерения в ответе.']);
        $this->submit($hw, $s['adam'], HomeworkSubmission::STATUS_GRADED, ['days_ago' => 5, 'grade' => 6, 'photos' => 1,
            'content' => 'Сделал 1–6, седьмую и восьмую не успел.',
            'feedback' => 'Задачи 1–6 решены верно. Разберём 7 и 8 на занятии — там нужна формула разбавления.']);
        $this->submit($hw, $s['marem'], HomeworkSubmission::STATUS_SUBMITTED, ['days_ago' => 1, 'photos' => 3,
            'content' => 'Прошу прощения за опоздание, болела. Все задачи решены.']);
        $this->works['madina-rastvory'] = $this->submit($hw, $s['madina'], HomeworkSubmission::STATUS_REVISION_REQUESTED, ['days_ago' => 6, 'photos' => 2,
            'content' => 'Решила все, кроме 4-й — не поняла условие.',
            'feedback' => 'В задачах 2 и 5 перепутана массовая доля с мольной. Перерешайте их и задачу 4 — условие разобрали в чате.']);
        $this->submit($hw, $s['tanzila'], HomeworkSubmission::STATUS_SUBMITTED, ['days_ago' => 2, 'resubmitted' => true, 'photos' => 1,
            'content' => 'Исправила задачи 3 и 6, как вы просили.',
            'feedback' => 'В 3 и 6 ошибка в переводе граммов в моли — пересчитайте молярные массы.']);
        $this->submit($hw, $s['ahmed'], HomeworkSubmission::STATUS_GRADED, ['days_ago' => 8, 'grade' => 10, 'photos' => 2,
            'content' => 'Готово, решения в файле.',
            'feedback' => 'Безупречно. Задачу 8 можно было решить короче через пропорцию, покажу на занятии.']);
        // Заира не сдала — срок прошёл

        // 2. Пробник ОГЭ для группы 9 класса, срок прошёл 2 дня назад
        $hw = $this->hw['probnik'] = $this->homework($this->teacher, $oge, [
            'type' => Homework::TYPE_PRACTICE,
            'title' => 'Пробник ОГЭ: вариант 3 (задания 1–19)',
            'description' => '<p>Решите вариант целиком за 120 минут без справочных материалов, кроме таблицы Менделеева и таблицы растворимости.</p><p>Сфотографируйте бланк ответов и развёрнутые решения заданий 17–19.</p>',
            'max_score' => 40,
        ], 9, -2, $oge->participants, files: ['ОГЭ вариант 3.pdf', 'Бланк ответов.pdf']);

        $this->works['ibragim-probnik'] = $this->submit($hw, $s['ibragim'], HomeworkSubmission::STATUS_GRADED, ['days_ago' => 3, 'grade' => 27, 'photos' => 3,
            'content' => 'Уложился в 110 минут. Задание 19 не дорешал.',
            'feedback' => 'Тестовая часть — 22 из 24, хорошо. В 17 не уравнена ОВР, в 19 нет расчёта по второму уравнению.']);
        $this->submit($hw, $s['isa'], HomeworkSubmission::STATUS_SUBMITTED, ['days_ago' => 2, 'photos' => 2,
            'content' => 'Решал два дня по частям, время не засекал.']);
        $this->submit($hw, $s['alihan'], HomeworkSubmission::STATUS_GRADED, ['days_ago' => 4, 'grade' => 35, 'photos' => 3,
            'content' => 'Готово. Было сложно с 18-м заданием.',
            'feedback' => 'Отличный результат. В 18 не хватило одного качественного признака — осадок BaSO₄ белый.']);
        $this->submit($hw, $s['luiza'], HomeworkSubmission::STATUS_REVISION_REQUESTED, ['days_ago' => 3, 'photos' => 1,
            'content' => 'Сдаю только тестовую часть, 17–19 не успела.',
            'feedback' => 'Тестовая часть принята (20 из 24). Дорешайте 17–19 и пришлите — без них балл не выставляю.']);
        // Муса не сдал

        // 3. Контрольная для группы ЕГЭ, срок — завтра: кто-то уже сдал, большинство ещё нет
        $hw = $this->hw['kontrolnaya'] = $this->homework($this->teacher, $ege, [
            'type' => Homework::TYPE_EXAM,
            'title' => 'Контрольная работа по теме «Растворы и электролитическая диссоциация»',
            'description' => '<p>Контрольная по итогам блока. 6 заданий, из них 2 — задачи. Фотографию работы загрузите одним PDF.</p>',
            'max_score' => 20,
        ], 3, 1, $ege->participants, files: ['Контрольная — растворы.pdf']);

        $this->submit($hw, $s['ahmed'], HomeworkSubmission::STATUS_SUBMITTED, ['days_ago' => 1, 'pdf' => true,
            'content' => 'Сдаю заранее — завтра не будет интернета.']);
        $this->submit($hw, $s['hawa'], HomeworkSubmission::STATUS_SUBMITTED, ['days_ago' => 0, 'pdf' => true,
            'content' => 'Готово.']);

        // 4. Индивидуальное ДЗ, срок через 3 дня — сдано досрочно, ждёт проверки
        $hw = $this->hw['izomery'] = $this->homework($this->teacher, $this->rooms['hawa'], [
            'type' => Homework::TYPE_HOMEWORK,
            'title' => 'Изомерия алканов: составить все изомеры C₆H₁₄ и C₇H₁₆',
            'description' => '<p>Нарисуйте структурные формулы всех изомеров и назовите их по номенклатуре IUPAC. Для C₆H₁₄ их 5, для C₇H₁₆ — 9.</p>',
            'max_score' => 10,
        ], 2, 3, [$s['hawa']]);

        $this->works['hawa-izomery'] = $this->submit($hw, $s['hawa'], HomeworkSubmission::STATUS_SUBMITTED, ['days_ago' => 0, 'photos' => 2,
            'content' => 'Нашла 5 и 9 изомеров, названия в файле. Не уверена в 2,2,3-триметилбутане.']);

        // 5. Индивидуальное ДЗ, срок через 5 дней — пока ничего не сдано
        $this->hw['ovr'] = $this->homework($this->teacher, $this->rooms['ibragim'], [
            'type' => Homework::TYPE_HOMEWORK,
            'title' => 'Окислительно-восстановительные реакции: метод электронного баланса',
            'description' => '<p>Уравняйте 10 реакций из файла методом электронного баланса, укажите окислитель и восстановитель.</p>',
            'max_score' => 10,
        ], 1, 5, [$s['ibragim']], files: ['ОВР — 10 реакций.pdf']);

        // 6. Старое ДЗ в истории — всё проверено и оценено
        $hw = $this->homework($this->teacher, $ege, [
            'type' => Homework::TYPE_HOMEWORK,
            'title' => 'Электролиз расплавов и растворов солей',
            'description' => '<p>Составьте схемы электролиза для 6 веществ из списка, запишите процессы на катоде и аноде.</p>',
            'max_score' => 10,
        ], 24, -17, $ege->participants);

        foreach ([
            ['hawa', 10, 'Всё верно.'],
            ['adam', 7, 'Для раствора CuSO₄ на аноде окисляется вода, а не сульфат-ион.'],
            ['marem', 8, 'Хорошо. В схеме для NaCl (раствор) не указана среда у катода.'],
            ['madina', 9, 'Отлично, одна описка в коэффициентах.'],
            ['tanzila', 6, 'Расплавы — верно, растворы нужно повторить: правило активных металлов.'],
            ['ahmed', 10, 'Без замечаний.'],
            ['zaira', 8, 'Хорошо. Обратите внимание на электролиз с растворимым анодом.'],
        ] as $i => [$key, $grade, $feedback]) {
            $this->submit($hw, $s[$key], HomeworkSubmission::STATUS_GRADED, ['days_ago' => 18 + ($i % 3), 'grade' => $grade, 'photos' => 1,
                'content' => 'Готово, схемы во вложении.', 'feedback' => $feedback]);
        }

        // 7. Адам: проверено, хорошая оценка
        $hw = $this->homework($this->teacher, $this->rooms['adam'], [
            'type' => Homework::TYPE_HOMEWORK,
            'title' => 'Генетическая связь классов неорганических веществ',
            'description' => '<p>Осуществите 5 цепочек превращений из файла. Для реакций ионного обмена запишите полные и сокращённые ионные уравнения.</p>',
            'max_score' => 10,
        ], 10, -6, [$s['adam']], files: ['Цепочки превращений.pdf']);
        $this->works['adam-cepochki'] = $this->submit($hw, $s['adam'], HomeworkSubmission::STATUS_GRADED, ['days_ago' => 7, 'grade' => 8, 'photos' => 2,
            'content' => 'Во второй цепочке не понял, как из FeCl₃ получить Fe(OH)₃ без побочных продуктов.',
            'feedback' => 'Хорошо. FeCl₃ + 3NaOH → Fe(OH)₃↓ + 3NaCl — побочный NaCl остаётся в растворе, это нормально. В 4-й цепочке не расставлены коэффициенты.']);

        // 8. Муса: просрочено и не сдано
        $this->homework($this->teacher, $this->rooms['musa'], [
            'type' => Homework::TYPE_HOMEWORK,
            'title' => 'ОГЭ: задания 1–10 на строение атома и периодический закон',
            'description' => '<p>Решите задания 1–10 из тренировочного варианта. Ответы впишите в текст работы.</p>',
            'max_score' => 10,
        ], 11, -4, [$s['musa']]);

        // 9. Группа ОГЭ: новое задание, срок через 6 дней
        $this->hw['kachestvennye'] = $this->homework($this->teacher, $oge, [
            'type' => Homework::TYPE_HOMEWORK,
            'title' => 'Качественные реакции на катионы и анионы',
            'description' => '<p>Заполните таблицу: реактив, признак реакции, сокращённое ионное уравнение. Для каждого иона — по одному примеру.</p>',
            'max_score' => 12,
        ], 0, 6, $oge->participants, files: ['Таблица для заполнения.pdf']);

        // 10. Черновик — ученикам не виден
        $this->homework($this->teacher, $ege, [
            'type' => Homework::TYPE_PRACTICE,
            'title' => 'Пробник ЕГЭ: тренировочный вариант 5',
            'description' => '<p>Полный вариант, 3 часа 30 минут. Откроется после разбора контрольной.</p>',
            'max_score' => 56,
            'is_visible' => false,
        ], 0, 12, $ege->participants);

        // 11. Заира сдала позже срока, оценено
        $hw = $this->homework($this->teacher, $this->rooms['zaira'], [
            'type' => Homework::TYPE_HOMEWORK,
            'title' => 'Скорость химической реакции: правило Вант-Гоффа',
            'description' => '<p>Решите 6 задач на температурный коэффициент и константу скорости.</p>',
            'max_score' => 6,
        ], 14, -9, [$s['zaira']]);
        $this->submit($hw, $s['zaira'], HomeworkSubmission::STATUS_GRADED, ['days_ago' => 6, 'grade' => 4, 'photos' => 1,
            'content' => 'Извините, что поздно. Задачи 5 и 6 не получились.',
            'feedback' => 'Задачи 1–4 верно. В 5 и 6 температурный коэффициент стоит в степени (Δt / 10), а не умножается.']);

        // 12. Аминат (бесплатно): на доработке
        $hw = $this->homework($this->teacher, $this->rooms['aminat'], [
            'type' => Homework::TYPE_HOMEWORK,
            'title' => 'Валентность: составить формулы по валентности',
            'description' => '<p>Составьте формулы 15 веществ по валентности элементов. Пример разобран в конспекте.</p>',
            'max_score' => 5,
        ], 6, -1, [$s['aminat']]);
        $this->submit($hw, $s['aminat'], HomeworkSubmission::STATUS_REVISION_REQUESTED, ['days_ago' => 2, 'photos' => 1,
            'content' => 'Сделала 12 из 15.',
            'feedback' => 'Хорошее начало! Перепроверьте 4, 9 и 11: у серы в сульфидах валентность II. И допишите три оставшиеся.']);

        // 13. Биология: у Хавы проверенная работа от второго учителя, у группы — новое задание
        $hw = $this->homework($this->bio, $this->rooms['bio-hawa'], [
            'type' => Homework::TYPE_HOMEWORK,
            'title' => 'Задачи по генетике: дигибридное скрещивание',
            'description' => '<p>Решите 5 задач. Для каждой — схема скрещивания, решётка Пеннета и ответ.</p>',
            'max_score' => 10,
        ], 9, -3, [$s['hawa']]);
        $this->submit($hw, $s['hawa'], HomeworkSubmission::STATUS_GRADED, ['days_ago' => 5, 'grade' => 9, 'photos' => 2,
            'content' => 'Решила все пять.',
            'feedback' => 'Отлично! В задаче 4 не указано расщепление по фенотипу — на экзамене за это снимут балл.']);

        $this->homework($this->bio, $this->rooms['bio-group'], [
            'type' => Homework::TYPE_HOMEWORK,
            'title' => 'Строение клетки: рисунки из второй части ЕГЭ',
            'description' => '<p>Подпишите органоиды на 4 рисунках и объясните функцию каждого в одном-двух предложениях.</p>',
            'max_score' => 8,
        ], 1, 4, $this->rooms['bio-group']->participants, files: ['Рисунки — клетка.pdf']);
    }

    /**
     * @param iterable<User> $students
     */
    private function homework(User $teacher, Room $room, array $attrs, int $createdDaysAgo, int $deadlineInDays, iterable $students, array $files = []): Homework
    {
        $createdAt = now()->subDays($createdDaysAgo)->setTime(18, 30);
        $createdAt = $this->notFuture($createdAt);

        $paths = [];
        $names = [];
        foreach ($files as $name) {
            // Префикс homework-attachments/ — по нему кабинет выдаёт на файл временную ссылку S3
            $path = 'homework-attachments/demo/' . Str::slug(Str::transliterate($attrs['title']), '-') . '-' . Str::slug(Str::transliterate(pathinfo($name, PATHINFO_FILENAME))) . '.pdf';
            $this->demoFile($path, pathinfo($name, PATHINFO_FILENAME), [$attrs['title'], 'Задание 1. …', 'Задание 2. …', 'Задание 3. …']);
            $paths[] = $path;
            $names[$path] = $name;
        }

        $homework = Homework::withoutEvents(fn () => Homework::forceCreate($attrs + [
            'teacher_id' => $teacher->id,
            'room_id' => $room->id,
            'deadline' => now()->addDays($deadlineInDays)->setTime(23, 59),
            'is_visible' => true,
            'attachments' => $paths ?: null,
            'file_names' => $names ?: null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]));

        $homework->students()->attach(collect($students)->pluck('id')->all());

        return $homework;
    }

    /**
     * Сданная работа + история событий.
     *
     * opts: days_ago — когда сдана; grade; feedback; content; photos — сколько фото решения; pdf — работа одним PDF;
     *       resubmitted — работа уже возвращалась на доработку и сдана повторно.
     */
    private function submit(Homework $homework, User $student, string $status, array $opts): HomeworkSubmission
    {
        $submittedAt = $this->notFuture(now()->subDays($opts['days_ago'])->setTime(20, 15)->subMinutes($student->id % 50));
        $teacherId = $homework->teacher_id;
        $isGradedOrReturned = in_array($status, [HomeworkSubmission::STATUS_GRADED, HomeworkSubmission::STATUS_REVISION_REQUESTED], true);
        $checkedAt = $this->notFuture($submittedAt->copy()->addDay());

        $paths = [];
        $names = [];
        $base = 'homework-submissions/demo/' . $homework->id . '-' . $student->id;
        if (! empty($opts['pdf'])) {
            $paths[] = $path = "{$base}.pdf";
            $names[$path] = 'Работа.pdf';
            $this->demoFile($path, 'Решение', [$homework->title, $student->name]);
        }
        for ($i = 1; $i <= ($opts['photos'] ?? 0); $i++) {
            $paths[] = $path = "{$base}-{$i}.jpg";
            $names[$path] = "Фото {$i}.jpg";
            $this->demoFile($path, "Решение, лист {$i}", [$homework->title, 'Дано: …', 'Решение: …', 'Ответ: …']);
        }

        $submission = HomeworkSubmission::withoutEvents(fn () => HomeworkSubmission::forceCreate([
            'homework_id' => $homework->id,
            'student_id' => $student->id,
            'status' => $status,
            'content' => $opts['content'] ?? null,
            'attachments' => $paths ?: null,
            'file_names' => $names ?: null,
            'feedback' => ($isGradedOrReturned || ! empty($opts['resubmitted'])) ? ($opts['feedback'] ?? null) : null,
            'grade' => $status === HomeworkSubmission::STATUS_GRADED ? ($opts['grade'] ?? null) : null,
            'submitted_at' => $submittedAt,
            'created_at' => $submittedAt,
            'updated_at' => $isGradedOrReturned ? $checkedAt : $submittedAt,
        ]));

        $log = function (string $type, Carbon $at, ?int $userId, ?array $meta = null) use ($submission) {
            HomeworkActivity::forceCreate([
                'submission_id' => $submission->id,
                'user_id' => $userId,
                'type' => $type,
                'metadata' => $meta,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        };

        if (! empty($opts['resubmitted'])) {
            $log(HomeworkActivity::TYPE_SUBMITTED, $submittedAt->copy()->subDays(3), $student->id);
            $log(HomeworkActivity::TYPE_REVISION_REQUESTED, $submittedAt->copy()->subDays(2), $teacherId);
            $log(HomeworkActivity::TYPE_RESUBMITTED, $submittedAt, $student->id);
        } else {
            $log(HomeworkActivity::TYPE_SUBMITTED, $submittedAt, $student->id);
        }

        match ($status) {
            HomeworkSubmission::STATUS_GRADED => $log(HomeworkActivity::TYPE_GRADED, $checkedAt, $teacherId, ['grade' => $opts['grade'] ?? null]),
            HomeworkSubmission::STATUS_REVISION_REQUESTED => $log(HomeworkActivity::TYPE_REVISION_REQUESTED, $checkedAt, $teacherId),
            default => null,
        };

        return $submission;
    }

    // ───────────────────────── Материалы ─────────────────────────

    private function createMaterials(): void
    {
        if (! $this->fileLinks) {
            return;
        }

        $folder = function (string $name, ?MaterialFolder $parent = null, int $sort = 0) {
            return MaterialFolder::create(['teacher_id' => $this->teacher->id, 'parent_id' => $parent?->id, 'name' => $name, 'sort_order' => $sort]);
        };

        $ege = $folder('ЕГЭ', null, 1);
        $f = [
            'ege' => $ege,
            'organic' => $folder('Органическая химия', $ege, 1),
            'inorganic' => $folder('Неорганическая химия', $ege, 2),
            'tasks' => $folder('Задачи', $ege, 3),
            'oge' => $folder('ОГЭ', null, 2),
            'tables' => $folder('Справочные таблицы', null, 3),
            'olymp' => $folder('Олимпиады', null, 4),
        ];

        $all = TeacherMaterial::VISIBILITY_ALL;
        $rooms = TeacherMaterial::VISIBILITY_ROOMS;
        $private = TeacherMaterial::VISIBILITY_PRIVATE;

        // [папка, название, описание, расширение, видимость, комнаты]
        $list = [
            ['tables', 'Периодическая система Д. И. Менделеева', 'Длиннопериодный вариант, как на экзамене', 'pdf', $all, []],
            ['tables', 'Таблица растворимости', null, 'png', $all, []],
            ['tables', 'Ряд активности металлов', 'Электрохимический ряд напряжений', 'png', $all, []],
            ['tables', 'Формулы для расчётных задач', 'Все формулы на одном листе', 'pdf', $all, []],
            ['organic', 'Изомерия и номенклатура алканов', 'Конспект с примерами', 'pdf', $rooms, ['hawa', 'ege']],
            ['organic', 'Генетическая связь органических веществ', null, 'pdf', $rooms, ['ege']],
            ['organic', 'Альдегиды и кетоны — конспект с доски', null, 'jpg', $rooms, ['ege']],
            ['inorganic', 'Окислительно-восстановительные реакции', 'Метод электронного баланса, 20 примеров', 'pdf', $rooms, ['ahmed', 'ege', 'ibragim']],
            ['inorganic', 'Электролиз: схемы на катоде и аноде', null, 'pdf', $rooms, ['luiza', 'ege']],
            ['inorganic', 'Гидролиз солей', null, 'pdf', $rooms, ['ege']],
            ['tasks', 'Задачи на растворы: 30 задач с ответами', null, 'pdf', $rooms, ['tanzila', 'ege']],
            ['tasks', 'Задачи на смеси и примеси', null, 'pdf', $rooms, ['ege', 'madina']],
            ['tasks', 'Тренировочный вариант ЕГЭ № 4', 'Решаем за 3 часа 30 минут', 'pdf', $rooms, ['ege', 'madina']],
            ['tasks', 'Ответы к варианту № 4', null, 'pdf', $private, []],
            ['oge', 'Демоверсия ОГЭ 2026', null, 'pdf', $rooms, ['oge', 'ibragim', 'musa']],
            ['oge', 'Качественные реакции на ионы', 'Таблица признаков реакций', 'png', $rooms, ['oge', 'daud']],
            ['oge', 'Пробник ОГЭ, вариант 3 — ответы', null, 'pdf', $private, []],
            ['oge', 'Химия с нуля: валентность и формулы', 'Для тех, кто только начинает', 'pdf', $rooms, ['aminat']],
            ['olymp', 'Региональный этап: задачи прошлых лет', null, 'pdf', $private, []],
            [null, 'План подготовки к ЕГЭ на год', null, 'pdf', $private, []],
            [null, 'Техника безопасности при опытах дома', null, 'pdf', $all, []],
        ];

        $mime = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg'];

        foreach ($list as $i => [$folderKey, $title, $description, $ext, $visibility, $roomKeys]) {
            $slug = Str::slug(Str::transliterate($title));
            $path = "demo/materials/{$slug}.{$ext}";
            $size = $this->demoFile($path, $title, array_filter([$description, 'Химия · Евлоев М. А.']));
            $thumb = null;
            if ($ext === 'pdf' && $this->s3) {
                $thumb = "demo/materials/thumbs/{$slug}.jpg";
                $this->demoFile($thumb, $title, array_filter([$description]), thumbnail: true);
            }

            // Без событий: наблюдатель читает размер из S3 и ставит в очередь создание миниатюры
            $material = TeacherMaterial::withoutEvents(fn () => TeacherMaterial::create([
                'teacher_id' => $this->teacher->id,
                'folder_id' => $folderKey ? $f[$folderKey]->id : null,
                'title' => $title,
                'description' => $description,
                'file_path' => $path,
                'thumbnail_path' => $thumb,
                'original_name' => "{$title}.{$ext}",
                'mime_type' => $mime[$ext],
                'file_size' => $size,
                'visibility' => $visibility,
                'sort_order' => $i,
            ]));
            $material->forceFill(['created_at' => now()->subDays(40 - $i), 'updated_at' => now()->subDays(40 - $i)])->saveQuietly();

            if ($roomKeys) {
                $material->rooms()->attach(collect($roomKeys)->map(fn ($key) => $this->rooms[$key]->id)->all());
            }
        }

        // У учителя биологии — свой небольшой набор
        foreach ([['Строение клетки: органоиды', $all], ['Генетика: решение задач по шагам', $all]] as $i => [$title, $visibility]) {
            $path = 'demo/materials/bio-' . Str::slug(Str::transliterate($title)) . '.pdf';
            TeacherMaterial::withoutEvents(fn () => TeacherMaterial::create([
                'teacher_id' => $this->bio->id,
                'title' => $title,
                'file_path' => $path,
                'original_name' => "{$title}.pdf",
                'mime_type' => 'application/pdf',
                'file_size' => $this->demoFile($path, $title, ['Биология · Цурова Л. Р.']),
                'visibility' => $visibility,
                'sort_order' => $i,
            ]));
        }
    }

    // ───────────────────────── Планы занятий ─────────────────────────

    private function createLessonPlans(): void
    {
        $plans = [
            'ege' => [
                "Разбор контрольной: типичные ошибки\nГидролиз солей: сильные и слабые электролиты\nЗадание 21 ЕГЭ — среда растворов\nДомашнее задание: 10 задач на гидролиз",
                "Пробник ЕГЭ, вариант 5 — объявить условия\nЗадачи на смеси: 3 примера у доски\nВопросы по материалам",
            ],
            'oge' => [
                "Разбор пробника: задания 17–19\nКачественные реакции — повторение таблицы\nЛабораторный опыт на видео: осадок BaSO₄",
                "Проверка таблицы качественных реакций\nЗадания 20–22 из демоверсии",
            ],
            'hawa' => ["Проверить изомеры C₇H₁₆\nАлкены: номенклатура и изомерия положения двойной связи\nРеакции присоединения: правило Марковникова"],
            'ibragim' => ["Разобрать ошибки в задании 17 пробника\nОВР: метод электронного баланса, 5 реакций вместе\nНапомнить про оплату"],
            'madina' => ["Задача 4 из домашнего задания — разобрать условие\nМассовая и мольная доля: в чём разница\nЗадачи 30–31 ЕГЭ"],
            'ramzan' => ["Знакомство: цели, сроки, уровень подготовки\nДиагностика: 10 заданий первой части\nСоставить план на полгода"],
        ];

        foreach ($plans as $key => $bodies) {
            foreach ($this->upcomingOriginals($key, count($bodies)) as $i => $original) {
                LessonPlan::create([
                    'room_id' => $this->rooms[$key]->id,
                    'starts_at' => $original,
                    'body' => $bodies[$i],
                    'updated_by' => $this->teacher->id,
                ]);
            }
        }
    }

    /** Ближайшие исходные времена занятий комнаты (без отменённых) — к ним привязан план */
    private function upcomingOriginals(string $key, int $count): array
    {
        $result = collect();

        foreach ($this->schedules[$key] ?? [] as $schedule) {
            $cancelled = $schedule->exceptions()->where('status', RoomScheduleException::STATUS_CANCELLED)->get()
                ->map(fn ($e) => $e->original_date->toDateString())->flip();

            foreach ($schedule->rawOccurrences(today(), now()->addWeeks(3)) as $original) {
                if ($original->copy()->addMinutes($schedule->minutes())->isFuture() && ! isset($cancelled[$original->toDateString()])) {
                    $result->push($original);
                }
            }
        }

        return $result->sortBy(fn (Carbon $at) => $at->getTimestamp())->take($count)->values()->all();
    }

    // ───────────────────────── Записи занятий ─────────────────────────

    /**
     * Записи — у комнат с включённой записью. У самого свежего занятия (если оно было за последние сутки)
     * записи нет: в отчёте видно «Запись обрабатывается».
     */
    private function createRecordings(): void
    {
        foreach ($this->sessions as $key => $sessions) {
            if (! isset($this->recorded[$key])) {
                continue;
            }

            foreach ($sessions as $session) {
                if ($session->ended_at->gt(now()->subDay())) {
                    continue;
                }

                $path = 'demo/recordings/' . $session->internal_meeting_id . '.mp4';
                Recording::create([
                    'meeting_id' => $session->meeting_id,
                    'record_id' => $session->internal_meeting_id,
                    'name' => $this->rooms[$key]->name,
                    'published' => true,
                    'start_time' => $session->started_at->copy()->addMinute(),
                    'end_time' => $session->ended_at,
                    'participants' => $session->participant_count,
                    's3_url' => $this->s3Url($path),
                    's3_uploaded_at' => $session->ended_at->copy()->addMinutes(40),
                ])->forceFill(['created_at' => $session->ended_at, 'updated_at' => $session->ended_at])->saveQuietly();
            }
        }
    }

    /** Запрос на удаление: учитель ошибочно запустил занятие Дауда, ученик не пришёл */
    private function createDeletionRequest(): void
    {
        $session = collect($this->sessions['daud'] ?? [])->last();

        if (! $session) {
            return;
        }

        $snapshot = $session->pricing_snapshot;
        $snapshot['participants'] = collect($snapshot['participants'])->map(fn ($p) => ['attended' => false] + $p)->all();
        $snapshot['total_cost'] = 0;
        $analytics = $session->analytics_data;
        $analytics['participants'] = array_values(array_filter($analytics['participants'], fn ($p) => $p['role'] === 'MODERATOR'));

        $session->forceFill([
            'participant_count' => 1,
            'ended_at' => $session->started_at->copy()->addMinutes(12),
            'analytics_data' => $analytics,
            'pricing_snapshot' => $snapshot,
            'deletion_requested_at' => $this->notFuture($session->started_at->copy()->addMinutes(30)),
            'deletion_reason' => 'Ученик заболел и не пришёл, занятие открыл по ошибке — прошу не списывать его с тарифа.',
        ])->saveQuietly();

        PaymentRecord::where('meeting_session_id', $session->id)->delete();
    }

    // ───────────────────────── Сообщения ─────────────────────────

    private function createMessages(): void
    {
        $t = $this->teacher;
        $s = $this->s;
        $at = fn (int $daysAgo, string $time) => $this->notFuture(now()->subDays($daysAgo)->setTimeFromTimeString($time));
        $lastEge = collect($this->sessions['ege'] ?? [])->last();

        // [комната, автор, текст, когда, прочитано, вложения]
        $messages = [
            ['hawa', $s['hawa'], 'Магомед Ахмедович, добрый вечер! В задаче 8 про растворы ответ получился 12,5 %, а в ответах 12 %. Это из-за округления?', $at(9, '19:40'), true],
            ['hawa', $t, 'Добрый вечер! Да, в ответах округлено до целого. Ваш ответ верный, главное — не терять единицы измерения.', $at(9, '20:05'), true],
            ['hawa', $s['hawa'], 'Спасибо!', $at(9, '20:07'), true],
            ['hawa', $s['hawa'], 'Отправила изомеры. Больше всего сомневаюсь в 2,2,3-триметилбутане — посмотрите его первым, пожалуйста.', $at(0, '09:20'), true],
            ['hawa', $t, 'Хорошо, проверю сегодня вечером и разберём на следующем занятии.', $at(0, '10:02'), false],

            ['adam', $t, 'Адам, ближайшее занятие отменил — удачи на олимпиаде! Пропущенную тему разберём на следующей неделе.', $at(3, '12:15'), true],
            ['adam', $s['adam'], 'Спасибо! Расскажу, как прошло.', $at(3, '12:40'), true],

            ['ibragim', $t, 'Ибрагим, напоминаю про оплату — срок по прошлому занятию уже прошёл.', $at(4, '11:00'), true],
            ['ibragim', $s['ibragim'], 'Извините, забыл. Сегодня скажу родителям.', $at(4, '18:30'), true],
            ['ibragim', $t, 'Во вторник у тебя пробник в школе, поэтому занятие переносим на среду, 18:00.', $at(2, '16:10'), true],
            ['ibragim', $s['ibragim'], 'Перевёл за все занятия, чек отправил через оплату в кабинете.', $at(0, '08:45'), false],

            ['musa', $t, 'Муса, по оплате накопился долг за несколько занятий. Пока он не погашен, занятия будут недоступны.', $at(6, '10:00'), true],
            ['musa', $s['musa'], 'Понял, поговорю с мамой.', $at(6, '19:12'), true],
            ['musa', $s['musa'], 'Вот скрин, мама перевела.', $at(3, '21:06'), true, $this->chatFile('musa-skrin.jpg', 'Скриншот перевода.jpg', 'Перевод выполнен')],
            ['musa', $t, 'Перевод пока не пришёл. Пришлите, пожалуйста, чек из приложения банка — в скриншоте нет номера операции.', $at(2, '09:30'), false],

            ['madina', $s['madina'], 'Можно в среду начать на полчаса позже? У меня пробник в школе до 14:20.', $at(5, '17:02'), true],
            ['madina', $t, 'Давайте в этот раз так, начнём в 14:30.', $at(5, '17:30'), true],
            ['madina', $s['madina'], 'Спасибо! И ещё: можно разобрать задачу 4 из домашнего задания? Я так и не поняла условие.', $at(0, '09:12'), false],

            ['aminat', $t, 'Аминат, перед средой посмотрите конспект про валентность — он в материалах.', $at(5, '13:00'), true],
            ['aminat', $s['aminat'], 'Хорошо, посмотрю!', $at(5, '15:20'), true],

            ['ahmed', $s['ahmed'], 'Можно получить ещё задачи на ОВР с органикой? Хочу потренироваться.', $at(7, '20:40'), true],
            ['ahmed', $t, 'Конечно. Добавил в материалы «Окислительно-восстановительные реакции» — там 20 примеров, последние 8 как раз с органикой.', $at(7, '21:15'), true],

            ['ramzan', $t, 'Рамзан, добро пожаловать! Первое занятие в четверг в 18:30. Возьмите тетрадь и калькулятор — начнём с небольшой диагностики.', $at(1, '19:00'), true],
            ['ramzan', $s['ramzan'], 'Здравствуйте! Хорошо, буду.', $at(1, '19:25'), true],

            ['ege', $t, 'Всем добрый вечер! Таблица растворимости лежит в материалах — распечатайте её к четвергу.', $at(15, '19:00'), true],
            ['ege', $s['ahmed'], 'Хорошо', $at(15, '19:04'), true],
            ['ege', $s['zaira'], 'А можно в электронном виде на планшете?', $at(15, '19:10'), true],
            ['ege', $t, 'Можно, но на экзамене будет бумажная — привыкайте к ней.', $at(15, '19:20'), true],
            ['ege', $t, 'Разбор контрольной прошлого года — во вложении.', $at(6, '18:45'), true, $this->chatFile('ege-razbor.pdf', 'Разбор контрольной.pdf', 'Разбор контрольной')],
            ['ege', $t, 'Контрольную по растворам сдаём до завтра, до 23:59.', $at(1, '18:40'), true],
            ['ege', $s['tanzila'], 'А калькулятором можно пользоваться?', $at(0, '11:03'), false],
            ['ege', $s['marem'], 'Присоединяюсь к вопросу', $at(0, '11:10'), false],

            ['oge', $t, 'Пробник ОГЭ, вариант 3 — в заданиях. Решаем строго за 120 минут.', $at(9, '17:40'), true],
            ['oge', $s['alihan'], 'А справочные таблицы можно?', $at(9, '18:02'), true],
            ['oge', $t, 'Только таблицу Менделеева, растворимости и ряд напряжений.', $at(9, '18:10'), true],
            ['oge', $s['luiza'], 'Я не успела 17–19, можно дорешать?', $at(3, '20:30'), true],
            ['oge', $t, 'Да, пришлите до пятницы.', $at(3, '20:45'), true],
            ['oge', $s['isa'], 'Отправил пробник', $at(2, '22:10'), true],

            ['bio-hawa', $this->bio, 'Хава, в задаче 4 не забудьте про расщепление по фенотипу — на экзамене за это снимают балл.', $at(4, '12:00'), true],
            ['bio-hawa', $s['hawa'], 'Поняла, спасибо! Исправлю в следующих задачах.', $at(4, '13:40'), true],
            ['bio-group', $this->bio, 'Новое задание про строение клетки — в заданиях. Рисунки распечатайте, будем подписывать на занятии.', $at(1, '10:00'), false],
        ];

        // Вопросы во время последнего занятия группы ЕГЭ — они попадают в отчёт о занятии
        if ($lastEge) {
            $during = fn (int $minutes) => $lastEge->started_at->copy()->addMinutes($minutes);
            $messages[] = ['ege', $s['zaira'], 'Не слышно звук, перезайду', $during(4), true];
            $messages[] = ['ege', $s['adam'], 'Можно ещё раз номер задачи?', $during(31), true];
            $messages[] = ['ege', $t, 'Задача 12, страница 3 в файле с задачами.', $during(32), true];
            $messages[] = ['ege', $s['hawa'], 'Спасибо за занятие!', $during(88), true];
        }

        usort($messages, fn ($a, $b) => $a[3] <=> $b[3]);

        foreach ($messages as $m) {
            [$key, $author, $content, $when, $read] = $m;
            Message::forceCreate([
                'room_id' => $this->rooms[$key]->id,
                'user_id' => $author->id,
                'content' => $content,
                'attachments' => $m[5] ?? null,
                'read_at' => $read ? $this->notFuture($when->copy()->addMinutes(mt_rand(2, 90))) : null,
                'created_at' => $when,
                'updated_at' => $when,
            ]);
        }
    }

    /** Вложение сообщения (или null, если ссылки на файлы не строятся) */
    private function chatFile(string $file, string $name, string $title): ?array
    {
        if (! $this->fileLinks) {
            return null;
        }

        $path = "demo/chat/{$file}";
        $ext = pathinfo($file, PATHINFO_EXTENSION);

        return [[
            'path' => $path,
            'name' => $name,
            'type' => $ext === 'pdf' ? 'application/pdf' : 'image/jpeg',
            'size' => $this->demoFile($path, $title, ['Демо-файл'], receipt: $ext !== 'pdf'),
        ]];
    }

    private function createSupportChat(): void
    {
        if (! $this->admin) {
            return;
        }

        $chat = SupportChat::create(['user_id' => $this->teacher->id]);

        foreach ([
            [$this->teacher, 'Здравствуйте! Как перенести одно занятие на другой день, чтобы ученик получил уведомление?', 12, '10:14'],
            [$this->admin, 'Здравствуйте! Откройте занятие в расписании и нажмите «Перенести» — ученик получит уведомление в кабинете и пуш, если они включены.', 12, '10:31'],
            [$this->teacher, 'Получилось, спасибо!', 12, '10:40'],
        ] as [$author, $content, $daysAgo, $time]) {
            $when = now()->subDays($daysAgo)->setTimeFromTimeString($time);
            SupportMessage::forceCreate([
                'support_chat_id' => $chat->id,
                'user_id' => $author->id,
                'content' => $content,
                'read_at' => $when->copy()->addMinutes(5),
                'created_at' => $when,
                'updated_at' => $when,
            ]);
        }
    }

    // ───────────────────────── Отзывы ─────────────────────────

    private function createReviews(): void
    {
        $reviews = [
            ['hawa', 5, 'Занимаюсь с Магомедом Ахмедовичем с 10 класса. Органика перестала быть набором формул: теперь понимаю, почему реакция идёт именно так. На пробниках стабильно 85+.', 60, true],
            ['adam', 5, 'Очень понятно объясняет, всегда отвечает в чате, даже вечером. Домашние задания проверяет подробно, с разбором каждой ошибки.', 45, true],
            ['ahmed', 5, 'Лучший учитель химии! За полгода поднял пробник с 52 до 81 балла.', 40, true],
            ['tanzila', 4, 'Хорошо объясняет задачи на растворы. Иногда задаёт многовато, но результат виден.', 30, true],
            ['alihan', 5, 'Готовлюсь к ОГЭ. Занятия в группе интересные, много практики.', 21, true],
            ['zaira', 4, 'Кинетика наконец стала понятной. Хотелось бы больше видео-разборов.', 15, true],
            ['marem', 5, 'Занимаюсь в группе 11 класса. Очень удобно, что все материалы и записи занятий в одном месте — пересматриваю перед контрольными.', 2, false],
            ['ibragim', 4, 'Объясняет понятно, но спрашивает строго :)', 1, false],
        ];

        foreach ($reviews as [$key, $rating, $text, $daysAgo, $read]) {
            $this->review($this->s[$key], $this->teacher, $rating, $text, $daysAgo, $read);
        }

        // Отзыв с грубостью: учитель пожаловался, ждёт решения администратора
        $this->review($this->s['musa'], $this->teacher, 1, 'Закрыл мне занятия из-за денег, вообще не входит в положение!!!', 2, true, [
            'is_reported' => true,
            'report_reason' => 'rude',
            'report_note' => 'Ученик пишет отзыв из-за блокировки за неоплату, к занятиям претензий нет.',
            'reported_at' => now()->subDay(),
        ]);

        $this->review($this->s['hawa'], $this->bio, 5, 'Генетика с Лианой Руслановной — одно удовольствие. Задачи разбираем до полного понимания.', 10, true);

        // Отзыв учителя о платформе — на проверке у команды
        $this->review($this->teacher, null, 5, 'Перевёл всех учеников сюда за месяц. Больше всего экономит время проверка заданий с пометками на фото и то, что оплата учеников видна в одном месте.', 8, false);
    }

    private function review(User $author, ?User $teacher, int $rating, string $text, int $daysAgo, bool $read, array $extra = []): void
    {
        $at = now()->subDays($daysAgo)->setTime(mt_rand(10, 22), mt_rand(0, 59));
        $at = $this->notFuture($at);

        $review = Review::create($extra + [
            'user_id' => $author->id,
            'teacher_id' => $teacher?->id,
            'rating' => $rating,
            'text' => $text,
            'teacher_read_at' => $read && $teacher ? $at->copy()->addHours(3) : null,
            'show_on_site' => true,
        ]);
        $review->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();
    }

    // ───────────────────────── Тариф и платежи ─────────────────────────

    private function createSubscriptions(): void
    {
        $tariffs = Tariff::whereIn('slug', ['start', 'basic', 'pro'])->get()->keyBy('slug');
        $chem = $this->teacher;

        // «Старт» с регистрации → три месяца «Базовый» → «Профи» с продлениями, текущий период идёт 12 дней
        $this->subscription($chem, $tariffs['start'], $chem->created_at, $chem->created_at->copy()->addDays(40), null);

        $periods = [
            ['basic', 200], ['basic', 170], ['basic', 140],
            ['pro', 110], ['pro', 80], ['pro', 50], ['pro', 12],
        ];
        foreach ($periods as [$slug, $daysAgo]) {
            $this->subscription($chem, $tariffs[$slug], now()->subDays($daysAgo), now()->subDays($daysAgo)->addDays(30), 'paid');
        }

        // Неудачная попытка оплаты картой (в истории не показывается) и возврат ошибочного платежа
        $this->payment($chem, $tariffs['pro'], null, SubscriptionPayment::STATUS_FAILED, now()->subDays(50)->subHour());
        $this->payment($chem, $tariffs['pro'], null, SubscriptionPayment::STATUS_REFUNDED, now()->subDays(79), meta: ['refunded_at' => now()->subDays(78)->toIso8601String()]);

        // Докупка занятий
        $this->payment($chem, $tariffs['pro'], null, SubscriptionPayment::STATUS_PAID, now()->subDays(20), extraLessons: 10, amount: 990);

        $chem->forceFill([
            'extra_lessons_balance' => 25,
            'auto_renew' => false, // без сохранённой карты: автосписание на демо-аккаунте невозможно
        ])->saveQuietly();

        // Учитель биологии — «Базовый»
        $this->subscription($this->bio, $tariffs['start'], $this->bio->created_at, $this->bio->created_at->copy()->addDays(30), null);
        $this->subscription($this->bio, $tariffs['basic'], now()->subDays(20), now()->addDays(10), 'paid');
    }

    private function subscription(User $user, Tariff $tariff, Carbon $starts, Carbon $ends, ?string $payment): Subscription
    {
        $active = $starts->isPast() && $ends->isFuture();

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'tariff_id' => $tariff->id,
            'status' => $active ? Subscription::STATUS_ACTIVE : Subscription::STATUS_EXPIRED,
            'price' => $tariff->price,
            'starts_at' => $starts,
            'ends_at' => $ends,
        ]);
        $subscription->forceFill(['created_at' => $starts, 'updated_at' => $starts])->saveQuietly();

        if ($payment) {
            $this->payment($user, $tariff, $subscription, SubscriptionPayment::STATUS_PAID, $starts);
        }

        return $subscription;
    }

    private function payment(User $user, Tariff $tariff, ?Subscription $subscription, string $status, Carbon $at, int $extraLessons = 0, ?int $amount = null, array $meta = []): SubscriptionPayment
    {
        $payment = SubscriptionPayment::create([
            'user_id' => $user->id,
            'tariff_id' => $tariff->id,
            'subscription_id' => $subscription?->id,
            'amount' => $amount ?? $tariff->price,
            'period_days' => $extraLessons ? 0 : 30,
            'extra_lessons' => $extraLessons,
            'status' => $status,
            'gateway' => 'yookassa',
            'gateway_order_id' => 'demo-' . Str::uuid(),
            'paid_at' => in_array($status, [SubscriptionPayment::STATUS_PAID, SubscriptionPayment::STATUS_REFUNDED], true) ? $at : null,
            'meta' => $meta ?: null,
        ]);
        $payment->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();

        return $payment;
    }

    // ───────────────────────── Приглашённые учителя ─────────────────────────

    private function createReferrals(): void
    {
        $basic = Tariff::where('slug', 'basic')->first();
        $pro = Tariff::where('slug', 'pro')->first();

        // [почта, фамилия, имя, предмет, дней назад, тариф оплаты]
        foreach ([
            ['pay-ref-1@demo.ru', 'Хашагульгова', 'Зарема', 'Математика', 45, $basic],
            ['pay-ref-2@demo.ru', 'Дзейтова', 'Лейла', 'Английский язык', 25, $pro],
            ['pay-ref-3@demo.ru', 'Мержоев', 'Руслан', 'Физика', 6, null],
        ] as [$email, $last, $first, $subject, $daysAgo, $tariff]) {
            $user = $this->createUser($email, $last, $first, null, User::ROLE_TUTOR, [
                'referred_by_id' => $this->teacher->id,
                'is_active' => false, // не показываем в каталоге сайта
            ], registeredDaysAgo: $daysAgo);
            $user->subjects()->sync([Subject::firstOrCreate(['name' => $subject])->id]);

            if (! $tariff) {
                continue;
            }

            $subscription = $this->subscription($user, $tariff, now()->subDays($daysAgo - 2), now()->subDays($daysAgo - 32), 'paid');
            $reward = ReferralReward::create([
                'referrer_id' => $this->teacher->id,
                'referred_id' => $user->id,
                'payment_id' => $subscription->payments()->value('id'),
                'referrer_lessons' => 10,
                'referred_lessons' => 5,
                'status' => ReferralReward::STATUS_CREDITED,
            ]);
            $reward->forceFill(['created_at' => $subscription->starts_at, 'updated_at' => $subscription->starts_at])->saveQuietly();
        }

        // Заявка приглашённого учителя ещё на рассмотрении
        TeacherApplication::create([
            'last_name' => 'Дзортов',
            'first_name' => 'Тимур',
            'email' => 'pay-ref-4@demo.ru',
            'phone' => '+79287140001',
            'about' => 'Преподаю историю и обществознание, готовлю к ЕГЭ.',
            'subjects' => [Subject::firstOrCreate(['name' => 'История'])->id],
            'grade' => [9, 10, 11],
            'status' => TeacherApplication::STATUS_PENDING,
            'referred_by_id' => $this->teacher->id,
        ]);
    }

    // ───────────────────────── Уведомления ─────────────────────────

    private function createNotifications(): void
    {
        $t = $this->teacher;
        $s = $this->s;
        $ago = fn (int $days, string $time = '12:00') => $this->notFuture(now()->subDays($days)->setTimeFromTimeString($time));

        // ── Учитель ──
        if ($work = $this->works['hawa-izomery'] ?? null) {
            $this->notify($t, 'HomeworkSubmitted', CabinetMessage::make('Новая работа')
                ->body($s['hawa']->name . ' · «' . $work->homework->title . '»')->icon('tasks')
                ->action('Проверить', route('cabinet.teacher.review', $work)), $work->submitted_at, false);
        }
        if ($claim = $this->claims['ibragim'] ?? null) {
            $this->notify($t, 'PaymentClaimSubmitted', CabinetMessage::make('Ученик сообщил об оплате')
                ->body($s['ibragim']->name . ' · ' . plural_ru($claim->records()->count(), 'занятие', 'занятия', 'занятий') . ' · ' . Money::format($claim->amount) . ' · чек приложен')
                ->icon('wallet')->action('Проверить оплату', TeacherStudentsService::studentUrl($s['ibragim'], ['tab' => 'pay'])), $claim->created_at, false);
        }
        $this->notify($t, 'NewMessage', CabinetMessage::make('Новое сообщение в «' . $this->rooms['madina']->name . '»')
            ->body($s['madina']->name . ': Спасибо! И ещё: можно разобрать задачу 4 из домашнего задания?')->icon('chat')
            ->action('Открыть чат', route('cabinet.teacher.messages', ['room' => $this->rooms['madina']->id])), $ago(0, '09:12'), false);
        $this->notify($t, 'StudentLeftReview', CabinetMessage::make('Новый отзыв')
            ->body($s['marem']->name . ' · оценка 5 из 5 — «Занимаюсь в группе 11 класса. Очень удобно, что все материалы и записи занятий в одном месте…»')
            ->icon('star')->action('Открыть отзывы', route('cabinet.teacher.reviews')), $ago(2, '21:30'), false);
        $this->notify($t, 'StudentAcceptedInvite', CabinetMessage::make('Новый ученик')
            ->body($s['ramzan']->name . ' теперь в вашем списке учеников')->icon('users')
            ->action('Открыть ученика', TeacherStudentsService::studentUrl($s['ramzan'])), $ago(2, '18:05'), true);
        $this->notify($t, 'HomeworkSubmitted', CabinetMessage::make('Новая работа')
            ->body($s['isa']->name . ' · «' . $this->hw['probnik']->title . '»')->icon('tasks')
            ->action('Проверить', route('cabinet.teacher.task', $this->hw['probnik'])), $ago(2, '20:15'), true);
        $this->notify($t, 'LessonStartingSoon', CabinetMessage::make('Скоро занятие')
            ->body('«' . $this->rooms['ege']->name . '» сегодня в 17:00 — через 15 минут')->icon('clock')
            ->action('Начать занятие', route('cabinet.teacher.lesson', $this->rooms['ege'])), $ago(3, '16:45'), true);
        $this->notify($t, 'ReferralBonusCredited', CabinetMessage::make('Вам начислено +10 занятий')
            ->body('Дзейтова Лейла оплатила тариф по вашему приглашению. Дополнительных занятий на балансе: 25. Они не сгорают и расходуются после лимита тарифа.')
            ->icon('wallet')->action('Открыть приглашения', route('cabinet.teacher.referrals')), $ago(23, '14:20'), true);
        $sub = $t->activeSubscription();
        $this->notify($t, 'SubscriptionPaid', CabinetMessage::make('Тариф оплачен')
            ->body('Оплата ' . Money::format((int) $sub?->price) . ' за тариф «' . $sub?->tariff?->name . '» прошла' . ($sub?->ends_at ? '. Тариф действует до ' . HumanDate::date($sub->ends_at) : ''))
            ->icon('check')->action('Открыть тариф', route('cabinet.teacher.subscription')), $sub?->starts_at ?? $ago(12), true);

        // ── Ученики ──
        $this->notify($s['hawa'], 'NewMessage', CabinetMessage::make('Новое сообщение в «' . $this->rooms['hawa']->name . '»')
            ->body($t->name . ': Хорошо, проверю сегодня вечером и разберём на следующем занятии.')->icon('chat')
            ->action('Открыть чат', route('cabinet.student.messages', ['room' => $this->rooms['hawa']->id])), $ago(0, '10:02'), false);
        $this->notify($s['hawa'], 'NewHomework', CabinetMessage::make('Новое задание')
            ->body('«' . $this->hw['kontrolnaya']->title . '» — сдать до ' . TeacherLessonService::when($this->hw['kontrolnaya']->deadline))->icon('tasks')
            ->action('Открыть задание', route('cabinet.student.task', $this->hw['kontrolnaya'])), $this->hw['kontrolnaya']->created_at, true);
        $this->notify($s['hawa'], 'HomeworkGraded', CabinetMessage::make('Работа проверена')
            ->body('«' . $this->hw['rastvory']->title . '» — оценка ' . $this->hw['rastvory']->formatGrade(9))->icon('tasks')
            ->action('Открыть задание', route('cabinet.student.task', $this->hw['rastvory'])), $ago(6, '19:00'), true);
        $this->notify($s['hawa'], 'PaymentClaimDecided', CabinetMessage::make('Оплата подтверждена')
            ->body($t->name . ' подтвердил оплату')->icon('wallet')
            ->action('Открыть оплату', route('cabinet.student.payments')), $ago(4, '11:00'), true);

        $moved = $this->rooms['ibragim']->scheduleExceptions()->where('status', RoomScheduleException::STATUS_MOVED)->first();
        if ($moved) {
            $this->notify($s['ibragim'], 'TeacherUpdatedSchedule', CabinetMessage::make('Расписание изменилось')
                ->body('«' . $this->rooms['ibragim']->name . '»: занятие ' . TeacherLessonService::when($moved->original_starts_at) . ' перенесено на ' . TeacherLessonService::when($moved->starts_at))
                ->icon('calendar')->action('Открыть занятие', route('cabinet.student.lesson', $this->rooms['ibragim'])), $ago(2, '16:10'), false);
        }
        $this->notify($s['ibragim'], 'PaymentReminder', CabinetMessage::make('Напоминание об оплате')
            ->body('Есть неоплаченные занятия у ' . $t->name . '. Оплатите их, чтобы занятия этого учителя оставались открыты')->icon('wallet')
            ->action('Открыть оплату', route('cabinet.student.payments')), $ago(1, '10:00'), true);
        $this->notify($s['ibragim'], 'NewHomework', CabinetMessage::make('Новое задание')
            ->body('«' . $this->hw['ovr']->title . '» — сдать до ' . TeacherLessonService::when($this->hw['ovr']->deadline))->icon('tasks')
            ->action('Открыть задание', route('cabinet.student.task', $this->hw['ovr'])), $this->hw['ovr']->created_at, false);
        $this->notify($s['ibragim'], 'HomeworkGraded', CabinetMessage::make('Работа проверена')
            ->body('«' . $this->hw['probnik']->title . '» — оценка ' . $this->hw['probnik']->formatGrade(27))->icon('tasks')
            ->action('Открыть задание', route('cabinet.student.task', $this->hw['probnik'])), $ago(2, '12:00'), true);

        $this->notify($s['musa'], 'PaymentClaimDecided', CabinetMessage::make('Оплата не подтверждена')
            ->body($t->name . ': Перевод пока не пришёл. Пришлите, пожалуйста, чек из приложения банка.')->icon('wallet')
            ->action('Открыть оплату', route('cabinet.student.payments')), $ago(2, '09:30'), false);
        $this->notify($s['musa'], 'PaymentReminder', CabinetMessage::make('Напоминание об оплате')
            ->body('Есть неоплаченные занятия у ' . $t->name . ' — 3 занятия. Оплатите их, чтобы занятия этого учителя оставались открыты')->icon('wallet')
            ->action('Открыть оплату', route('cabinet.student.payments')), $ago(5, '10:00'), true);

        $cancelled = $this->rooms['adam']->scheduleExceptions()->where('status', RoomScheduleException::STATUS_CANCELLED)->first();
        if ($cancelled) {
            $this->notify($s['adam'], 'TeacherUpdatedSchedule', CabinetMessage::make('Расписание изменилось')
                ->body('«' . $this->rooms['adam']->name . '»: занятие ' . TeacherLessonService::when($cancelled->original_starts_at) . ' отменено')->icon('calendar')
                ->action('Открыть занятие', route('cabinet.student.lesson', $this->rooms['adam'])), $ago(3, '12:15'), false);
        }

        if ($work = $this->works['madina-rastvory'] ?? null) {
            $this->notify($s['madina'], 'HomeworkRevisionRequested', CabinetMessage::make('Работу нужно доработать')
                ->body('«' . $work->homework->title . '»: ' . $work->feedback)->icon('repeat')
                ->action('Открыть задание', route('cabinet.student.task', $work->homework)), $work->updated_at, false);
        }

        $this->notify($s['isa'], 'PaymentReminder', CabinetMessage::make('Напоминание об оплате')
            ->body('Есть неоплаченные занятия у ' . $t->name . '. Оплатите их, чтобы занятия этого учителя оставались открыты')->icon('wallet')
            ->action('Открыть оплату', route('cabinet.student.payments')), $ago(1, '10:00'), false);

        foreach ($this->rooms['oge']->participants as $student) {
            $this->notify($student, 'NewHomework', CabinetMessage::make('Новое задание')
                ->body('«' . $this->hw['kachestvennye']->title . '» — сдать до ' . TeacherLessonService::when($this->hw['kachestvennye']->deadline))->icon('tasks')
                ->action('Открыть задание', route('cabinet.student.task', $this->hw['kachestvennye'])), $this->hw['kachestvennye']->created_at, false);
        }
    }

    private function notify(User $user, string $class, CabinetMessage $message, ?Carbon $at, bool $read): void
    {
        $at = $this->notFuture(($at ?? now())->copy());

        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\' . $class,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode($message->toArray(), JSON_UNESCAPED_UNICODE),
            'read_at' => $read ? $at->copy()->addMinutes(mt_rand(5, 180)) : null,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    // ───────────────────────── Файлы ─────────────────────────

    /**
     * Демо-файл: страница с заголовком и строками (jpg/png — картинка, pdf — картинка внутри PDF).
     * В S3 загружается, только если он настроен. Возвращает размер файла в байтах.
     */
    private function demoFile(string $path, string $title, array $lines = [], bool $receipt = false, bool $thumbnail = false, bool $public = true): int
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        [$w, $h] = match (true) {
            $thumbnail => [480, 680],
            $receipt => [720, 1100],
            default => [1240, 1754],
        };

        $image = $this->page($title, $lines, $w, $h);

        ob_start();
        match ($ext) {
            'png' => imagepng($image, null, 9),
            default => imagejpeg($image, null, 82),
        };
        $bytes = ob_get_clean();

        if ($ext === 'pdf') {
            $bytes = $this->pdf($bytes, $w, $h);
        }

        if ($this->s3) {
            try {
                Storage::disk('s3')->put($path, $bytes, $public ? 'public' : 'private');
            } catch (\Throwable $e) {
                $this->command?->warn("Не удалось загрузить {$path}: {$e->getMessage()}");
            }
        }

        return strlen($bytes);
    }

    private function page(string $title, array $lines, int $w, int $h): \GdImage
    {
        $img = imagecreatetruecolor($w, $h);
        imagefilledrectangle($img, 0, 0, $w, $h, imagecolorallocate($img, 255, 255, 255));
        $ink = imagecolorallocate($img, 24, 24, 27);
        $muted = imagecolorallocate($img, 113, 113, 122);
        $rule = imagecolorallocate($img, 228, 228, 231);
        $bold = resource_path('fonts/Inter-SemiBold.ttf');
        $regular = resource_path('fonts/Inter-Regular.ttf');

        $scale = $w / 1240;
        $x = (int) (96 * $scale);
        $y = (int) (180 * $scale);

        foreach ($this->wrap($title, (int) (34 / max($scale, 0.6))) as $line) {
            imagettftext($img, 40 * $scale, 0, $x, $y, $ink, $bold, $line);
            $y += (int) (64 * $scale);
        }

        $y += (int) (24 * $scale);
        imageline($img, $x, $y, $w - $x, $y, $rule);
        $y += (int) (80 * $scale);

        foreach ($lines as $text) {
            foreach ($this->wrap($text, (int) (60 / max($scale, 0.6))) as $line) {
                imagettftext($img, 24 * $scale, 0, $x, $y, $muted, $regular, $line);
                $y += (int) (48 * $scale);
            }
            $y += (int) (16 * $scale);
        }

        return $img;
    }

    private function wrap(string $text, int $width): array
    {
        $lines = [];
        $line = '';
        foreach (preg_split('/\s+/u', trim($text)) as $word) {
            if ($line !== '' && mb_strlen($line . ' ' . $word) > $width) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $line === '' ? $word : "{$line} {$word}";
            }
        }

        return $line === '' ? $lines : [...$lines, $line];
    }

    /** Одностраничный PDF A4 с JPEG на всю страницу */
    private function pdf(string $jpeg, int $w, int $h): string
    {
        $content = 'q 595 0 0 842 0 0 cm /Im0 Do Q';
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /XObject << /Im0 4 0 R >> >> /Contents 5 0 R >>',
            "<< /Type /XObject /Subtype /Image /Width {$w} /Height {$h} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($jpeg) . " >>\nstream\n{$jpeg}\nendstream",
            '<< /Length ' . strlen($content) . " >>\nstream\n{$content}\nendstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref' . "\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf . 'trailer' . "\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }

    private function s3Url(string $path): string
    {
        if ($base = config('filesystems.disks.s3.url')) {
            return rtrim($base, '/') . '/' . ltrim($path, '/');
        }

        try {
            return Storage::disk('s3')->url($path);
        } catch (\Throwable) {
            return '/' . $path;
        }
    }

    // ───────────────────────── Итог ─────────────────────────

    private function report(): void
    {
        $status = fn (string $key) => PaymentRecordService::debtStatus($this->s[$key]->id, $this->teacher->id);

        $this->command?->info('Демо-кабинеты созданы. Пароль у всех: password');
        $this->command?->line('  Учитель химии:    pay-teacher@demo.ru');
        $this->command?->line('  Учитель биологии: pay-teacher-bio@demo.ru');
        $this->command?->line(sprintf(
            '  Занятий проведено: %d · начислений: %d · заданий: %d · сообщений: %d',
            MeetingSession::whereIn('user_id', [$this->teacher->id, $this->bio->id])->count(),
            PaymentRecord::whereIn('teacher_id', [$this->teacher->id, $this->bio->id])->count(),
            Homework::whereIn('teacher_id', [$this->teacher->id, $this->bio->id])->count(),
            Message::whereIn('room_id', collect($this->rooms)->pluck('id'))->count(),
        ));

        foreach (['ibragim' => false, 'musa' => true, 'isa' => false] as $key => $blocked) {
            $st = $status($key);
            $ok = $st['overdue_count'] > 0 && $st['blocked'] === $blocked;
            $this->command?->line(sprintf('  %s: просрочено %d, занятий с долгом %d, %s%s',
                $this->s[$key]->first_name, $st['overdue_count'], $st['lessons_with_debt'],
                $st['blocked'] ? 'занятия закрыты' : 'занятия открыты', $ok ? '' : ' — не совпало со сценарием'));
        }

        if (! $this->fileLinks) {
            $this->command?->warn('  S3 не настроен (нет AWS_URL и бакета): материалы, презентация и вложения чата пропущены — без хранилища эти экраны не строят ссылки на файлы.');
        } elseif (! $this->s3) {
            $this->command?->warn('  Файлы не загружены в S3 (нет ключей доступа): в кабинетах они есть, но ссылки на них не откроются.');
        }
    }
}
