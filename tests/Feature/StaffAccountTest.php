<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Stylist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffAccountTest extends TestCase
{
    use RefreshDatabase;

    private function as(string $role): User
    {
        $u = User::create(['name' => ucfirst($role), 'email' => "$role@t.test", 'password' => 'password', 'role' => $role]);
        Sanctum::actingAs($u);

        return $u;
    }

    public function test_admin_creates_a_manager_who_can_log_in_with_the_temp_password(): void
    {
        $this->as('admin');

        $res = $this->postJson('/api/staff', ['name' => 'Mia', 'email' => 'mia@t.test', 'role' => 'manager'])->assertCreated();
        $this->assertSame('manager', User::where('email', 'mia@t.test')->value('role'));
        $this->assertSame(0, Stylist::count());
        $this->assertSame(1, AuditLog::where('action', 'staff.created')->count());

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/login', ['email' => 'mia@t.test', 'password' => $res->json('temp_password')])->assertOk();
    }

    public function test_admin_creates_a_stylist_with_a_profile_and_needs_a_title(): void
    {
        $this->as('admin');

        $this->postJson('/api/staff', ['name' => 'Ana', 'email' => 'ana@t.test', 'role' => 'stylist'])->assertStatus(422);

        $this->postJson('/api/staff', ['name' => 'Ana', 'email' => 'ana@t.test', 'role' => 'stylist', 'title' => 'Colorist'])->assertCreated();
        $login = User::where('email', 'ana@t.test')->firstOrFail();
        $this->assertSame('Colorist', Stylist::findOrFail($login->stylist_id)->role);
    }

    public function test_admin_cannot_create_another_admin_or_client_here_and_email_must_be_unique(): void
    {
        $this->as('admin');

        $this->postJson('/api/staff', ['name' => 'X', 'email' => 'x@t.test', 'role' => 'admin'])->assertStatus(422);
        $this->postJson('/api/staff', ['name' => 'X', 'email' => 'admin@t.test', 'role' => 'manager'])->assertStatus(422);
    }

    public function test_non_admins_cannot_list_or_create_staff(): void
    {
        foreach (['manager', 'stylist', 'client'] as $role) {
            $this->as($role);
            $this->getJson('/api/staff')->assertForbidden();
            $this->postJson('/api/staff', ['name' => 'Z', 'email' => "z-$role@t.test", 'role' => 'manager'])->assertForbidden();
        }
        $this->assertSame(0, User::where('email', 'like', 'z-%')->count());
    }

    public function test_guests_are_rejected(): void
    {
        $this->postJson('/api/staff', [])->assertUnauthorized();
    }
}
