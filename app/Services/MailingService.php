<?php

namespace App\Services;

use App\Mail\MailingLetter;
use App\Models\MailingCampaign;
use App\Models\MailingContact;
use App\Models\MailingDelivery;
use App\Models\MailingLink;
use App\Models\MailingList;
use App\Models\User;
use App\Support\RichText;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;

/**
 * Почтовые рассылки на внешние адреса (школы) — админка «Рассылки».
 *
 * Списки адресов загружаются из файла или вставкой (App\Support\MailingImport). Адрес один на всю систему:
 * отписка по ссылке из письма действует на все списки и все будущие письма.
 *
 * Письмо: черновик → «Отправить» (сразу) или «Запланировать». При запуске фиксируем адресатов (mailing_deliveries)
 * и ссылки письма (mailing_links), дальше команда mailings:send раз в минуту отправляет по config('mail.newsletter.per_minute')
 * писем через отдельный почтовый канал newsletter (config/mail.php). Открытия — по картинке 1×1, переходы — через
 * /m/c/{token}/{link}, отписка — /m/u/{token} (с подтверждением; почтовые программы шлют POST «в один клик»).
 */
class MailingService
{
    /**
     * Подстановки в тему и текст: {школа}, {город}, {имя}; запасное значение — {школа|коллеги}.
     * {имя} — имя пользователя платформы (для адресов из пользователей), иначе то же, что {школа}.
     */
    public const PLACEHOLDERS = ['школа' => 'name', 'город' => 'city', 'имя' => 'first'];

    /** Кого из пользователей добавить в список. */
    public const USER_GROUPS = ['tutors' => 'Учителя', 'students' => 'Ученики', 'people' => 'Выбрать людей'];

    /** Фильтр учителей по тарифу. */
    public const TARIFF_FILTERS = ['all' => 'Все', 'paid' => 'На платном тарифе', 'free' => 'Без платного тарифа'];

    /** Подряд столько отказов сервера — значит, дело не в адресах (лимит, ключ, домен не подтверждён): ставим отправку на паузу. */
    private const FAILS_IN_A_ROW = 3;

    /** Дневной лимит сервиса почты исчерпан — пробуем снова через столько минут (лимит Postbox считается за скользящие сутки). */
    private const QUOTA_RETRY_MINUTES = 60;

    private const CHUNK = 500;

    /* ---------- Списки ---------- */

    public function createList(string $name): MailingList
    {
        return MailingList::create(['name' => trim($name)]);
    }

    public function renameList(MailingList $list, string $name): void
    {
        $list->update(['name' => trim($name)]);
    }

    /** Удалить список. Адреса, которых больше нет ни в одном списке и которым не писали, удаляются; отписки остаются навсегда. */
    public function deleteList(MailingList $list): void
    {
        DB::transaction(function () use ($list) {
            $ids = $list->contacts()->pluck('mailing_contacts.id');
            $list->delete();
            $this->deleteOrphans($ids->all());
        });
    }

    /**
     * Добавить разобранные адреса в список. $parsed — результат MailingImport.
     * Возвращает: added — новых в списке, existing — уже были в нём, unsubscribed — из добавленных отписались раньше
     * (письма им не уйдут), invalid — строки без почты.
     */
    public function import(MailingList $list, array $parsed): array
    {
        $rows = array_values($parsed['contacts'] ?? []);
        $added = 0;
        $unsubscribed = 0;

        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::transaction(function () use ($list, $chunk, &$added, &$unsubscribed) {
                $emails = array_column($chunk, 'email');
                $known = MailingContact::whereIn('email', $emails)->get()->keyBy('email');

                $now = now();
                $new = [];
                foreach ($chunk as $row) {
                    $contact = $known->get($row['email']);
                    if (! $contact) {
                        $new[] = ['email' => $row['email'], 'name' => $row['name'], 'city' => $row['city'], 'user_id' => $row['user_id'] ?? null, 'created_at' => $now, 'updated_at' => $now];
                        continue;
                    }
                    // Пустые поля дополняем из новой загрузки, заполненные не трогаем
                    $fill = array_filter([
                        'name' => $contact->name ? null : $row['name'],
                        'city' => $contact->city ? null : $row['city'],
                        'user_id' => $contact->user_id ? null : ($row['user_id'] ?? null),
                    ]);
                    if ($fill) {
                        $contact->update($fill);
                    }
                }
                if ($new) {
                    MailingContact::insertOrIgnore($new);
                }

                $contacts = MailingContact::whereIn('email', $emails)->get(['id', 'unsubscribed_at']);
                $inList = DB::table('mailing_contact_list')->where('mailing_list_id', $list->id)
                    ->whereIn('mailing_contact_id', $contacts->pluck('id'))->pluck('mailing_contact_id')->flip();

                $attach = [];
                foreach ($contacts as $c) {
                    if (! isset($inList[$c->id])) {
                        $attach[] = ['mailing_list_id' => $list->id, 'mailing_contact_id' => $c->id, 'created_at' => $now];
                        $added++;
                        $unsubscribed += $c->unsubscribed_at ? 1 : 0;
                    }
                }
                if ($attach) {
                    DB::table('mailing_contact_list')->insertOrIgnore($attach);
                }
            });
        }

