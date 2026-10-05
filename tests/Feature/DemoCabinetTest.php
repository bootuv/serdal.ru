<?php

namespace Tests\Feature;

use App\Demo\Routes;
use Tests\TestCase;

/**
 * Демо-кабинет на странице «О платформе» (App\Demo): настоящие шаблоны экранов с выдуманными данными.
 * Поменялся шаблон или данные настоящего экрана — демо должно по-прежнему открываться.
 */
class DemoCabinetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /** Каждый экран (и его состояния из EXAMPLES) открывается, без ссылок в настоящий кабинет. */
    public function test_every_demo_screen_renders(): void
    {
        $table = Routes::table('teacher');
        $this->assertNotEmpty($table);

        foreach ($table as $path => $class) {
            $urls = defined($class . '::EXAMPLES') ? $class::EXAMPLES : [$path];

            foreach ($urls as $url) {
                $response = $this->get('/demo/teacher' . ($url === '' ? '' : '/' . ltrim($url, '/')));

                $response->assertOk();
                $html = $response->getContent();
                $this->assertStringNotContainsString('/cabinet/teacher', $html, "Ссылка в настоящий кабинет: {$url}");
                // Ссылки, которые работают на проде: подписанные (приглашение ученика) и вход гостем в комнату
                $this->assertStringNotContainsString('signature=', $html, "Подписанная ссылка: {$url}");
                $this->assertDoesNotMatchRegularExpression('~/rooms/\d+/join~', $html, "Вход в настоящую комнату: {$url}");
            }
        }
    }

    /** Демо не ставит куку сессии и не «логинит» посетителя. */
    public function test_demo_has_no_session(): void
    {
        $response = $this->get('/demo/teacher');

        $response->assertOk();
        $this->assertEmpty($response->headers->getCookies());
        $this->assertGuest();
    }

    public function test_unknown_screen_is_404(): void
    {
        $this->get('/demo/teacher/no-such-screen')->assertNotFound();
    }
}
