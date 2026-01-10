<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Pages\Auth\Register;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use App\Filament\Resources\UserResource;

class UserValidationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function password_is_required_on_create()
    {
        $admin = User::firstOrCreate(['email' => 'admin@example.com'], [
            'name' => 'Admin',
            'username' => 'admin',
            'password' => bcrypt('password'),
            'role' => User::ROLE_ADMIN,
        ]);

        $this->actingAs($admin);

        Livewire::test(UserResource\Pages\CreateUser::class)
            ->set('data.name', 'Test User')
            ->set('data.email', 'test@example.com')
            ->set('data.username', 'testuser')
            ->set('data.password', '') // Empty password
            ->call('create')
            ->assertHasErrors(['data.password' => 'required']);
    }

    /** @test */
    public function password_is_nullable_on_edit()
    {
        $admin = User::firstOrCreate(['email' => 'admin@example.com'], [
            'name' => 'Admin',
            'username' => 'admin',
            'password' => bcrypt('password'),
            'role' => User::ROLE_ADMIN,
        ]);

        $user = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'username' => 'johndoe',
            'role' => User::ROLE_STUDENT,
        ]);

        $this->actingAs($admin);

        Livewire::test(UserResource\Pages\EditUser::class, ['record' => $user->getRouteKey()])
            ->set('data.password', '') // Empty password should be allowed on edit
            ->call('save')
            ->assertHasNoErrors(['data.password']);
    }
}
