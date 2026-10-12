<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Stylist;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Accounts and their related records are created together or not at all. */
class AccountCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_a_client_account_and_record(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'New Person', 'email' => 'new@t.test', 'password' => 'long-enough-1'])
            ->assertCreated()->assertJsonStructure(['user', 'token']);

        $user = User::where('email', 'new@t.test')->firstOrFail();
        $this->assertSame('client', $user->role);
        $this->assertSame(1, Client::where('user_id', $user->id)->count());
    }

    public function test_a_failed_registration_leaves_no_half_created_account(): void
    {
        Client::creating(function () {
            throw new \RuntimeException('client table unavailable');
        });

        $this->withoutExceptionHandling();
        try {
            $this->postJson('/api/auth/register', ['name' => 'Half', 'email' => 'half@t.test', 'password' => 'long-enough-1']);
            $this->fail('registration should have failed');
        } catch (\RuntimeException $e) {
            $this->assertSame('client table unavailable', $e->getMessage());
        }

        $this->assertSame(0, User::where('email', 'half@t.test')->count());
    }

    public function test_creating_a_stylist_with_a_login(): void
    {
        Sanctum::actingAs(User::create(['name' => 'Boss', 'email' => 'boss@t.test', 'password' => 'password', 'role' => 'admin']));

        $res = $this->postJson('/api/stylists', ['name' => 'Ana', 'role' => 'Colorist', 'email' => 'ana@t.test'])->assertCreated();

        $stylist = Stylist::findOrFail($res->json('id'));
        $login = User::where('email', 'ana@t.test')->firstOrFail();
        $this->assertSame('stylist', $login->role);
        $this->assertSame($stylist->id, $login->stylist_id);
    }

    public function test_a_failed_login_creation_leaves_no_orphan_stylist(): void
    {
        Sanctum::actingAs(User::create(['name' => 'Boss', 'email' => 'boss@t.test', 'password' => 'password', 'role' => 'admin']));
        User::creating(function ($user) {
            if ($user->email === 'ana@t.test') {
                throw new \RuntimeException('user table unavailable');
            }
        });

        $this->withoutExceptionHandling();
        try {
            $this->postJson('/api/stylists', ['name' => 'Ana', 'role' => 'Colorist', 'email' => 'ana@t.test']);
            $this->fail('creation should have failed');
        } catch (\RuntimeException $e) {
            $this->assertSame('user table unavailable', $e->getMessage());
        }

        $this->assertSame(0, Stylist::where('name', 'Ana')->count());
    }

    public function test_the_default_seeder_refuses_to_run_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        try {
            (new DatabaseSeeder)->run();
            $this->fail('the seeder should have refused to run');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('must not run in production', $e->getMessage());
        }

        $this->assertSame(0, User::where('email', 'test@example.com')->count());
    }

    public function test_the_default_seeder_still_works_locally(): void
    {
        (new DatabaseSeeder)->run();

        $this->assertSame(1, User::where('email', 'test@example.com')->count());
    }
}
