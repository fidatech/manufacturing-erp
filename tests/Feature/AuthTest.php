<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_login_page_loads(): void
    {
        $this->get(route('login'))->assertOk()->assertSee(__('app.auth.submit'));
    }

    public function test_user_can_sign_in_and_sees_the_dashboard(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'correct-password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->get(route('dashboard'))->assertOk()->assertSee($user->name);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);

        $this->from(route('login'))
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_sign_in_is_paused_after_five_wrong_passwords(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'correct-password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_can_sign_out(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_signed_in_users_skip_the_login_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('login'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_create_admin_command_creates_a_user(): void
    {
        $this->artisan('app:create-admin')
            ->expectsQuestion('Full name', 'First Admin')
            ->expectsQuestion('Email address', 'Admin@Example.com')
            ->expectsQuestion('Password (at least 8 characters)', 'a-long-password')
            ->expectsQuestion('Repeat the password', 'a-long-password')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'admin@example.com', 'name' => 'First Admin']);
    }

    public function test_create_admin_command_refuses_a_short_password(): void
    {
        $this->artisan('app:create-admin')
            ->expectsQuestion('Full name', 'First Admin')
            ->expectsQuestion('Email address', 'admin@example.com')
            ->expectsQuestion('Password (at least 8 characters)', 'short')
            ->expectsQuestion('Repeat the password', 'short')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
