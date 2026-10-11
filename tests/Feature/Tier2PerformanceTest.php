<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\Stylist;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Tier2PerformanceTest extends TestCase
{
    use RefreshDatabase;

    private function appt(Client $c, Stylist $s, Service $svc, string $when, string $status = 'confirmed'): Appointment
    {
        return Appointment::create([
            'client_id' => $c->id, 'stylist_id' => $s->id, 'service_id' => $svc->id,
            'scheduled_at' => $when, 'status' => $status, 'price' => 50,
        ]);
    }

    private function world(): array
    {
        return [
            Client::create(['name' => 'C']),
            Stylist::create(['name' => 'S', 'role' => 'Stylist', 'active' => true]),
            Service::create(['name' => 'Cut', 'price' => 50, 'duration_minutes' => 60]),
        ];
    }

    public function test_appointments_table_has_the_lookup_indexes(): void
    {
        $this->assertTrue(Schema::hasIndex('appointments', ['stylist_id', 'scheduled_at']));
        $this->assertTrue(Schema::hasIndex('appointments', ['scheduled_at']));
    }

    public function test_overlap_check_only_reads_the_same_day_but_still_detects_conflicts(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 08:00:00', config('app.salon_timezone')));
        [$client, $stylist, $service] = $this->world();
        foreach (range(1, 30) as $i) { // a long history that must not be loaded for a new booking
            $this->appt($client, $stylist, $service, '2026-09-'.str_pad((string) (($i % 27) + 1), 2, '0', STR_PAD_LEFT).' 10:00:00', 'completed');
        }
        $this->appt($client, $stylist, $service, '2026-10-15 10:00:00');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        DB::enableQueryLog();
        $body = fn ($t) => ['client_name' => 'Walk In', 'stylist_id' => $stylist->id, 'service_id' => $service->id, 'scheduled_at' => $t];

        $this->postJson('/api/appointments', $body('2026-10-15 10:30:00'))->assertStatus(422)->assertJsonValidationErrors('scheduled_at');
        $this->postJson('/api/appointments', $body('2026-10-15 11:00:00'))->assertCreated(); // back to back is fine
        $this->postJson('/api/appointments', $body('2026-10-16 10:00:00'))->assertCreated(); // other day is fine

        $overlapQueries = collect(DB::getQueryLog())->pluck('query')->filter(fn ($q) => str_contains($q, 'from "appointments" where "stylist_id"'));
        $this->assertNotEmpty($overlapQueries);
        foreach ($overlapQueries as $q) {
            $this->assertStringContainsString('"scheduled_at" between', $q);
        }
    }

    public function test_appointment_list_supports_day_and_range_filters(): void
    {
        [$client, $stylist, $service] = $this->world();
        $this->appt($client, $stylist, $service, '2026-10-01 09:00:00');
        $this->appt($client, $stylist, $service, '2026-10-10 23:30:00'); // late in the day: must be included for that day
        $this->appt($client, $stylist, $service, '2026-10-10 09:00:00');
        $this->appt($client, $stylist, $service, '2026-10-20 09:00:00');
        Sanctum::actingAs(User::factory()->create(['role' => 'manager']));

        $this->getJson('/api/appointments')->assertOk()->assertJsonCount(4);
        $this->getJson('/api/appointments?date=2026-10-10')->assertOk()->assertJsonCount(2);
        $this->getJson('/api/appointments?from=2026-10-05')->assertOk()->assertJsonCount(3);
        $this->getJson('/api/appointments?from=2026-10-05&to=2026-10-10')->assertOk()->assertJsonCount(2);
        $this->getJson('/api/appointments?to=2026-10-01')->assertOk()->assertJsonCount(1);
    }

    public function test_mobile_menu_script_is_present_and_carries_the_csp_nonce(): void
    {
        $response = $this->get('/')->assertOk();
        preg_match("/'nonce-([^']+)'/", $response->headers->get('Content-Security-Policy'), $m);
        $html = $response->getContent();

        $this->assertStringContainsString("removeAttribute('open')", $html);
        $this->assertStringContainsString('<script nonce="'.$m[1].'">', $html);
    }
}
