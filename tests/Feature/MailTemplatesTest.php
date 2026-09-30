<?php

namespace Tests\Feature;

use App\Mail\StudentInvitation;
use App\Mail\TeacherApplicationApproved;
use App\Mail\TeacherApplicationRejected;
use App\Models\User;
use App\Notifications\EmailVerificationCode;
use App\Notifications\SubscriptionPaid;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Письма Serdal собираются на общем каркасе (resources/views/vendor/mail) и без стандартных фраз Laravel. */
class MailTemplatesTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['role' => User::ROLE_TUTOR, 'username' => 't' . uniqid(), 'first_name' => 'Анна', 'email' => 'anna@example.com']);
    }

    /** @return array<string, string> название => HTML письма */
    private function mails(): array
    {
        $user = $this->user();

        return [
            'уведомление кабинета' => (string) (new SubscriptionPaid('Базовый', 1490, now()->addMonth()))->toMail($user)->render(),
            'код подтверждения' => (string) (new EmailVerificationCode('482913'))->toMail($user)->render(),
            'восстановление пароля' => (string) (new ResetPassword('token'))->toMail($user)->render(),
            'приглашение ученика' => (new StudentInvitation('https://serdal.ru/invite/abc', 'Мария Соколова'))->render(),
            'заявка одобрена' => (new TeacherApplicationApproved($user, 'secret-pass'))->render(),
            'заявка отклонена' => (new TeacherApplicationRejected('Нет опыта'))->render(),
        ];
    }

    public function test_every_mail_uses_the_branded_layout(): void
    {
        foreach ($this->mails() as $name => $html) {
            $this->assertStringContainsString('class="inner-body"', $html, $name);
            $this->assertStringContainsString('images/logo.png', $html, $name);
            $this->assertStringContainsString('<h1', $html, $name);
            // Стандартные фразы Laravel и сырые markdown-остатки в письмо не попадают
            foreach (['Все права защищены', 'Если у Вас возникли проблемы', 'All rights reserved', 'Regards', '<pre>', '<code>'] as $bad) {
                $this->assertStringNotContainsString($bad, $html, $name . ': ' . $bad);
            }
        }
    }

    public function test_mail_contents(): void
    {
        $mails = $this->mails();

        $this->assertStringContainsString('Тариф оплачен', $mails['уведомление кабинета']);
        $this->assertStringContainsString('Кнопка не открывается?', $mails['уведомление кабинета']);
        $this->assertStringContainsString('482913', $mails['код подтверждения']);
        $this->assertStringContainsString('Задать новый пароль', $mails['восстановление пароля']);
        $this->assertStringContainsString('reset-password/token', $mails['восстановление пароля']);
        $this->assertStringContainsString('Мария Соколова приглашает вас заниматься', $mails['приглашение ученика']);
        $this->assertStringContainsString('secret-pass', $mails['заявка одобрена']);
        $this->assertStringContainsString('anna@example.com', $mails['заявка одобрена']);
        $this->assertStringContainsString('Нет опыта', $mails['заявка отклонена']);
    }
}
