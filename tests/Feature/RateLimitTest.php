<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Login, registration and password reset each have their own budget. */
class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    private function badLogin(string $email)
    {
        return $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'wrong']);
    }

    public function test_repeated_wrong_passwords_for_one_account_get_429(): void
    {
        User::create(['name' => 'A', 'email' => 'a@t.test', 'password' => 'password', 'role' => 'client']);

        for ($i = 0; $i < 6; $i++) {
            $this->badLogin('a@t.test')->assertStatus(401);
        }
        $this->badLogin('a@t.test')->assertStatus(429);
    }

    public function test_one_locked_account_does_not_lock_everyone_on_the_same_connection(): void
    {
        User::create(['name' => 'A', 'email' => 'a@t.test', 'password' => 'password', 'role' => 'client']);
        User::create(['name' => 'B', 'email' => 'b@t.test', 'password' => 'password', 'role' => 'client']);

        for ($i = 0; $i < 7; $i++) {
            $this->badLogin('a@t.test');
        }

        // Same IP, different person: still allowed, and a correct password works.
        $this->postJson('/api/auth/login', ['email' => 'b@t.test', 'password' => 'password'])->assertOk();
    }

    public function test_the_email_is_case_insensitive_for_the_limit(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->badLogin($i % 2 ? 'A@T.TEST' : 'a@t.test');
        }

        $this->badLogin('A@t.test')->assertStatus(429);
    }

    public function test_password_reset_requests_do_not_use_up_login_attempts(): void
    {
        User::create(['name' => 'A', 'email' => 'a@t.test', 'password' => 'password', 'role' => 'client']);

        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/auth/forgot-password', ['email' => 'a@t.test']);
        }
        $this->postJson('/api/auth/forgot-password', ['email' => 'a@t.test'])->assertStatus(429);

        $this->postJson('/api/auth/login', ['email' => 'a@t.test', 'password' => 'password'])->assertOk();
    }

    public function test_registrations_have_their_own_budget(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/auth/register', ['name' => "N$i", 'email' => "n$i@t.test", 'password' => 'long-enough-1'])->assertCreated();
        }
        $this->postJson('/api/auth/register', ['name' => 'N7', 'email' => 'n7@t.test', 'password' => 'long-enough-1'])->assertStatus(429);

        User::create(['name' => 'A', 'email' => 'a@t.test', 'password' => 'password', 'role' => 'client']);
        $this->postJson('/api/auth/login', ['email' => 'a@t.test', 'password' => 'password'])->assertOk();
    }

    public function test_signed_in_calls_are_counted_per_user_not_per_connection(): void
    {
        $first = User::create(['name' => 'F', 'email' => 'f@t.test', 'password' => 'password', 'role' => 'admin']);
        $second = User::create(['name' => 'S', 'email' => 's@t.test', 'password' => 'password', 'role' => 'admin']);

        Sanctum::actingAs($first);
        for ($i = 0; $i < 30; $i++) {
            $this->getJson('/api/appointments')->assertOk();
        }
        $this->getJson('/api/appointments')->assertStatus(429);

        Sanctum::actingAs($second); // a colleague on the same Wi-Fi is unaffected
        $this->getJson('/api/appointments')->assertOk();
    }
}
