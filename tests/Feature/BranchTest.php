<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Service;
use App\Models\Stylist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BranchTest extends TestCase
{
    use RefreshDatabase;

    private function as(string $role, ?int $branchId = null): User
    {
        $u = User::create(['name' => ucfirst($role), 'email' => $role.uniqid().'@t.test', 'password' => 'password', 'role' => $role, 'branch_id' => $branchId]);
        Sanctum::actingAs($u);

        return $u;
    }

    public function test_the_migration_leaves_one_default_branch(): void
    {
        $this->assertSame(1, Branch::count());
        $this->assertSame('Main Branch', Branch::first()->name);
    }

    public function test_anyone_can_list_active_branches_but_only_admin_sees_inactive(): void
    {
        $b = Branch::create(['name' => 'Closed One', 'active' => false]);

        $this->getJson('/api/branches')->assertOk()->assertJsonCount(1);

        $this->as('admin');
        $this->getJson('/api/branches')->assertOk()->assertJsonCount(1);
        $this->getJson('/api/branches?include_inactive=1')->assertOk()->assertJsonCount(2);
    }

    public function test_admin_adds_and_edits_branches_with_validation(): void
    {
        $this->as('admin');

        $id = $this->postJson('/api/branches', ['name' => 'Makati', 'address' => '1 Ayala Ave', 'open_time' => '10:00', 'close_time' => '20:00'])
            ->assertCreated()->json('id');
        $this->postJson('/api/branches', ['name' => 'Makati'])->assertStatus(422);
        $this->postJson('/api/branches', ['name' => 'Bad Hours', 'open_time' => '18:00', 'close_time' => '09:00'])->assertStatus(422);

        $this->patchJson("/api/branches/$id", ['phone' => '555-0100', 'active' => false])->assertOk()->assertJsonPath('active', false);
        $this->assertDatabaseHas('audit_logs', ['action' => 'branch.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'branch.updated']);
    }

    public function test_managers_stylists_and_clients_cannot_change_branches(): void
    {
        foreach (['manager', 'stylist', 'client'] as $role) {
            $this->as($role, 1);
            $this->postJson('/api/branches', ['name' => "X $role"])->assertForbidden();
            $this->patchJson('/api/branches/1', ['name' => 'Hacked'])->assertForbidden();
        }
        $this->assertSame('Main Branch', Branch::find(1)->name);
    }

    public function test_staff_need_an_active_branch(): void
    {
        $this->as('admin');
        $inactive = Branch::create(['name' => 'Off', 'active' => false]);

        $this->postJson('/api/staff', ['name' => 'A', 'email' => 'a@t.test', 'role' => 'manager'])->assertStatus(422)->assertJsonValidationErrors('branch_id');
        $this->postJson('/api/staff', ['name' => 'A', 'email' => 'a@t.test', 'role' => 'manager', 'branch_id' => $inactive->id])->assertStatus(422);
        $this->postJson('/api/staff', ['name' => 'A', 'email' => 'a@t.test', 'role' => 'manager', 'branch_id' => 999])->assertStatus(422);
    }

    public function test_a_stylist_staff_account_puts_login_and_profile_in_the_chosen_branch(): void
    {
        $this->as('admin');
        $second = Branch::create(['name' => 'Second']);

        $this->postJson('/api/staff', ['name' => 'Ana', 'email' => 'ana@t.test', 'role' => 'stylist', 'title' => 'Colorist', 'branch_id' => $second->id])->assertCreated();

        $login = User::where('email', 'ana@t.test')->firstOrFail();
        $this->assertSame($second->id, $login->branch_id);
        $this->assertSame($second->id, Stylist::findOrFail($login->stylist_id)->branch_id);
    }

    public function test_a_manager_adds_stylists_only_to_their_own_branch(): void
    {
        $second = Branch::create(['name' => 'Second']);
        $this->as('manager', $second->id);

        // Even if they ask for another branch, it goes into their own.
        $id = $this->postJson('/api/stylists', ['name' => 'Ben', 'role' => 'Barber', 'branch_id' => 1])->assertCreated()->json('id');
        $this->assertSame($second->id, Stylist::findOrFail($id)->branch_id);
    }

    public function test_admin_must_choose_a_branch_when_adding_a_stylist(): void
    {
        $this->as('admin');
        $this->postJson('/api/stylists', ['name' => 'Ben', 'role' => 'Barber'])->assertStatus(422);
        $this->postJson('/api/stylists', ['name' => 'Ben', 'role' => 'Barber', 'branch_id' => 1])->assertCreated();
    }

    public function test_a_manager_cannot_edit_or_move_stylists_from_another_branch(): void
    {
        $second = Branch::create(['name' => 'Second']);
        $mine = Stylist::create(['name' => 'Mine', 'role' => 'X', 'branch_id' => $second->id]);
        $theirs = Stylist::create(['name' => 'Theirs', 'role' => 'X', 'branch_id' => 1]);
        $this->as('manager', $second->id);

        $this->patchJson("/api/stylists/{$theirs->id}", ['active' => false])->assertForbidden();
        $this->patchJson("/api/stylists/{$mine->id}", ['active' => false])->assertOk();
        $this->patchJson("/api/stylists/{$mine->id}", ['branch_id' => 1])->assertForbidden();
    }

    public function test_admin_can_move_a_stylist_unless_they_already_have_appointments(): void
    {
        $second = Branch::create(['name' => 'Second']);
        $free = Stylist::create(['name' => 'Free', 'role' => 'X', 'branch_id' => 1]);
        $busy = Stylist::create(['name' => 'Busy', 'role' => 'X', 'branch_id' => 1]);
        $login = User::create(['name' => 'Free', 'email' => 'free@t.test', 'password' => 'password', 'role' => 'stylist', 'stylist_id' => $free->id, 'branch_id' => 1]);
        $service = Service::create(['name' => 'Cut', 'price' => 50, 'duration_minutes' => 30]);
        $client = Client::create(['name' => 'C']);
        Appointment::create(['client_id' => $client->id, 'stylist_id' => $busy->id, 'service_id' => $service->id, 'scheduled_at' => now()->addDay(), 'price' => 50]);

        $this->as('admin');
        $this->patchJson("/api/stylists/{$free->id}", ['branch_id' => $second->id])->assertOk();
        $this->assertSame($second->id, $login->fresh()->branch_id);
        $this->patchJson("/api/stylists/{$busy->id}", ['branch_id' => $second->id])->assertStatus(422);
        $this->assertSame(1, $busy->fresh()->branch_id);
    }

    public function test_stylist_list_can_be_filtered_by_branch(): void
    {
        $second = Branch::create(['name' => 'Second']);
        Stylist::create(['name' => 'A', 'role' => 'X', 'branch_id' => 1]);
        Stylist::create(['name' => 'B', 'role' => 'X', 'branch_id' => $second->id]);

        $this->getJson('/api/stylists')->assertOk()->assertJsonCount(2);
        $this->getJson("/api/stylists?branch_id={$second->id}")->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'B');
    }
}
