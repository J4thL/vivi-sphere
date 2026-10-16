<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Review;
use App\Models\Service;
use App\Models\Stylist;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Constraints the database itself enforces, and the per-booking duration. */
class DatabaseIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private Stylist $stylist;

    private Service $service;

    private Client $client;

    private string $day;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stylist = Stylist::create(['branch_id' => 1, 'name' => 'Marisol', 'role' => 'Colorist', 'active' => true]);
        $this->service = Service::create(['name' => 'Cut', 'price' => 50, 'duration_minutes' => 60]);
        $this->client = Client::create(['name' => 'Cleo']);
        $this->day = now()->addDays(3)->format('Y-m-d');
    }

    private function appt(string $time, array $extra = []): Appointment
    {
        return Appointment::create($extra + ['branch_id' => 1, 
            'client_id' => $this->client->id, 'stylist_id' => $this->stylist->id, 'service_id' => $this->service->id,
            'scheduled_at' => "{$this->day} $time", 'status' => 'confirmed', 'price' => 50,
        ]);
    }

    private function manager(): void
    {
        Sanctum::actingAs(User::create(['name' => 'M', 'email' => 'm@t.test', 'password' => 'password', 'role' => 'manager', 'branch_id' => 1]));
    }

    // ---- database constraints ---------------------------------------------------------------

    public function test_a_client_with_appointments_cannot_be_deleted(): void
    {
        $this->appt('10:00:00');

        $this->expectException(QueryException::class);
        $this->client->delete();
    }

    public function test_a_stylist_or_service_with_appointments_cannot_be_deleted(): void
    {
        $this->appt('10:00:00');

        foreach ([$this->stylist, $this->service] as $record) {
            try {
                $record->delete();
                $this->fail('deleting '.class_basename($record).' should have been refused');
            } catch (QueryException) {
            }
        }
        $this->assertSame(1, Appointment::count(), 'booking history must survive');
    }

    public function test_an_appointment_with_a_review_cannot_be_deleted(): void
    {
        $appt = $this->appt('10:00:00', ['status' => 'completed']);
        Review::create(['appointment_id' => $appt->id, 'client_id' => $this->client->id, 'stylist_id' => $this->stylist->id, 'rating' => 5, 'status' => 'approved']);

        $this->expectException(QueryException::class);
        $appt->delete();
    }

    public function test_one_login_cannot_own_two_client_records(): void
    {
        $user = User::create(['name' => 'U', 'email' => 'u@t.test', 'password' => 'password', 'role' => 'client']);
        Client::create(['name' => 'First', 'user_id' => $user->id]);

        $this->expectException(QueryException::class);
        Client::create(['name' => 'Second', 'user_id' => $user->id]);
    }

    public function test_many_walk_in_clients_without_a_login_are_still_allowed(): void
    {
        Client::create(['name' => 'Walk-in A']);
        Client::create(['name' => 'Walk-in B']);

        $this->assertSame(3, Client::count()); // plus the one from setUp
    }

    public function test_a_stylist_login_must_point_at_a_real_stylist_and_not_be_shared(): void
    {
        User::create(['name' => 'S1', 'email' => 's1@t.test', 'password' => 'password', 'role' => 'stylist', 'stylist_id' => $this->stylist->id]);

        try {
            User::create(['name' => 'S2', 'email' => 's2@t.test', 'password' => 'password', 'role' => 'stylist', 'stylist_id' => $this->stylist->id]);
            $this->fail('two logins must not share one stylist');
        } catch (QueryException) {
        }

        $this->expectException(QueryException::class);
        User::create(['name' => 'S3', 'email' => 's3@t.test', 'password' => 'password', 'role' => 'stylist', 'stylist_id' => 99999]);
    }

    // ---- per-booking duration ------------------------------------------------------------------

    public function test_a_new_booking_stores_the_length_it_was_made_with(): void
    {
        $this->manager();

        $res = $this->postJson('/api/appointments', [
            'client_name' => 'Walk In', 'stylist_id' => $this->stylist->id,
            'service_id' => $this->service->id, 'scheduled_at' => "{$this->day} 10:00:00",
        ])->assertCreated()->assertJsonPath('duration_minutes', 60);

        $this->assertSame(60, Appointment::findOrFail($res->json('id'))->duration_minutes);
    }

    public function test_lengthening_a_service_does_not_stretch_existing_bookings(): void
    {
        $this->manager();
        $this->appt('10:00:00', ['duration_minutes' => 60]); // 10:00 - 11:00

        $this->service->update(['duration_minutes' => 180]); // the menu length changes afterwards

        // 11:00 is still free, because the booked hour ended at 11:00 (not at 13:00).
        $this->postJson('/api/appointments', [
            'client_name' => 'Next Client', 'stylist_id' => $this->stylist->id,
            'service_id' => Service::create(['name' => 'Trim', 'price' => 20, 'duration_minutes' => 30])->id,
            'scheduled_at' => "{$this->day} 11:00:00",
        ])->assertCreated();
    }

    public function test_older_bookings_without_a_stored_length_use_the_services(): void
    {
        $this->manager();
        $this->appt('10:00:00'); // duration_minutes left null, as for rows from before the column existed

        $this->postJson('/api/appointments', [
            'client_name' => 'Overlapper', 'stylist_id' => $this->stylist->id,
            'service_id' => $this->service->id, 'scheduled_at' => "{$this->day} 10:30:00",
        ])->assertStatus(422)->assertJsonValidationErrors('scheduled_at');
    }

    public function test_switching_service_changes_price_and_length_but_other_edits_do_not(): void
    {
        $this->manager();
        $appt = $this->appt('10:00:00', ['duration_minutes' => 60, 'price' => 35]);
        $colour = Service::create(['name' => 'Colour', 'price' => 120, 'duration_minutes' => 90]);

        $this->patchJson("/api/appointments/{$appt->id}", ['status' => 'in-progress'])->assertOk();
        $this->assertSame(60, $appt->fresh()->duration_minutes);
        $this->assertSame('35.00', (string) $appt->fresh()->price);

        $this->patchJson("/api/appointments/{$appt->id}", ['service_id' => $colour->id])->assertOk();
        $this->assertSame(90, $appt->fresh()->duration_minutes);
        $this->assertSame('120.00', (string) $appt->fresh()->price);
    }
}
