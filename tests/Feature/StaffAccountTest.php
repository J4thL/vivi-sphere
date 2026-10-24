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

        $res = $this->postJson('/api/staff', ['name' => 'Mia', 'email' => 'mia@t.test', 'role' => 'manager', 'branch_id' => 1])->assertCreated();
        $this->assertSame('manager', User::where('email', 'mia@t.test')->value('role'));
        $this->assertSame(0, Stylist::count());
        $this->assertSame(1, AuditLog::where('action', 'staff.created')->count());

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/login', ['email' => 'mia@t.test', 'password' => $res->json('temp_password')])->assertOk();
    }

    public function test_admin_creates_a_stylist_with_a_profile_and_needs_a_title(): void
    {
        $this->as('admin');

        $this->postJson('/api/staff', ['name' => 'Ana', 'email' => 'ana@t.test', 'role' => 'stylist', 'branch_id' => 1])->assertStatus(422);

        $this->postJson('/api/staff', ['name' => 'Ana', 'email' => 'ana@t.test', 'role' => 'stylist', 'title' => 'Colorist', 'branch_id' => 1])->assertCreated();
        $login = User::where('email', 'ana@t.test')->firstOrFail();
        $this->assertSame('Colorist', Stylist::findOrFail($login->stylist_id)->role);
    }

    public function test_admin_cannot_create_another_admin_or_client_here_and_email_must_be_unique(): void
    {
        $this->as('admin');

        $this->postJson('/api/staff', ['name' => 'X', 'email' => 'x@t.test', 'role' => 'admin'])->assertStatus(422);
        $this->postJson('/api/staff', ['name' => 'X', 'email' => 'admin@t.test', 'role' => 'manager', 'branch_id' => 1])->assertStatus(422);
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

    public function test_a_disabled_staff_login_is_refused_signed_out_and_can_be_re_enabled(): void
    {
        $admin = $this->as('admin');
        $staff = User::create(['name' => 'Mia', 'email' => 'mia@t.test', 'password' => 'password', 'role' => 'manager']);
        $staff->createToken('web');

        $this->patchJson("/api/staff/{$staff->id}", ['disabled' => true])->assertOk();
        $this->assertNotNull($staff->fresh()->disabled_at);
        $this->assertSame(0, $staff->tokens()->count());
        $this->assertSame(1, AuditLog::where('action', 'staff.disabled')->count());

        $this->postJson('/api/auth/login', ['email' => 'mia@t.test', 'password' => 'password'])->assertForbidden();
        $this->postJson('/api/auth/login', ['email' => 'mia@t.test', 'password' => 'wrong-pass'])->assertUnauthorized();

        $this->patchJson("/api/staff/{$staff->id}", ['disabled' => false])->assertOk();
        $this->postJson('/api/auth/login', ['email' => 'mia@t.test', 'password' => 'password'])->assertOk();
        $this->assertSame($admin->id, AuditLog::where('action', 'staff.enabled')->value('user_id'));
    }

    public function test_only_an_admin_can_disable_and_only_staff_accounts(): void
    {
        $client = User::create(['name' => 'C', 'email' => 'c@t.test', 'password' => 'password', 'role' => 'client']);
        $admin = $this->as('admin');
        $this->patchJson("/api/staff/{$client->id}", ['disabled' => true])->assertNotFound();
        $this->patchJson("/api/staff/{$admin->id}", ['disabled' => true])->assertNotFound();

        $mgr = $this->as('manager');
        $victim = User::create(['name' => 'S', 'email' => 's@t.test', 'password' => 'password', 'role' => 'stylist']);
        $this->patchJson("/api/staff/{$victim->id}", ['disabled' => true])->assertForbidden();
        $this->assertNull($victim->fresh()->disabled_at);
    }

    public function test_changing_your_own_password(): void
    {
        $u = User::create(['name' => 'Mia', 'email' => 'mia@t.test', 'password' => 'temp-pass-1', 'role' => 'manager']);
        $login = $this->postJson('/api/auth/login', ['email' => 'mia@t.test', 'password' => 'temp-pass-1'])->assertOk();
        $other = $u->createToken('phone');
        $headers = ['Authorization' => 'Bearer '.$login->json('token')];

        $this->withHeaders($headers)->postJson('/api/auth/change-password', ['current_password' => 'nope', 'password' => 'brand-new-pw', 'password_confirmation' => 'brand-new-pw'])->assertStatus(422);
        $this->withHeaders($headers)->postJson('/api/auth/change-password', ['current_password' => 'temp-pass-1', 'password' => 'short', 'password_confirmation' => 'short'])->assertStatus(422);
        $this->withHeaders($headers)->postJson('/api/auth/change-password', ['current_password' => 'temp-pass-1', 'password' => 'brand-new-pw', 'password_confirmation' => 'other-pw-123'])->assertStatus(422);

        $this->withHeaders($headers)->postJson('/api/auth/change-password', ['current_password' => 'temp-pass-1', 'password' => 'brand-new-pw', 'password_confirmation' => 'brand-new-pw'])->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $other->accessToken->id]);
        $this->assertSame(1, $u->tokens()->count()); // this device stays signed in
        $this->postJson('/api/auth/login', ['email' => 'mia@t.test', 'password' => 'temp-pass-1'])->assertUnauthorized();
        $this->postJson('/api/auth/login', ['email' => 'mia@t.test', 'password' => 'brand-new-pw'])->assertOk();
    }

    public function test_change_password_needs_a_login(): void
    {
        $this->postJson('/api/auth/change-password', [])->assertUnauthorized();
    }

    public function test_new_staff_must_replace_the_temporary_password_before_using_the_api(): void
    {
        $this->as('admin');
        $temp = $this->postJson('/api/staff', ['name' => 'Mia', 'email' => 'mia@t.test', 'role' => 'manager', 'branch_id' => 1])->assertCreated()->json('temp_password');
        $this->assertTrue((bool) User::where('email', 'mia@t.test')->value('must_change_password'));

        $this->app['auth']->forgetGuards();
        $login = $this->postJson('/api/auth/login', ['email' => 'mia@t.test', 'password' => $temp])->assertOk();
        $this->assertTrue($login->json('user.must_change_password'));
        $h = ['Authorization' => 'Bearer '.$login->json('token')];

        $this->withHeaders($h)->getJson('/api/auth/me')->assertOk();
        $this->withHeaders($h)->getJson('/api/clients')->assertForbidden()->assertJsonPath('code', 'password_change_required');

        $this->withHeaders($h)->postJson('/api/auth/change-password', ['current_password' => $temp, 'password' => 'my-own-pass-1', 'password_confirmation' => 'my-own-pass-1'])
            ->assertOk()->assertJsonPath('user.must_change_password', false);
        $this->withHeaders($h)->getJson('/api/clients')->assertOk();
    }

    public function test_existing_users_are_not_forced_to_change_their_password(): void
    {
        User::create(['name' => 'Old', 'email' => 'old@t.test', 'password' => 'password', 'role' => 'manager']);
        $token = $this->postJson('/api/auth/login', ['email' => 'old@t.test', 'password' => 'password'])->assertOk()->json('token');
        $this->withHeaders(['Authorization' => "Bearer $token"])->getJson('/api/clients')->assertOk();
    }
}
