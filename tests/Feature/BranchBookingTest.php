<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Service;
use App\Models\Stylist;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Multi-branch step 3: appointments and the waitlist belong to a branch. */
class BranchBookingTest extends TestCase
{
    use RefreshDatabase;

    private Branch $a;
    private Branch $b;
    private Stylist $ana;
    private Stylist $ben;
    private Service $service;
    private string $day;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Branch::findOrFail(1);
        $this->b = Branch::create(['name' => 'Second', 'open_time' => '10:00', 'close_time' => '14:00']);
        $this->ana = Stylist::create(['name' => 'Ana', 'role' => 'Colorist', 'active' => true, 'branch_id' => $this->a->id]);
        $this->ben = Stylist::create(['name' => 'Ben', 'role' => 'Barber', 'active' => true, 'branch_id' => $this->b->id]);
        $this->service = Service::create(['name' => 'Cut', 'price' => 50, 'duration_minutes' => 30]);
        $this->day = now()->addDays(3)->format('Y-m-d');
    }

    private function as(string $role, ?int $branchId = null, array $extra = []): User
    {
        $u = User::create(['name' => ucfirst($role), 'email' => $role.uniqid().'@t.test', 'password' => 'password', 'role' => $role, 'branch_id' => $branchId] + $extra);
        Sanctum::actingAs($u);

        return $u;
    }

    private function book(Stylist $s, string $time = '11:00:00', string $status = 'confirmed'): Appointment
    {
        $client = Client::create(['name' => 'C '.uniqid()]);

        return Appointment::create(['client_id' => $client->id, 'stylist_id' => $s->id, 'service_id' => $this->service->id, 'branch_id' => $s->branch_id, 'scheduled_at' => "{$this->day} $time", 'status' => $status, 'price' => 50]);
    }

    public function test_a_booking_takes_its_branch_from_the_stylist(): void
    {
        $this->as('client');

        $res = $this->postJson('/api/appointments', ['client_name' => 'Me', 'stylist_id' => $this->ben->id, 'service_id' => $this->service->id, 'scheduled_at' => "{$this->day} 11:00:00"])->assertCreated();

        $this->assertSame($this->b->id, Appointment::findOrFail($res->json('id'))->branch_id);
    }

    public function test_bookings_follow_the_branch_opening_hours(): void
    {
        $this->as('client');

        // Second branch opens at 10:00 and closes at 14:00; the main branch is open 09:00-19:00.
        $this->postJson('/api/appointments', ['client_name' => 'Me', 'stylist_id' => $this->ben->id, 'service_id' => $this->service->id, 'scheduled_at' => "{$this->day} 09:00:00"])->assertStatus(422)->assertJsonValidationErrors('scheduled_at');
        $this->postJson('/api/appointments', ['client_name' => 'Me', 'stylist_id' => $this->ben->id, 'service_id' => $this->service->id, 'scheduled_at' => "{$this->day} 14:00:00"])->assertStatus(422);
        $this->postJson('/api/appointments', ['client_name' => 'Me', 'stylist_id' => $this->ana->id, 'service_id' => $this->service->id, 'scheduled_at' => "{$this->day} 09:00:00"])->assertCreated();
    }

    public function test_a_switched_off_branch_takes_no_new_bookings(): void
    {
        $this->b->update(['active' => false]);
        $this->as('client');

        $this->postJson('/api/appointments', ['client_name' => 'Me', 'stylist_id' => $this->ben->id, 'service_id' => $this->service->id, 'scheduled_at' => "{$this->day} 11:00:00"])->assertStatus(422)->assertJsonValidationErrors('stylist_id');
    }

    public function test_a_manager_sees_only_their_branch_and_the_admin_sees_all(): void
    {
        $this->book($this->ana);
        $this->book($this->ben);

        $this->as('manager', $this->b->id);
        $this->getJson('/api/appointments')->assertOk()->assertJsonCount(1)->assertJsonPath('0.stylist_id', $this->ben->id);

        $this->as('admin');
        $this->getJson('/api/appointments')->assertOk()->assertJsonCount(2);
        $this->getJson("/api/appointments?branch_id={$this->a->id}")->assertOk()->assertJsonCount(1)->assertJsonPath('0.stylist_id', $this->ana->id);
    }

    public function test_a_manager_cannot_book_or_change_appointments_in_another_branch(): void
    {
        $theirs = $this->book($this->ana);
        $this->as('manager', $this->b->id);

        $this->postJson('/api/appointments', ['client_name' => 'Walk In', 'stylist_id' => $this->ana->id, 'service_id' => $this->service->id, 'scheduled_at' => "{$this->day} 12:00:00"])->assertForbidden();
        $this->patchJson("/api/appointments/{$theirs->id}", ['status' => 'cancelled'])->assertForbidden();
        $this->assertSame('confirmed', $theirs->fresh()->status);

        $this->postJson('/api/appointments', ['client_name' => 'Walk In', 'stylist_id' => $this->ben->id, 'service_id' => $this->service->id, 'scheduled_at' => "{$this->day} 12:00:00"])->assertCreated();
    }

    public function test_an_appointment_cannot_be_reassigned_to_a_stylist_in_another_branch(): void
    {
        $appt = $this->book($this->ana);
        $other = Stylist::create(['name' => 'Cleo', 'role' => 'X', 'active' => true, 'branch_id' => $this->a->id]);
        $this->as('admin');

        $this->patchJson("/api/appointments/{$appt->id}", ['stylist_id' => $this->ben->id])->assertStatus(422)->assertJsonValidationErrors('stylist_id');
        $this->patchJson("/api/appointments/{$appt->id}", ['stylist_id' => $other->id])->assertOk();
    }

    public function test_the_same_time_can_be_booked_with_stylists_in_different_branches(): void
    {
        $this->as('admin');
        $this->postJson('/api/appointments', ['client_name' => 'One', 'stylist_id' => $this->ana->id, 'service_id' => $this->service->id, 'scheduled_at' => "{$this->day} 11:00:00"])->assertCreated();
        $this->postJson('/api/appointments', ['client_name' => 'Two', 'stylist_id' => $this->ben->id, 'service_id' => $this->service->id, 'scheduled_at' => "{$this->day} 11:00:00"])->assertCreated();
    }

    public function test_waitlist_needs_a_branch_and_a_matching_stylist(): void
    {
        $this->as('client');
        $day = now()->addDays(2)->format('Y-m-d');
        $base = ['client_name' => 'Me', 'service_id' => $this->service->id, 'preferred_date' => $day];

        $this->postJson('/api/waitlist', $base)->assertStatus(422)->assertJsonValidationErrors('branch_id');
        $this->postJson('/api/waitlist', $base + ['branch_id' => $this->b->id, 'stylist_id' => $this->ana->id])->assertStatus(422)->assertJsonValidationErrors('stylist_id');
        $this->postJson('/api/waitlist', $base + ['branch_id' => $this->b->id, 'stylist_id' => $this->ben->id])->assertCreated()->assertJsonPath('branch_id', $this->b->id);
        // The branch can be taken from the chosen stylist.
        $this->postJson('/api/waitlist', $base + ['stylist_id' => $this->ana->id])->assertCreated()->assertJsonPath('branch_id', $this->a->id);
    }

    public function test_waitlist_is_scoped_to_the_managers_branch(): void
    {
        $mk = fn ($branch) => WaitlistEntry::create(['client_name' => 'W', 'service_id' => $this->service->id, 'branch_id' => $branch->id, 'preferred_date' => $this->day, 'status' => 'waiting']);
        $mine = $mk($this->b);
        $theirs = $mk($this->a);

        $this->as('manager', $this->b->id);
        $this->getJson('/api/waitlist')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $mine->id);
        $this->deleteJson("/api/waitlist/{$theirs->id}")->assertForbidden();
        $this->deleteJson("/api/waitlist/{$mine->id}")->assertOk();

        // A manager adding to the waitlist always lands in their own branch.
        $res = $this->postJson('/api/waitlist', ['client_name' => 'X', 'service_id' => $this->service->id, 'preferred_date' => $this->day, 'branch_id' => $this->a->id])->assertCreated();
        $this->assertSame($this->b->id, $res->json('branch_id'));

        $this->as('admin');
        $this->getJson('/api/waitlist')->assertOk()->assertJsonCount(2);
    }
}
