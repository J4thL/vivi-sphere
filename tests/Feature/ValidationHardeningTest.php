<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Review;
use App\Models\SalonSetting;
use App\Models\Service;
use App\Models\Stylist;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Bad input must be answered with a clear 422/403, never saved or turned into a 500. */
class ValidationHardeningTest extends TestCase
{
    use RefreshDatabase;

    private Stylist $stylist;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stylist = Stylist::create(['name' => 'Marisol', 'role' => 'Colorist', 'active' => true]);
        $this->service = Service::create(['name' => 'Cut', 'price' => 50, 'duration_minutes' => 60]);
    }

    private function as(string $role, array $extra = []): User
    {
        $user = User::create(['name' => ucfirst($role), 'email' => $role.uniqid().'@t.test', 'password' => 'password', 'role' => $role] + $extra);
        Sanctum::actingAs($user);

        return $user;
    }

    // ---- appointment date filters ------------------------------------------------------

    public function test_garbage_date_filters_give_422_not_500(): void
    {
        $this->as('admin');

        foreach (['date', 'from', 'to'] as $key) {
            $this->getJson("/api/appointments?{$key}=not-a-date")->assertStatus(422)->assertJsonValidationErrors($key);
        }
        $this->getJson('/api/appointments?date=2026-10-12')->assertOk();
        $this->getJson('/api/appointments?from=2026-10-01&to=2026-10-31')->assertOk();
    }

    // ---- salon settings -----------------------------------------------------------------

    public function test_malformed_faqs_are_rejected_so_the_homepage_cannot_break(): void
    {
        $this->as('admin');

        $this->patchJson('/api/settings', ['faqs' => [['question' => 'Only a question']]])->assertStatus(422)->assertJsonValidationErrors('faqs.0.answer');
        $this->patchJson('/api/settings', ['faqs' => ['just a string']])->assertStatus(422);
        $this->assertSame([], SalonSetting::current()->faqs);

        $this->patchJson('/api/settings', ['faqs' => [['id' => 1, 'question' => 'Parking?', 'answer' => 'Yes.']]])->assertOk();
        $this->get('/')->assertOk()->assertSee('Parking?');
    }

    public function test_featured_services_must_be_a_list_of_names(): void
    {
        $this->as('admin');

        $this->patchJson('/api/settings', ['featured_services' => [['nested' => 'x']]])->assertStatus(422);
        $this->patchJson('/api/settings', ['featured_services' => ['Cut']])->assertOk();
    }

    public function test_closing_time_must_be_after_opening_time(): void
    {
        $this->as('admin');

        $this->patchJson('/api/settings', ['open_time' => '18:00', 'close_time' => '09:00'])->assertStatus(422)->assertJsonValidationErrors('close_time');
        $this->patchJson('/api/settings', ['open_time' => '10:00', 'close_time' => '10:00'])->assertStatus(422);
        $this->patchJson('/api/settings', ['open_time' => '09:00', 'close_time' => '18:00'])->assertOk();

        // Sending only one of the two is judged against the stored other one.
        $this->patchJson('/api/settings', ['open_time' => '19:00'])->assertStatus(422);
        $this->patchJson('/api/settings', ['close_time' => '08:00'])->assertStatus(422);
        $this->patchJson('/api/settings', ['close_time' => '20:00'])->assertOk();
    }

    public function test_settings_changes_are_audit_logged(): void
    {
        $admin = $this->as('admin');

        $this->patchJson('/api/settings', ['salon_name' => 'Vivi Davao'])->assertOk();

        $log = AuditLog::where('action', 'settings.updated')->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame(['salon_name'], $log->meta['fields']);
    }

    // ---- waitlist ------------------------------------------------------------------------

    public function test_waitlist_rejects_past_dates_in_salon_time(): void
    {
        $this->as('manager');
        $this->travelTo(Carbon::parse('2026-10-11 18:30:00', 'UTC')); // already the 12th in Davao
        $base = ['client_name' => 'Wanda', 'service_id' => $this->service->id];

        $this->postJson('/api/waitlist', $base + ['preferred_date' => '2026-10-11'])->assertStatus(422)->assertJsonValidationErrors('preferred_date');
        $this->postJson('/api/waitlist', $base + ['preferred_date' => '2026-10-12'])->assertCreated();
        $this->postJson('/api/waitlist', $base + ['preferred_date' => 'soon'])->assertStatus(422);
    }

    public function test_a_stylist_cannot_attach_a_waitlist_entry_to_an_arbitrary_client(): void
    {
        $this->as('stylist', ['stylist_id' => $this->stylist->id]);
        $someone = Client::create(['name' => 'Private Person']);
        $day = now()->addDays(2)->format('Y-m-d');

        $this->postJson('/api/waitlist', ['client_id' => $someone->id, 'service_id' => $this->service->id, 'preferred_date' => $day])->assertForbidden();
        $this->postJson('/api/waitlist', ['client_name' => 'Walk In', 'service_id' => $this->service->id, 'preferred_date' => $day])->assertCreated();
    }

    public function test_a_manager_can_attach_a_waitlist_entry_to_an_existing_client(): void
    {
        $this->as('manager');
        $someone = Client::create(['name' => 'Known Client']);

        $this->postJson('/api/waitlist', ['client_id' => $someone->id, 'service_id' => $this->service->id, 'preferred_date' => now()->addDays(2)->format('Y-m-d')])
            ->assertCreated()->assertJsonPath('client_name', 'Known Client');
    }

    // ---- reviews ---------------------------------------------------------------------------

    private function completedVisitFor(User $user): Appointment
    {
        $client = Client::create(['name' => $user->name, 'user_id' => $user->id]);

        return Appointment::create([
            'client_id' => $client->id, 'stylist_id' => $this->stylist->id, 'service_id' => $this->service->id,
            'scheduled_at' => now()->subDay()->format('Y-m-d').' 10:00:00', 'status' => 'completed', 'price' => 50,
        ]);
    }

    public function test_a_pending_review_can_be_corrected_but_a_moderated_one_cannot(): void
    {
        $user = $this->as('client');
        $appt = $this->completedVisitFor($user);

        $this->postJson('/api/reviews', ['appointment_id' => $appt->id, 'rating' => 3])->assertCreated();
        $this->postJson('/api/reviews', ['appointment_id' => $appt->id, 'rating' => 5, 'comment' => 'Better'])->assertCreated();
        $this->assertSame(1, Review::count());
        $this->assertSame(5, Review::first()->rating);

        foreach (['approved', 'rejected'] as $status) {
            Review::first()->update(['status' => $status, 'rating' => 5]);
            $this->postJson('/api/reviews', ['appointment_id' => $appt->id, 'rating' => 1])->assertStatus(422);
            $this->assertSame($status, Review::first()->status);
            $this->assertSame(5, Review::first()->rating);
        }
    }

    // ---- reassigning ---------------------------------------------------------------------

    public function test_appointments_cannot_be_reassigned_to_an_inactive_stylist(): void
    {
        $this->as('manager');
        $gone = Stylist::create(['name' => 'Left', 'role' => 'Barber', 'active' => false]);
        $client = Client::create(['name' => 'C']);
        $day = now()->addDays(2)->format('Y-m-d');
        $appt = Appointment::create(['client_id' => $client->id, 'stylist_id' => $this->stylist->id, 'service_id' => $this->service->id, 'scheduled_at' => "$day 10:00:00", 'status' => 'confirmed', 'price' => 50]);

        $this->patchJson("/api/appointments/{$appt->id}", ['stylist_id' => $gone->id])->assertStatus(422)->assertJsonValidationErrors('stylist_id');
        $this->assertSame($this->stylist->id, $appt->fresh()->stylist_id);
    }

    public function test_an_appointment_of_a_deactivated_stylist_can_still_be_completed(): void
    {
        $this->as('manager');
        $client = Client::create(['name' => 'C']);
        $day = now()->addDays(2)->format('Y-m-d');
        $appt = Appointment::create(['client_id' => $client->id, 'stylist_id' => $this->stylist->id, 'service_id' => $this->service->id, 'scheduled_at' => "$day 10:00:00", 'status' => 'confirmed', 'price' => 50]);
        $this->stylist->update(['active' => false]);

        // The dashboard sends the unchanged stylist_id along with a status change.
        $this->patchJson("/api/appointments/{$appt->id}", ['stylist_id' => $this->stylist->id, 'status' => 'completed'])->assertOk();
    }

    public function test_echoing_unchanged_fields_does_not_reprice_a_booking(): void
    {
        $this->as('manager');
        $client = Client::create(['name' => 'C']);
        $day = now()->addDays(2)->format('Y-m-d');
        $appt = Appointment::create(['client_id' => $client->id, 'stylist_id' => $this->stylist->id, 'service_id' => $this->service->id, 'scheduled_at' => "$day 10:00:00", 'status' => 'confirmed', 'price' => 35]);

        $this->patchJson("/api/appointments/{$appt->id}", ['service_id' => $this->service->id, 'scheduled_at' => "$day 10:00:00", 'status' => 'completed'])->assertOk();
        $this->assertSame('35.00', (string) $appt->fresh()->price, 'the price agreed at booking time must survive');
    }
}
