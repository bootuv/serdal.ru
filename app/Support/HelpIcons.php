<?php

namespace App\Support;

/**
 * Иконки категорий базы знаний. В поле help_categories.icon хранится ключ линейной иконки кабинета
 * (x-ui.icon). В старых данных там могут остаться эмодзи — они показываются как есть.
 */
class HelpIcons
{
    /** Ключ иконки => подпись для выбора в окне категории. Порядок — как в окне. */
    public const ICONS = [
        'home' => 'Дом',
        'video' => 'Видео',
        'calendar' => 'Календарь',
        'tasks' => 'Задание',
        'folder' => 'Папка',
        'wallet' => 'Оплата',
        'users' => 'Люди',
        'user' => 'Человек',
        'chat' => 'Сообщение',
        'star' => 'Звезда',
        'bell' => 'Колокольчик',
        'help' => 'Вопрос',
        'clock' => 'Часы',
        'lock' => 'Замок',
        'mail' => 'Письмо',
    ];

    /** Иконка новой категории по умолчанию. */
    public const DEFAULT = 'folder';

    /** Эмодзи из старых данных => ключ иконки (миграция 2026_09_29_100000). */
    public const EMOJI = [
        '🎓' => 'home', '🚀' => 'home', '👋' => 'home', '🏁' => 'home', '🏠' => 'home', '✨' => 'home',
        '🎥' => 'video', '📹' => 'video', '🎬' => 'video', '📺' => 'video', '💻' => 'video', '🖥' => 'video', '🖥️' => 'video', '📷' => 'video',
        '📅' => 'calendar', '🗓' => 'calendar', '🗓️' => 'calendar', '📆' => 'calendar',
        '📝' => 'tasks', '✅' => 'tasks', '📋' => 'tasks', '✍️' => 'tasks', '✏️' => 'tasks',
        '📁' => 'folder', '📂' => 'folder', '📚' => 'folder', '📖' => 'folder', '🗂' => 'folder', '🗂️' => 'folder',
        '💳' => 'wallet', '💰' => 'wallet', '💵' => 'wallet', '💸' => 'wallet', '🧾' => 'wallet',
        '👥' => 'users', '🧑‍🏫' => 'users', '👨‍🏫' => 'users', '👩‍🏫' => 'users', '👨‍👩‍👧' => 'users',
        '👤' => 'user', '🙋' => 'user', '🧑‍🎓' => 'user', '👨‍🎓' => 'user', '👩‍🎓' => 'user',
        '💬' => 'chat', '🗨' => 'chat', '🗨️' => 'chat',
        '⭐' => 'star', '⭐️' => 'star', '🌟' => 'star', '🏆' => 'star',
        '🔔' => 'bell', '📣' => 'bell', '📢' => 'bell',
        '❓' => 'help', '❔' => 'help', '🆘' => 'help', '🛟' => 'help', '💡' => 'help', 'ℹ️' => 'help',
        '⏰' => 'clock', '🕐' => 'clock', '⏱' => 'clock', '⏱️' => 'clock', '⌛' => 'clock', '⏳' => 'clock',
        '🔒' => 'lock', '🔐' => 'lock', '🔑' => 'lock', '🛡' => 'lock', '🛡️' => 'lock',
        '✉️' => 'mail', '📧' => 'mail', '📨' => 'mail', '📩' => 'mail', '📬' => 'mail',
    ];

    /** Ключ иконки => эмодзи (для отката миграции). */
    public const TO_EMOJI = [
        'home' => '🎓', 'video' => '🎥', 'calendar' => '📅', 'tasks' => '📝', 'folder' => '📁',
        'wallet' => '💳', 'users' => '👥', 'user' => '👤', 'chat' => '💬', 'star' => '⭐',
        'bell' => '🔔', 'help' => '❓', 'clock' => '⏰', 'lock' => '🔒', 'mail' => '✉️',
    ];

    /** Это ключ линейной иконки (а не эмодзи из старых данных)? */
    public static function isKey(?string $value): bool
    {
        return $value !== null && array_key_exists($value, self::ICONS);
    }
}
