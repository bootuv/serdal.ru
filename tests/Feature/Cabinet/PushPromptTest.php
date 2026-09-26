<?php

namespace Tests\Feature\Cabinet;

use App\Livewire\Cabinet\PushPrompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Окна «Включить уведомления?» и «Уведомления заблокированы» (SyPush, SyPushBlocked). */
class PushPromptTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attrs = []): User
    {
        return User::factory()->create(array_merge(['role' => User::ROLE_STUDENT, 'username' => 's' . uniqid(), 'is_active' => true, 'is_blocked' => false], $attrs));
    }

    public function test_later_snoozes_for_a_week_and_stops_after_three_times(): void
    {
        $user = $this->user();

        Livewire::actingAs($user)->test(PushPrompt::class)
            ->assertSet('mayAsk', true)
            ->call('later')
            ->assertSet('mayAsk', false)
            ->assertDispatched('toast', message: 'Хорошо, напомним через неделю');

        $user->refresh();
        $this->assertSame(1, $user->push_reminder_count);
        $this->assertTrue($user->push_reminder_at->isFuture());
        Livewire::actingAs($user)->test(PushPrompt::class)->assertSet('mayAsk', false);

        $user->forceFill(['push_reminder_at' => now()->subDay(), 'push_reminder_count' => 3])->save();
        Livewire::actingAs($user)->test(PushPrompt::class)->assertSet('mayAsk', false);
    }

    public function test_blocked_instructions(): void
    {
        Livewire::actingAs($this->user())->test(PushPrompt::class)
            ->dispatch('push-blocked')
            ->assertSee('Уведомления заблокированы')
            ->assertSee('В пункте «Уведомления» выберите «Разрешить»')
            ->call('closeBlocked')
            ->assertDontSee('Уведомления заблокированы');
    }
}
