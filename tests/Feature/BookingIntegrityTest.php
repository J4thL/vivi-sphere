<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Service;
use App\Models\Stylist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Status rules, visit counting and all-or-nothing writes for appointments.
 *
 *   confirmed -> in-progress | completed | cancelled
 *   in-progress -> confirmed | completed | cancelled
 *   completed -> (final)
 *   cancelled -> confirmed (managers only, and the slot must still be free)
 */
class BookingIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private Stylist $stylist;

    private Service $service;

    private Client $client;

    private string $day;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stylist = Stylist::create(['name' => 'Marisol', 'role' => 'Colorist', 'active' => true]);
        $this->service = Service::create(['name' => 'Cut', 'price' => 50, 'duration_minutes' => 60]);
        $this->client = Client::create(['name' => 'Cleo Client']);
        $this->day = now()->addDays(3)->format('Y-m-d');
    }

    private function as(string $role, array $extra = []): User
    {
        $user = User::create(['name' => ucfirst($role), 'email' => $role.uniqid().'@t.test', 'password' => 'password', 'role' => $role] + $extra);
        Sanctum::actingAs($user);

        return $user;
    }

    private function appt(string $time, string $status = 'confirmed', ?Stylist $stylist = null): Appointment
    {
        return Appointment::create([
            'client_id' => $this->client->id, 'stylist_id' => ($stylist ?? $this->stylist)->id, 'service_id' => $this->service->id,
            'scheduled_at' => "{$this->day} {$time}", 'status' => $status, 'price' => 50,
        ]);
    }

    private function setStatus(Appointment $a, string $status)
    {
        return $this->patchJson("/api/appointments/{$a->id}", ['status' => $status]);
    }

    // ---- visit counting -----------------------------------------------------------

    public function test_the_normal_flow_counts_one_visit(): void
    {
        $this->as('manager');
        $a = $this->appt('10:00:00');

        $this->setStatus($a, 'in-progress')->assertOk();
        $this->setStatus($a, 'completed')->assertOk();
        $this->setStatus($a, 'completed')->assertOk(); // repeating the same status is harmless

        $c = $this->client->fresh();
        $this->assertSame(1, (int) $c->visits);
        $this->assertSame('50.00', (string) $c->spend);
    }

    public function test_a_completed_appointment_cannot_be_reopened_so_the_visit_cannot_be_counted_twice(): void
    {
        $this->as('manager');
        $a = $this->appt('10:00:00');
        $this->setStatus($a, 'completed')->assertOk();

        foreach (['confirmed', 'in-progress', 'cancelled'] as $next) {
            $this->setStatus($a, $next)->assertStatus(422)->assertJsonValidationErrors('status');
        }

        $this->assertSame('completed', $a->fresh()->status);
        $this->assertSame(1, (int) $this->client->fresh()->visits);
    }

    public function test_a_cancelled_appointment_cannot_be_jumped_straight_to_completed(): void
    {
        $this->as('manager');
        $a = $this->appt('10:00:00', 'cancelled');

        $this->setStatus($a, 'completed')->assertStatus(422)->assertJsonValidationErrors('status');
        $this->setStatus($a, 'in-progress')->assertStatus(422);

        $this->assertSame(0, (int) $this->client->fresh()->visits);
    }

    public function test_a_completed_appointment_cannot_be_moved_or_repriced(): void
    {
        $this->as('manager');
        $a = $this->appt('10:00:00', 'completed');
        $dearer = Service::create(['name' => 'Colour', 'price' => 200, 'duration_minutes' => 30]);

        $this->patchJson("/api/appointments/{$a->id}", ['scheduled_at' => "{$this->day} 15:00:00"])->assertStatus(422);
        $this->patchJson("/api/appointments/{$a->id}", ['service_id' => $dearer->id])->assertStatus(422);

        $this->assertSame('50.00', (string) $a->fresh()->price);
    }

    // ---- reopening a cancelled appointment ---------------------------------------------

    public function test_a_manager_can_reopen_a_cancelled_appointment_when_the_slot_is_free(): void
    {
        $this->as('manager');
        $a = $this->appt('10:00:00', 'cancelled');

        $this->setStatus($a, 'confirmed')->assertOk();
        $this->assertSame('confirmed', $a->fresh()->status);
    }

    public function test_reopening_is_refused_if_someone_else_took_the_slot(): void
    {
        $this->as('manager');
        $cancelled = $this->appt('10:00:00', 'cancelled');
        $this->appt('10:30:00'); // overlaps 10:00-11:00 for the same stylist

        $this->setStatus($cancelled, 'confirmed')->assertStatus(422)->assertJsonValidationErrors('scheduled_at');
        $this->assertSame('cancelled', $cancelled->fresh()->status);
    }

    public function test_a_stylist_cannot_reopen_a_cancelled_appointment(): void
    {
        $this->as('stylist', ['stylist_id' => $this->stylist->id]);
        $a = $this->appt('10:00:00', 'cancelled');

        $this->setStatus($a, 'confirmed')->assertForbidden();
    }

    // ---- all-or-nothing writes -----------------------------------------------------------

    public function test_a_failure_while_booking_leaves_nothing_behind(): void
    {
        $this->as('manager');
        AuditLog::creating(function () {
            throw new \RuntimeException('audit log unavailable');
        });

        $this->withoutExceptionHandling();
        try {
            $this->postJson('/api/appointments', [
                'client_name' => 'Brand New Person', 'stylist_id' => $this->stylist->id,
                'service_id' => $this->service->id, 'scheduled_at' => "{$this->day} 11:00:00",
            ]);
            $this->fail('the booking should have failed');
        } catch (\RuntimeException $e) {
            $this->assertSame('audit log unavailable', $e->getMessage());
        }

        $this->assertSame(0, Appointment::count(), 'the appointment must not survive a failed booking');
        $this->assertSame(0, Client::where('name', 'Brand New Person')->count(), 'nor the client record created for it');
    }

    public function test_a_failure_while_completing_leaves_status_and_counters_untouched(): void
    {
        $this->as('manager');
        $a = $this->appt('10:00:00', 'in-progress');
        AuditLog::creating(function () {
            throw new \RuntimeException('audit log unavailable');
        });

        $this->withoutExceptionHandling();
        try {
            $this->setStatus($a, 'completed');
            $this->fail('the update should have failed');
        } catch (\RuntimeException) {
        }

        $this->assertSame('in-progress', $a->fresh()->status);
        $this->assertSame(0, (int) $this->client->fresh()->visits);
        $this->assertSame('0.00', (string) $this->client->fresh()->spend);
    }
}
