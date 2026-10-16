<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Review;
use App\Models\Service;
use App\Models\Stylist;
use App\Models\User;
use App\Models\WaitlistEntry;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** List endpoints stay bounded as the salon grows, without hiding anything the caller asks for. */
class ListBoundsTest extends TestCase
{
    use RefreshDatabase;

    private Stylist $stylist;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stylist = Stylist::create(['branch_id' => 1, 'name' => 'Marisol', 'role' => 'Colorist', 'active' => true]);
        $this->service = Service::create(['name' => 'Cut', 'price' => 50, 'duration_minutes' => 60]);
    }

    private function as(string $role): User
    {
        $user = User::create(['name' => ucfirst($role), 'email' => $role.uniqid().'@t.test', 'password' => 'password', 'role' => $role, 'branch_id' => in_array($role, ['manager', 'stylist']) ? 1 : null]);
        Sanctum::actingAs($user);

        return $user;
    }

    private function appt(Client $client, string $when): Appointment
    {
        return Appointment::create(['branch_id' => 1, 'client_id' => $client->id, 'stylist_id' => $this->stylist->id, 'service_id' => $this->service->id, 'scheduled_at' => $when, 'status' => 'completed', 'price' => 50]);
    }

    public function test_staff_appointment_lists_start_a_limited_time_back_but_always_include_the_future(): void
    {
        $this->as('manager');
        config(['salon.appointments_window_days' => 30]);
        $this->travelTo(Carbon::parse('2026-10-12 03:00:00', 'UTC')); // 11:00 in Davao
        $c = Client::create(['name' => 'C']);
        $old = $this->appt($c, '2026-05-01 10:00:00');
        $recent = $this->appt($c, '2026-10-01 10:00:00');
        $future = $this->appt($c, '2026-12-01 10:00:00');

        $ids = collect($this->getJson('/api/appointments')->assertOk()->json())->pluck('id');

        $this->assertFalse($ids->contains($old->id));
        $this->assertTrue($ids->contains($recent->id));
        $this->assertTrue($ids->contains($future->id));
    }

    public function test_an_explicit_range_reaches_further_back(): void
    {
        $this->as('manager');
        config(['salon.appointments_window_days' => 30]);
        $this->travelTo(Carbon::parse('2026-10-12 03:00:00', 'UTC'));
        $old = $this->appt(Client::create(['name' => 'C']), '2026-05-01 10:00:00');

        $this->assertCount(1, $this->getJson('/api/appointments?from=2026-04-01&to=2026-06-01')->assertOk()->json());
        $this->assertSame($old->id, $this->getJson('/api/appointments?date=2026-05-01')->json('0.id'));
    }

    public function test_a_client_always_sees_all_of_their_own_history(): void
    {
        $user = $this->as('client');
        config(['salon.appointments_window_days' => 30]);
        $this->travelTo(Carbon::parse('2026-10-12 03:00:00', 'UTC'));
        $mine = Client::create(['name' => 'Me', 'user_id' => $user->id]);
        $this->appt($mine, '2025-01-01 10:00:00');

        $this->assertCount(1, $this->getJson('/api/appointments')->assertOk()->json());
    }

    public function test_the_client_list_is_capped_to_the_top_spenders(): void
    {
        $this->as('manager');
        config(['salon.clients_max' => 2]);
        Client::create(['name' => 'Low', 'spend' => 10]);
        Client::create(['name' => 'High', 'spend' => 900]);
        Client::create(['name' => 'Mid', 'spend' => 300]);

        $this->assertSame(['High', 'Mid'], collect($this->getJson('/api/clients')->assertOk()->json())->pluck('name')->all());
    }

    public function test_reviews_and_the_waitlist_are_capped(): void
    {
        config(['salon.reviews_max' => 2, 'salon.public_reviews_max' => 1, 'salon.waitlist_max' => 2]);
        $c = Client::create(['name' => 'Rae Reviewer']);
        foreach (range(1, 3) as $i) {
            $appt = $this->appt($c, "2026-09-0$i 10:00:00");
            Review::create(['appointment_id' => $appt->id, 'client_id' => $c->id, 'stylist_id' => $this->stylist->id, 'rating' => 5, 'status' => 'approved']);
            WaitlistEntry::create(['branch_id' => 1, 'client_name' => "W$i", 'service_id' => $this->service->id, 'preferred_date' => '2026-12-0'.$i, 'status' => 'waiting']);
        }

        $this->getJson('/api/reviews')->assertOk()->assertJsonCount(1); // public, approved
        $this->as('manager');
        $this->getJson('/api/reviews')->assertOk()->assertJsonCount(2);
        $this->getJson('/api/waitlist')->assertOk()->assertJsonCount(2);
    }
}
