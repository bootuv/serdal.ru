<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Правка полного имени не теряется у пользователей с фамилией/именем/отчеством (приглашённые ученики). */
class UserNameEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_full_name_updates_parts(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_STUDENT, 'username' => 'u' . uniqid(),
            'last_name' => 'Смирнова', 'first_name' => 'Алина', 'middle_name' => 'Сергеевна']);
        $this->assertSame('Смирнова Алина Сергеевна', $user->name);

        $user->update(['name' => 'Иванова Алина']);
        $user->refresh();

        $this->assertSame('Иванова Алина', $user->name);
        $this->assertSame('Иванова', $user->last_name);
        $this->assertSame('Алина', $user->first_name);
        $this->assertNull($user->middle_name);
    }

    public function test_editing_parts_still_rebuilds_full_name(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_TUTOR, 'username' => 'u' . uniqid(),
            'last_name' => 'Соколова', 'first_name' => 'Мария']);

        $user->update(['first_name' => 'Марина']);

        $this->assertSame('Соколова Марина', $user->fresh()->name);
    }

    public function test_user_without_parts_keeps_plain_name(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_STUDENT, 'username' => 'u' . uniqid(), 'name' => 'Алина']);
        $user->update(['name' => 'Алина С.']);

        $this->assertSame('Алина С.', $user->fresh()->name);
    }
}