        $list->touch();

        return [
            'added' => $added,
            'existing' => count($rows) - $added,
            'unsubscribed' => $unsubscribed,
            'invalid' => $parsed['invalid'] ?? [],
        ];
    }

    /**
     * Пользователи платформы для списка: учителя (все / на платном тарифе / без платного), ученики или выбранные люди.
     * Заблокированные и без почты не попадают.
     */
    public function users(string $group, string $tariff = 'all', array $ids = []): Builder
    {
        if ($group === 'people') {
            return $this->people()->whereIn('id', $ids ?: [0]);
        }

        $query = $this->people()->where('role', $group === 'students' ? User::ROLE_STUDENT : User::ROLE_TUTOR);
        if ($group === 'tutors' && $tariff !== 'all') {
            $paid = fn (Builder $q) => $q->active()->whereHas('tariff', fn (Builder $t) => $t->where('price', '>', 0));
            $tariff === 'paid' ? $query->whereHas('subscriptions', $paid) : $query->whereDoesntHave('subscriptions', $paid);
        }

        return $query;
    }

    /** Учителя и ученики, которым можно написать: не заблокированы, есть почта. */
    public function people(): Builder
    {
        return User::query()
            ->whereIn('role', [User::ROLE_TUTOR, User::ROLE_STUDENT])
            ->where(fn (Builder $q) => $q->where('is_blocked', false)->orWhereNull('is_blocked'))
            ->whereNotNull('email')->where('email', '!=', '');
    }

    /** Добавить пользователей в список (их текущие адреса почты). Итог — как у import(). */
    public function importUsers(MailingList $list, Builder $users): array
    {
        $contacts = [];
        $users->select(['id', 'name', 'email'])->orderBy('id')->each(function (User $u) use (&$contacts) {
            $email = mb_strtolower(trim((string) $u->email));
            if (filter_var($email, FILTER_VALIDATE_EMAIL, FILTER_FLAG_EMAIL_UNICODE)) {
                $contacts[$email] ??= ['email' => $email, 'name' => trim((string) $u->name) ?: null, 'city' => null, 'user_id' => $u->id];
            }
        });

        return $this->import($list, ['contacts' => $contacts, 'invalid' => []]);
    }

    /** Убрать адреса из списка. */
    public function removeContacts(MailingList $list, array $contactIds): int
    {
        return DB::transaction(function () use ($list, $contactIds) {
            $removed = $list->contacts()->detach($contactIds);
            $this->deleteOrphans($contactIds);

            return $removed;
        });
    }

    /** Адреса без списков, без писем и без отписки больше не нужны. */
    private function deleteOrphans(array $ids): void
    {
        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            MailingContact::whereIn('id', $chunk)
                ->whereNull('unsubscribed_at')
                ->whereDoesntHave('lists')
                ->whereDoesntHave('deliveries')
                ->delete();
        }
    }

    /** Списки со счётчиками: total — всего адресов, active — кому уйдёт письмо (не отписались). */
    public function lists(): \Illuminate\Support\Collection
    {
        return MailingList::query()
            ->withCount(['contacts as total', 'contacts as active' => fn (Builder $q) => $q->whereNull('unsubscribed_at')])
            ->orderBy('name')
            ->get();
    }

    /* ---------- Письма ---------- */

    /**
     * Сохранить черновик или запланированное письмо. $data: subject, preheader, body, button_text, button_url.
     * После запуска письмо не меняется.
     */
    public function save(?MailingCampaign $campaign, array $data, array $listIds, ?User $author = null): MailingCampaign
    {
        $campaign ??= new MailingCampaign(['created_by' => $author?->id, 'status' => MailingCampaign::DRAFT]);
        abort_unless($campaign->isEditable(), 409);

        $buttonText = trim((string) ($data['button_text'] ?? ''));
        $buttonUrl = trim((string) ($data['button_url'] ?? ''));

        $campaign->fill([
            'subject' => trim((string) $data['subject']),
            'preheader' => trim((string) ($data['preheader'] ?? '')) ?: null,
            'body' => RichText::clean($data['body'] ?? null),
            'button_text' => $buttonText !== '' && $buttonUrl !== '' ? $buttonText : null,
            'button_url' => $buttonText !== '' && $buttonUrl !== '' ? $buttonUrl : null,
        ])->save();

        $campaign->lists()->sync(MailingList::whereIn('id', $listIds)->pluck('id'));

        return $campaign;
    }

    /** Кому уйдёт письмо: адреса выбранных списков без отписавшихся, каждый один раз. */
    public function recipients(array $listIds): Builder
    {
        return MailingContact::query()
            ->subscribed()
            ->whereHas('lists', fn (Builder $q) => $q->whereIn('mailing_lists.id', $listIds ?: [0]));
    }

    public function schedule(MailingCampaign $campaign, CarbonInterface $at): void
    {
        abort_unless($campaign->isEditable(), 409);
        $campaign->update(['status' => MailingCampaign::SCHEDULED, 'scheduled_at' => $at]);
    }

    public function unschedule(MailingCampaign $campaign): void
    {
        abort_unless($campaign->status === MailingCampaign::SCHEDULED, 409);
        $campaign->update(['status' => MailingCampaign::DRAFT, 'scheduled_at' => null]);
    }

    /**
     * Запустить отправку: зафиксировать адресатов и ссылки. Письма уходят командой mailings:send.
     * Возвращает число адресатов (0 — письмо не запущено: уже запущено или некому отправлять).
     */
    public function start(MailingCampaign $campaign): int
    {
        // Отмечаем атомарно: двойной клик или параллельная команда не запустят дважды
        $claimed = MailingCampaign::whereKey($campaign->id)
            ->whereIn('status', [MailingCampaign::DRAFT, MailingCampaign::SCHEDULED])
            ->update(['status' => MailingCampaign::SENDING, 'started_at' => now(), 'error' => null]);
        if (! $claimed) {
            return 0;
        }
        $campaign->refresh();

        $count = 0;
        $listIds = $campaign->lists()->pluck('mailing_lists.id')->all();
        $this->recipients($listIds)->select(['id', 'email'])->chunkById(self::CHUNK, function ($contacts) use ($campaign, &$count) {
            $now = now();
            MailingDelivery::insertOrIgnore($contacts->map(fn (MailingContact $c) => [
                'mailing_campaign_id' => $campaign->id,
                'mailing_contact_id' => $c->id,
                'email' => $c->email,
                'token' => Str::random(40),
                'status' => MailingDelivery::QUEUED,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
            $count += $contacts->count();
        });

        foreach ($this->urls($campaign) as $url) {
            $campaign->links()->create(['url' => $url]);
        }

        if ($count === 0) {
            $campaign->update(['status' => MailingCampaign::SENT, 'finished_at' => now()]);
        }

        return $count;
    }

    /** Остановить отправку: неотправленные письма не уйдут. */
    public function stop(MailingCampaign $campaign): void
    {
        abort_unless($campaign->status === MailingCampaign::SENDING, 409);

        $campaign->deliveries()->where('status', MailingDelivery::QUEUED)->update(['status' => MailingDelivery::SKIPPED]);
        $campaign->update(['status' => MailingCampaign::STOPPED, 'finished_at' => now()]);
    }

    /** Копия письма — новый черновик с тем же текстом и списками. */
    public function duplicate(MailingCampaign $campaign, ?User $author = null): MailingCampaign
    {
        $copy = MailingCampaign::create([
            'subject' => $campaign->subject,
            'preheader' => $campaign->preheader,
            'body' => $campaign->body,
            'button_text' => $campaign->button_text,
            'button_url' => $campaign->button_url,
            'status' => MailingCampaign::DRAFT,
            'created_by' => $author?->id,
        ]);
        $copy->lists()->sync($campaign->lists()->pluck('mailing_lists.id'));

        return $copy;
    }

    public function delete(MailingCampaign $campaign): void
    {
        abort_if($campaign->status === MailingCampaign::SENDING, 409);
        $campaign->delete();
    }

    /** Итоги письма: всего, отправлено, ошибки, ждут, пропущены, открыли, перешли, отписались. */
    public function stats(MailingCampaign $campaign): array
    {
        $row = $campaign->deliveries()->toBase()->selectRaw('
            count(*) as total,
            sum(case when status = ? then 1 else 0 end) as sent,
            sum(case when status = ? then 1 else 0 end) as failed,
            sum(case when status = ? then 1 else 0 end) as queued,
            sum(case when status = ? then 1 else 0 end) as skipped,
            sum(case when opened_at is not null then 1 else 0 end) as opened,
            sum(case when clicked_at is not null then 1 else 0 end) as clicked,
            sum(case when unsubscribed_at is not null then 1 else 0 end) as unsubscribed
        ', [MailingDelivery::SENT, MailingDelivery::FAILED, MailingDelivery::QUEUED, MailingDelivery::SKIPPED])->first();

        return array_map('intval', (array) $row);
    }

    /* ---------- Отправка ---------- */

    /**
     * Команда mailings:send (раз в минуту): запустить запланированные, время которых пришло, и отправить
     * следующую порцию писем. Возвращает число отправленных.
     */
    public function sendDue(?int $limit = null): int
    {
        MailingCampaign::where('status', MailingCampaign::SCHEDULED)->where('scheduled_at', '<=', now())->get()
            ->each(fn (MailingCampaign $c) => $this->start($c));

        $limit ??= max(1, (int) config('mail.newsletter.per_minute', 20));
        $sent = 0;

        $campaigns = MailingCampaign::where('status', MailingCampaign::SENDING)
            ->where(fn ($q) => $q->whereNull('resume_at')->orWhere('resume_at', '<=', now()))
            ->orderBy('started_at')->get();
        foreach ($campaigns as $campaign) {
            if ($sent >= $limit) {
                break;
            }
            $sent += $this->sendBatch($campaign, $limit - $sent);
        }

        return $sent;
    }

    /** Порция писем одного письма рассылки. */
    private function sendBatch(MailingCampaign $campaign, int $limit): int
    {
        $deliveries = $campaign->deliveries()->with('contact.user')->where('status', MailingDelivery::QUEUED)->orderBy('id')->limit($limit)->get();
        $links = $campaign->links()->pluck('id', 'url')->all();

        $sent = 0;
        $paused = false;
        $failedInRow = [];
        foreach ($deliveries as $delivery) {
            // Отписался уже после запуска — не пишем
            if ($delivery->contact?->unsubscribed_at) {
                $delivery->update(['status' => MailingDelivery::SKIPPED]);
                continue;
            }

            try {
                Mail::mailer('newsletter')->to($delivery->email)->send($this->letter($campaign, $delivery->contact, $delivery, $links));
            } catch (TransportExceptionInterface $e) {
                $error = self::errorText($e);

                // Исчерпан дневной лимит: письма ждут в очереди, раз в час проверяем, обновился ли лимит
                if (self::isQuotaError($e)) {
                    MailingDelivery::whereIn('id', $failedInRow)->update(['status' => MailingDelivery::QUEUED, 'error' => null]);
                    $campaign->update(['error' => $error, 'resume_at' => now()->addMinutes(self::QUOTA_RETRY_MINUTES)]);
                    $paused = true;
                    break;
                }

                // Временный сбой (сервер недоступен, слишком часто, неверный ключ): письмо остаётся в очереди, отправка ждёт
                if (! self::isAddressError($e)) {
                    $campaign->update(['error' => $error]);
                    report($e);
                    break;
                }

                $delivery->update(['status' => MailingDelivery::FAILED, 'error' => $error]);
                $failedInRow[] = $delivery->id;
                if (count($failedInRow) >= self::FAILS_IN_A_ROW) {
                    // Подряд отказы на разные адреса — скорее всего, сервер отклоняет всё (домен не подтверждён, лимит)
                    MailingDelivery::whereIn('id', $failedInRow)->update(['status' => MailingDelivery::QUEUED, 'error' => null]);
                    $campaign->update(['error' => $error]);
                    break;
                }
                continue;
            }

            $delivery->update(['status' => MailingDelivery::SENT, 'sent_at' => now(), 'error' => null]);
            $failedInRow = [];
            $sent++;
        }

        if ($sent > 0 && ! $paused && ($campaign->error || $campaign->resume_at)) {
            $campaign->update(['error' => null, 'resume_at' => null]);
        }

        if (! $campaign->deliveries()->where('status', MailingDelivery::QUEUED)->exists()) {
            MailingCampaign::whereKey($campaign->id)->where('status', MailingCampaign::SENDING)
                ->update(['status' => MailingCampaign::SENT, 'finished_at' => now(), 'error' => null, 'resume_at' => null]);
        }

        return $sent;
    }

    /** Отказ про конкретный адрес (нет ящика, адрес неверный) — 5xx на получателя; остальное — сбой отправки. */
    private static function isAddressError(TransportExceptionInterface $e): bool
    {
        $code = $e instanceof UnexpectedResponseException ? (int) $e->getCode() : 0;

        return in_array($code, [501, 550, 551, 552, 553], true);
    }

    /** Дневной лимит сервиса почты (Postbox: «550 5.4.5 Daily sending quota exceeded»). */
    private static function isQuotaError(\Throwable $e): bool
    {
        return (bool) preg_match('/quota|5\.4\.5/i', $e->getMessage());
    }

    /** Причина отказа по-русски для админки; нераспознанное — с исходным ответом сервера. */
    public static function errorText(\Throwable $e): string
    {
        $raw = trim(preg_replace('/\s+/', ' ', $e->getMessage()));
        $known = [
            '/quota|5\.4\.5/i' => 'исчерпан дневной лимит писем в Yandex Cloud Postbox',
            '/throttl|rate exceeded|too many/i' => 'сервис почты просит отправлять медленнее',
            '/not verified|not authorized to send|identity/i' => 'адрес отправителя не подтвержден в Yandex Cloud Postbox — проверьте домен',
            '/\b535\b|authentication|credentials/i' => 'сервис почты не принял ключ доступа — проверьте логин и пароль в настройках сервера',
            '/connection|timed out|could not be established|refused/i' => 'не удалось подключиться к серверу почты',
        ];
        foreach ($known as $pattern => $text) {
            if (preg_match($pattern, $raw)) {
                return $text;
            }
        }

        return mb_substr('сервер почты не принял письмо: ' . ($raw ?: 'без объяснения'), 0, 500);
    }

    /**
     * Пробное письмо на один адрес: как увидит первый адресат (или без подстановок), ссылки без учёта переходов.
     *
     * @throws TransportExceptionInterface
     */
    public function sendTest(MailingCampaign $campaign, string $email): void
    {
        $contact = $this->recipients($campaign->lists()->pluck('mailing_lists.id')->all())->orderBy('id')->first();
        Mail::mailer('newsletter')->to($email)->send($this->letter($campaign, $contact));
    }

    /** Письмо в браузере (предпросмотр в админке): HTML как в почте, без учёта открытий. */
    public function previewHtml(MailingCampaign $campaign, ?MailingContact $contact = null): string
    {
        return $this->letter($campaign, $contact)->render();
    }

    /**
     * Письмо адресату. С $delivery — с учётом открытий и переходов (ссылки через /m/c/…) и рабочей отпиской;
     * без — пробное или предпросмотр.
     */
    public function letter(MailingCampaign $campaign, ?MailingContact $contact = null, ?MailingDelivery $delivery = null, ?array $links = null): MailingLetter
    {
        $values = ['name' => $contact?->name, 'city' => $contact?->city, 'first' => self::firstName($contact)];
        $token = $delivery?->token;
        $links ??= $token ? $campaign->links()->pluck('id', 'url')->all() : [];

        $track = function (string $url) use ($token, $links): string {
            return $token && isset($links[$url]) ? route('mailing.click', ['token' => $token, 'link' => $links[$url]]) : $url;
        };

        $body = self::fill((string) $campaign->body, $values, html: true);
        $body = preg_replace_callback('/(<a\s[^>]*href=")(https?:\/\/[^"]+)(")/i', function ($m) use ($track) {
            return $m[1] . e($track(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5))) . $m[3];
        }, $body);
        // Ссылки из письма открываются в новой вкладке (в веб-почте), как в кабинетах
        $body = preg_replace('/<a(?=\s)(?![^>]*target=)/i', '<a target="_blank" rel="noopener"', $body);

        return new MailingLetter(
            subjectLine: self::fill($campaign->subject, $values),
            preheader: $campaign->preheader ? self::fill($campaign->preheader, $values) : null,
            bodyHtml: $body,
            buttonText: $campaign->button_text ? self::fill($campaign->button_text, $values) : null,
            buttonUrl: $campaign->button_url ? $track($campaign->button_url) : null,
            unsubscribeUrl: route('mailing.unsubscribe', ['token' => $token ?? 'test']),
            pixelUrl: $token ? route('mailing.open', ['token' => $token]) : null,
            oneClick: (bool) $token,
        );
    }

    /** Для {имя}: имя и отчество пользователя платформы («Иван Петрович»), иначе название из списка. */
    private static function firstName(?MailingContact $contact): ?string
    {
        $user = $contact?->user_id ? $contact->user : null;
        $first = trim($user?->first_name . ' ' . $user?->middle_name);

        return ($first ?: trim((string) ($user?->name ?: $contact?->name))) ?: null;
    }

    /** Подставить {школа}, {город}, {имя} (запасное — после «|»). В HTML значения экранируются. */
    public static function fill(string $text, array $values, bool $html = false): string
    {
        return preg_replace_callback('/\{(' . implode('|', array_keys(self::PLACEHOLDERS)) . ')(?:\|([^{}]*))?\}/u', function ($m) use ($values, $html) {
            $value = trim((string) ($values[self::PLACEHOLDERS[$m[1]]] ?? ''));
            if ($value === '') {
                return $m[2] ?? '';
            }

            return $html ? e($value) : $value;
        }, $text);
    }

    /** Ссылки письма для учёта переходов: из текста и кнопки. */
    private function urls(MailingCampaign $campaign): array
    {
        preg_match_all('/<a\s[^>]*href="(https?:\/\/[^"]+)"/i', (string) $campaign->body, $m);
        $urls = array_map(fn ($u) => html_entity_decode($u, ENT_QUOTES | ENT_HTML5), $m[1]);
        if ($campaign->button_url) {
            $urls[] = $campaign->button_url;
        }

        return array_values(array_unique(array_filter($urls, fn ($u) => mb_strlen($u) <= 2048)));
    }

    /* ---------- Открытия, переходы, отписка ---------- */

    public function trackOpen(string $token): void
    {
        $delivery = MailingDelivery::where('token', $token)->first();
        if (! $delivery) {
            return;
        }

        MailingDelivery::whereKey($delivery->id)->update([
            'opens' => DB::raw('opens + 1'),
            'opened_at' => $delivery->opened_at ?? now(),
        ]);
    }

    /** Переход по ссылке: отмечаем и возвращаем адрес, куда вести (null — ссылка не найдена). */
    public function trackClick(string $token, int $linkId): ?string
    {
        $delivery = MailingDelivery::where('token', $token)->first();
        $link = $delivery ? MailingLink::whereKey($linkId)->where('mailing_campaign_id', $delivery->mailing_campaign_id)->first() : null;
        if (! $link) {
            return null;
        }

        MailingLink::whereKey($link->id)->increment('clicks');
        // Переход без загрузки картинок — письмо всё равно открыли
        MailingDelivery::whereKey($delivery->id)->update([
            'clicks' => DB::raw('clicks + 1'),
            'clicked_at' => $delivery->clicked_at ?? now(),
            'opened_at' => $delivery->opened_at ?? now(),
        ]);

        return $link->url;
    }

    public function delivery(string $token): ?MailingDelivery
    {
        return MailingDelivery::with('contact')->where('token', $token)->first();
    }

    /** Отписка по ссылке из письма: адрес больше не получит ни одной рассылки. */
    public function unsubscribe(MailingDelivery $delivery): void
    {
        DB::transaction(function () use ($delivery) {
            MailingContact::where('email', $delivery->email)->whereNull('unsubscribed_at')->update(['unsubscribed_at' => now()]);
            if (! $delivery->unsubscribed_at) {
                $delivery->update(['unsubscribed_at' => now()]);
            }
            // Другие письма, которые ещё ждут отправки этому адресу, не уйдут
            MailingDelivery::where('email', $delivery->email)->where('status', MailingDelivery::QUEUED)
                ->update(['status' => MailingDelivery::SKIPPED]);
        });
    }
}
