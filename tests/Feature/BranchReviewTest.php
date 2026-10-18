<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Review;
use App\Models\Service;
use App\Models\Stylist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Multi-branch step 6: reviews belong to their stylist's branch. */
class BranchReviewTest extends TestCase
{
    use RefreshDatabase;

    private Branch $b;

    private Review $atMain;

    private Review $atSecond;

    protected function setUp(): void
    {
        parent::setUp();
        $this->b = Branch::create(['name' => 'Second']);
        $service = Service::create(['name' => 'Cut', 'price' => 50, 'duration_minutes' => 30]);
        $this->atMain = $this->review(Stylist::create(['name' => 'Ana', 'role' => 'X', 'branch_id' => 1]), $service);
        $this->atSecond = $this->review(Stylist::create(['name' => 'Ben', 'role' => 'X', 'branch_id' => $this->b->id]), $service);
    }

    private function review(Stylist $s, Service $service, string $status = 'pending'): Review
    {
        $client = Client::create(['name' => 'C '.uniqid()]);
        $appt = Appointment::create(['client_id' => $client->id, 'stylist_id' => $s->id, 'service_id' => $service->id, 'branch_id' => $s->branch_id, 'scheduled_at' => now()->subDay(), 'status' => 'completed', 'price' => 50]);

        return Review::create(['appointment_id' => $appt->id, 'client_id' => $client->id, 'stylist_id' => $s->id, 'rating' => 5, 'status' => $status]);
    }

    private function as(string $role, ?int $branchId = null): User
    {
        $u = User::create(['name' => ucfirst($role), 'email' => $role.uniqid().'@t.test', 'password' => 'password', 'role' => $role, 'branch_id' => $branchId]);
        Sanctum::actingAs($u);

        return $u;
    }

    public function test_a_manager_sees_only_their_branchs_reviews_and_the_admin_sees_all(): void
    {
        $this->as('manager', $this->b->id);
        $this->getJson('/api/reviews')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $this->atSecond->id);

        $this->as('admin');
        $this->getJson('/api/reviews')->assertOk()->assertJsonCount(2);
        $this->getJson("/api/reviews?branch_id={$this->b->id}")->assertOk()->assertJsonCount(1);
    }

    public function test_a_manager_can_only_moderate_their_own_branchs_reviews(): void
    {
        $this->as('manager', $this->b->id);

        $this->patchJson("/api/reviews/{$this->atMain->id}", ['status' => 'approved'])->assertForbidden();
        $this->patchJson("/api/reviews/{$this->atSecond->id}", ['status' => 'approved'])->assertOk();

        $this->assertSame('pending', $this->atMain->fresh()->status);
        $this->assertSame('approved', $this->atSecond->fresh()->status);
    }

    public function test_the_admin_can_moderate_any_branch(): void
    {
        $this->as('admin');
        $this->patchJson("/api/reviews/{$this->atMain->id}", ['status' => 'rejected'])->assertOk();
        $this->assertSame('rejected', $this->atMain->fresh()->status);
    }

    public function test_public_reviews_say_which_branch_they_are_for(): void
    {
        $this->atSecond->update(['status' => 'approved']);

        $this->getJson('/api/reviews')->assertOk()->assertJsonCount(1)->assertJsonPath('0.stylist.branch_id', $this->b->id);
    }
}
