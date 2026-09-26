<?php

namespace App\Services;

use App\Models\MeetingSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Активность учеников на проведённом занятии по событиям класса (MeetingSession.analytics_data, вебхуки BigBlueButton):
 * время в классе, время с микрофоном и камерой, оценка активности 0–10, число голосований.
 * Оценка — как в старом отчёте (MeetingSessionResource): минуты с микрофоном × 2 + сообщения + реакции + поднятая рука × 2, не больше 10.
 */
class LessonActivityService
{
    public const MAX_SCORE = 10;

    /** Оценка активности участника 0–10. $talkSeconds — время с микрофоном, если посчитано точнее, чем talking_time. */
    public static function score(array $participant, ?int $talkSeconds = null): int
    {
        $raw = (($talkSeconds ?? $participant['talking_time'] ?? 0) / 60) * 2
            + ($participant['message_count'] ?? 0)
            + ($participant['emoji_count'] ?? 0)
            + ($participant['raise_hand_count'] ?? 0) * 2;

        return (int) min(self::MAX_SCORE, round($raw));
    }

    /** Сколько голосований провели на занятии. */
    public static function polls(MeetingSession $session): int
    {
        return (int) ($session->analytics_data['poll_count'] ?? 0);
    }

    /**
     * Показатели участников по user_id: минуты в классе, с микрофоном и с камерой, оценка активности.
     * Микрофон и камера, не выключенные до конца занятия, считаются до его завершения.
     *
     * @return Collection<string, array{minutes:int, talk:int, camera:int, score:int}>
     */
    public static function participants(MeetingSession $session): Collection
    {
        $start = $session->started_at;
        $end = $session->ended_at ?? now();

        return collect($session->analytics_data['participants'] ?? [])
            ->filter(fn ($p) => is_array($p) && isset($p['user_id']))
            ->mapWithKeys(function (array $p) use ($start, $end) {
                $in = self::seconds($p, $start, $end);
                $talk = min($in, (int) ($p['talking_time'] ?? 0) + self::open($p['audio_started_at'] ?? null, $end));
                $camera = min($in, (int) ($p['webcam_time'] ?? 0) + self::open($p['cam_started_at'] ?? null, $end));

                return [(string) $p['user_id'] => [
                    'minutes' => (int) round($in / 60),
                    'talk' => (int) round($talk / 60),
                    'camera' => (int) round($camera / 60),
                    'score' => self::score($p, $talk),
                ]];
            });
    }

    /** Время в классе: от первого входа до выхода (или до конца занятия, если после последнего входа не выходил). */
    private static function seconds(array $p, ?Carbon $start, Carbon $end): int
    {
        $joined = self::time($p['joined_at'] ?? null);
        if (! $joined) {
            return 0;
        }

        $left = self::time($p['left_at'] ?? null);
        $rejoined = self::time($p['last_joined_at'] ?? null);
        $out = $left && (! $rejoined || $left->gte($rejoined)) ? $left : $end;

        if ($start && $joined->lt($start)) {
            $joined = $start->copy();
        }
        if ($out->gt($end)) {
            $out = $end->copy();
        }

        return max(0, (int) $joined->diffInSeconds($out, false));
    }

    /** Секунды с незакрытого включения (микрофон, камера) до конца занятия. */
    private static function open(?string $since, Carbon $end): int
    {
        $at = self::time($since);

        return $at ? max(0, (int) $at->diffInSeconds($end, false)) : 0;
    }

    private static function time(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->timezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }
}
