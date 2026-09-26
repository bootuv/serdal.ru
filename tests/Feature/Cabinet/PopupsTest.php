<?php

namespace Tests\Feature\Cabinet;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/** Всплывающие окна закрываются кликом мимо окна; картинки открываются в лайтбоксе. */
class PopupsTest extends TestCase
{
    use RefreshDatabase;

    public function test_modal_closes_on_backdrop_click_and_escape(): void
    {
        $html = Blade::render('<x-ui.modal title="Окно" close="closeX">Текст</x-ui.modal>');
        $this->assertStringContainsString('if (down && $event.target === $el) $wire.closeX()', $html);
        $this->assertStringContainsString('if (! document.body.dataset.lightbox) $wire.closeX()', $html);

        // Выражение вместо метода вызывается как есть
        $html = Blade::render('<x-ui.modal title="Окно" close="$set(\'open\', false)">Текст</x-ui.modal>');
        $this->assertStringContainsString('$wire.$set(&#039;open&#039;, false); down = false', $html);
    }

    public function test_cabinet_pages_have_lightbox(): void
    {
        $this->withoutVite();
        $teacher = User::factory()->create(['role' => User::ROLE_TUTOR, 'username' => 't' . uniqid(), 'is_profile_completed' => true]);

        $this->actingAs($teacher)->get(route('cabinet.teacher.today'))
            ->assertOk()
            ->assertSee('aria-label="Просмотр изображения"', false);
    }

    /** Логотип — и в меню, и в верхней полосе на телефоне — ведёт на главный экран кабинета. */
    public function test_logo_leads_to_cabinet_home(): void
    {
        $this->withoutVite();
        $student = User::factory()->create(['role' => User::ROLE_STUDENT, 'username' => 's' . uniqid(), 'is_profile_completed' => true]);

        $html = $this->actingAs($student)->get(route('cabinet.student.profile'))->assertOk()->getContent();
        $this->assertSame(2, preg_match_all('#<a href="' . preg_quote(route('cabinet.student.home'), '#') . '"[^>]*><img src="[^"]*Logo\.svg"#', $html));
    }
}
