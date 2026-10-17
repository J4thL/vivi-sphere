<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BranchInventoryTest extends TestCase
{
    use RefreshDatabase;

    private function as(string $role, ?int $branchId = null): User
    {
        $u = User::create(['name' => ucfirst($role), 'email' => $role.uniqid().'@t.test', 'password' => 'password', 'role' => $role, 'branch_id' => $branchId]);
        Sanctum::actingAs($u);

        return $u;
    }

    private function item(string $name, int $branchId): InventoryItem
    {
        return InventoryItem::create(['name' => $name, 'category' => 'Care', 'qty' => 5, 'reorder_level' => 2, 'unit_cost' => 3, 'branch_id' => $branchId]);
    }

    public function test_a_manager_sees_only_their_branch_stock_and_the_admin_sees_all(): void
    {
        $second = Branch::create(['name' => 'Second']);
        $this->item('Main shampoo', 1);
        $this->item('Second shampoo', $second->id);

        $this->as('manager', $second->id);
        $this->getJson('/api/inventory')->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'Second shampoo');

        $this->as('admin');
        $this->getJson('/api/inventory')->assertOk()->assertJsonCount(2);
        $this->getJson("/api/inventory?branch_id={$second->id}")->assertOk()->assertJsonCount(1);
    }

    public function test_a_manager_adds_stock_to_their_own_branch_only(): void
    {
        $second = Branch::create(['name' => 'Second']);
        $this->as('manager', $second->id);

        $id = $this->postJson('/api/inventory', ['name' => 'Gel', 'qty' => 4, 'reorder_level' => 1, 'unit_cost' => 2, 'branch_id' => 1])->assertCreated()->json('id');
        $this->assertSame($second->id, InventoryItem::findOrFail($id)->branch_id);
    }

    public function test_the_admin_must_choose_a_branch_for_new_stock(): void
    {
        $this->as('admin');
        $this->postJson('/api/inventory', ['name' => 'Gel', 'qty' => 4, 'reorder_level' => 1, 'unit_cost' => 2])->assertStatus(422);
        $this->postJson('/api/inventory', ['name' => 'Gel', 'qty' => 4, 'reorder_level' => 1, 'unit_cost' => 2, 'branch_id' => 1])->assertCreated();
    }

    public function test_a_manager_cannot_change_or_move_another_branchs_stock(): void
    {
        $second = Branch::create(['name' => 'Second']);
        $theirs = $this->item('Theirs', 1);
        $mine = $this->item('Mine', $second->id);
        $this->as('manager', $second->id);

        $this->patchJson("/api/inventory/{$theirs->id}", ['qty' => 99])->assertForbidden();
        $this->patchJson("/api/inventory/{$mine->id}", ['qty' => 9])->assertOk();
        $this->patchJson("/api/inventory/{$mine->id}", ['branch_id' => 1])->assertForbidden();
        $this->assertSame(5, $theirs->fresh()->qty);
        $this->assertSame($second->id, $mine->fresh()->branch_id);
    }

    public function test_the_admin_can_move_stock_to_another_active_branch(): void
    {
        $second = Branch::create(['name' => 'Second']);
        $off = Branch::create(['name' => 'Off', 'active' => false]);
        $item = $this->item('Gel', 1);
        $this->as('admin');

        $this->patchJson("/api/inventory/{$item->id}", ['branch_id' => $off->id])->assertStatus(422);
        $this->patchJson("/api/inventory/{$item->id}", ['branch_id' => $second->id])->assertOk();
        $this->assertSame($second->id, $item->fresh()->branch_id);
    }
}
