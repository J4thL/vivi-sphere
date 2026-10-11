<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\Stylist;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Appointment times are the salon's wall-clock time (Davao, Asia/Manila, UTC+8), stored
 * and exchanged as "YYYY-MM-DD HH:MM:SS" with no timezone. The app clock itself stays UTC,
 * so anything compared with "now" must first be converted to the salon's local time.
 */
class TimeHandlingTest extends TestCase
{
    use RefreshDatabase;

    private Stylist $stylist;

    private Service $service;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stylist = Stylist::create(['name' => 'Marisol', 'role' => 'Colorist', 'active' => true]);
        $this->service = Service::create(['name' => 'Cut', 'price' => 50, 'duration_minutes' => 60]);
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@t.test', 'password' => 'password', 'role' => 'admin']);
        Sanctum::actingAs($this->admin);
    }

    private function book(string $when, string $name = 'Walk In')
    {
        return $this->postJson('/api/appointments', [
            'client_name' => $name,
            'stylist_id' => $this->stylist->id,
            'service_id' => $this->service->id,
            'scheduled_at' => $when,
        ]);
    }

    public function test_the_salon_timezone_is_configured(): void
    {
        $this->assertSame('Asia/Manila', config('app.salon_timezone'));
        // The application clock itself stays UTC so stored timestamps (tokens, resets) are unaffected.
        $this->assertSame('UTC', config('app.timezone'));
    }

    public function test_api_sends_appointment_times_as_plain_local_date_and_time(): void
    {
        // The dashboard splits this on a space. An ISO string ("...T10:00:00.000000Z") made every
        // appointment match no day at all, so the schedule, today's revenue and the chart were empty.
        $client = Client::create(['name' => 'C']);
        Appointment::create(['client_id' => $client->id, 'stylist_id' => $this->stylist->id, 'service_id' => $this->service->id, 'scheduled_at' => '2026-10-12 10:00:00', 'status' => 'confirmed', 'price' => 50]);

        $this->getJson('/api/appointments')->assertOk()->assertJsonPath('0.scheduled_at', '2026-10-12 10:00:00');
    }

    public function test_a_created_or_updated_appointment_also_returns_the_plain_format(): void
    {
        $day = now()->addDays(3)->format('Y-m-d');

        $this->book("$day 10:00:00")->assertCreated()->assertJsonPath('scheduled_at', "$day 10:00:00");
    }

    public function test_bookings_earlier_today_in_salon_time_are_rejected(): void
    {
        // 07:00 UTC is 15:00 in Davao.
        $this->travelTo(Carbon::parse('2026-10-12 07:00:00', 'UTC'));

        $this->book('2026-10-12 10:00:00', 'Five hours ago')->assertStatus(422)->assertJsonValidationErrors('scheduled_at');
        $this->book('2026-10-12 14:00:00', 'One hour ago')->assertStatus(422)->assertJsonValidationErrors('scheduled_at');
    }

    public function test_bookings_later_today_in_salon_time_are_accepted(): void
    {
        $this->travelTo(Carbon::parse('2026-10-12 07:00:00', 'UTC')); // 15:00 in Davao

        $this->book('2026-10-12 16:00:00')->assertCreated();
    }

    public function test_a_walk_in_entered_just_after_arrival_is_accepted_within_the_grace_period(): void
    {
        $this->travelTo(Carbon::parse('2026-10-12 07:00:00', 'UTC')); // 15:00 in Davao

        $this->book('2026-10-12 14:50:00', 'Just arrived')->assertCreated();
    }

    public function test_a_booking_older_than_the_grace_period_is_rejected(): void
    {
        $this->travelTo(Carbon::parse('2026-10-12 07:00:00', 'UTC')); // 15:00 in Davao

        $this->book('2026-10-12 14:30:00', 'Too late')->assertStatus(422)->assertJsonValidationErrors('scheduled_at');
    }

    public function test_a_late_night_in_utc_is_still_the_next_morning_in_salon_time(): void
    {
        // 18:30 UTC on the 11th is 02:30 on the 12th in Davao. 09:00 on the 12th is in the future.
        $this->travelTo(Carbon::parse('2026-10-11 18:30:00', 'UTC'));

        $this->book('2026-10-12 09:00:00')->assertCreated();
    }

    public function test_rescheduling_into_the_past_in_salon_time_is_rejected(): void
    {
        $this->travelTo(Carbon::parse('2026-10-12 07:00:00', 'UTC')); // 15:00 in Davao
        $client = Client::create(['name' => 'C']);
        $appt = Appointment::create(['client_id' => $client->id, 'stylist_id' => $this->stylist->id, 'service_id' => $this->service->id, 'scheduled_at' => '2026-10-12 16:00:00', 'status' => 'confirmed', 'price' => 50]);

        $this->patchJson("/api/appointments/{$appt->id}", ['scheduled_at' => '2026-10-12 11:00:00'])->assertStatus(422);
        $this->patchJson("/api/appointments/{$appt->id}", ['scheduled_at' => '2026-10-12 17:00:00'])->assertOk();
    }
}
