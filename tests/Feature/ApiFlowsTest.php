<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Review;
use App\Models\Service;
use App\Models\Stylist;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiFlowsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, array $extra = []): User
    {
        return User::factory()->create(['role' => $role] + $extra);
    }

    private function service(array $o = []): Service
    {
        return Service::create($o + ['name' => 'Cut '.uniqid(), 'price' => 50, 'duration_minutes' => 45]);
    }

    private function stylist(bool $active = true): Stylist
    {
        return Stylist::create(['name' => 'S '.uniqid(), 'role' => 'Stylist', 'active' => $active]);
    }

    private function nextWeekdayAt(string $time): string
    {
        return now()->addDays(3)->format('Y-m-d').' '.$time;
    }

    // ---- Public pages -----------------------------------------------------

    public function test_public_pages_render(): void
    {
        $stylist = $this->stylist();
        foreach (['/', "/stylists/{$stylist->id}", '/terms', '/privacy', '/app/login', '/app/register'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/stylists/99999')->assertNotFound();
    }

    public function test_spa_shell_is_a_single_html_document(): void
    {
        $html = $this->get('/app/login')->getContent();
        $this->assertSame(1, substr_count($html, '<html'));
    }

    // ---- Auth emails (routes referenced by Laravel's built-in notifications) --

    public function test_registration_succeeds_and_renders_verification_email(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'New Client', 'email' => 'new@example.test', 'password' => 'password123',
        ])->assertCreated()->assertJsonStructure(['user', 'token']);

        $this->assertDatabaseHas('clients', ['name' => 'New Client']);
    }

    public function test_forgot_password_for_existing_user_does_not_error(): void
    {
        $this->user('client', ['email' => 'a@example.test']);
        $this->postJson('/api/auth/forgot-password', ['email' => 'a@example.test'])->assertOk();
        $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.test'])->assertOk();
    }

    public function test_password_reset_end_to_end(): void
    {
        $user = $this->user('client', ['email' => 'a@example.test']);
        $token = Password::createToken($user);

        $this->get("/app/reset-password/{$token}?email=a@example.test")->assertOk();

        $this->postJson('/api/auth/reset-password', [
            'token' => $token, 'email' => 'a@example.test',
            'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
        ])->assertOk();

        $this->postJson('/api/auth/login', ['email' => 'a@example.test', 'password' => 'brand-new-pass'])->assertOk();
    }

    public function test_email_verification_link(): void
    {
        $user = $this->user('client', ['email_verified_at' => null]);
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($url)->assertRedirect('/app/login');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        $bad = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => 'wrong']);
        $this->get($bad)->assertForbidden();
        $this->get("/email/verify/{$user->id}/".sha1($user->email))->assertForbidden(); // unsigned
    }

    // ---- Appointments -------------------------------------------------------

    public function test_cannot_book_an_inactive_stylist(): void
    {
        Sanctum::actingAs($this->user('client'));
        $this->postJson('/api/appointments', [
            'stylist_id' => $this->stylist(false)->id,
            'service_id' => $this->service()->id,
            'scheduled_at' => $this->nextWeekdayAt('10:00'),
        ])->assertStatus(422)->assertJsonValidationErrors('stylist_id');
    }

    public function test_overlapping_and_out_of_hours_bookings_are_rejected(): void
    {
        Sanctum::actingAs($this->user('admin'));
        $stylist = $this->stylist();
        $service = $this->service(['duration_minutes' => 60]);
        $body = fn ($t) => ['client_name' => 'Walk In', 'stylist_id' => $stylist->id, 'service_id' => $service->id, 'scheduled_at' => $this->nextWeekdayAt($t)];

        $this->postJson('/api/appointments', $body('10:00'))->assertCreated();
        $this->postJson('/api/appointments', $body('10:30'))->assertStatus(422)->assertJsonValidationErrors('scheduled_at');
        $this->postJson('/api/appointments', $body('03:00'))->assertStatus(422)->assertJsonValidationErrors('scheduled_at');
        $this->postJson('/api/appointments', $body('11:00'))->assertCreated();
    }

    public function test_completing_an_appointment_twice_counts_the_visit_once(): void
    {
        $admin = $this->user('admin');
        Sanctum::actingAs($admin);
        $client = Client::create(['name' => 'Repeat']);
        $appt = Appointment::create([
            'client_id' => $client->id, 'stylist_id' => $this->stylist()->id, 'service_id' => $this->service()->id,
            'scheduled_at' => $this->nextWeekdayAt('10:00'), 'status' => 'confirmed', 'price' => 50,
        ]);

        $this->patchJson("/api/appointments/{$appt->id}", ['status' => 'completed'])->assertOk();
        $this->patchJson("/api/appointments/{$appt->id}", ['status' => 'completed'])->assertOk();

        $this->assertSame(1, $client->fresh()->visits);
        $this->assertEquals(50, $client->fresh()->spend);
    }

    public function test_client_can_only_see_and_cancel_their_own_appointments(): void
    {
        $mine = $this->user('client');
        $other = $this->user('client');
        $myClient = Client::create(['user_id' => $mine->id, 'name' => $mine->name]);
        $otherClient = Client::create(['user_id' => $other->id, 'name' => $other->name]);
        $mk = fn ($c) => Appointment::create([
            'client_id' => $c->id, 'stylist_id' => $this->stylist()->id, 'service_id' => $this->service()->id,
            'scheduled_at' => $this->nextWeekdayAt('10:00'), 'status' => 'confirmed', 'price' => 50,
        ]);
        $a1 = $mk($myClient);
        $a2 = $mk($otherClient);

        Sanctum::actingAs($mine);
        $this->getJson('/api/appointments')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $a1->id);
        $this->patchJson("/api/appointments/{$a2->id}", ['status' => 'cancelled'])->assertForbidden();
        $this->patchJson("/api/appointments/{$a1->id}", ['status' => 'completed'])->assertForbidden();
        $this->patchJson("/api/appointments/{$a1->id}", ['status' => 'cancelled'])->assertOk();
    }

    // ---- Role gating ----------------------------------------------------------

    public function test_clients_endpoint_is_role_scoped(): void
    {
        $client = $this->user('client');
        Client::create(['user_id' => $client->id, 'name' => $client->name]);
        Client::create(['name' => 'Someone Else']);

        Sanctum::actingAs($client);
        $this->getJson('/api/clients')->assertOk()->assertJsonCount(1);

        Sanctum::actingAs($this->user('stylist'));
        $this->getJson('/api/clients')->assertForbidden();

        Sanctum::actingAs($this->user('manager'));
        $this->getJson('/api/clients')->assertOk()->assertJsonCount(2);
    }

    public function test_admin_only_and_manager_only_endpoints_reject_other_roles(): void
    {
        Sanctum::actingAs($this->user('client'));
        $this->postJson('/api/services', ['name' => 'X', 'price' => 1, 'duration_minutes' => 30])->assertForbidden();
        $this->getJson('/api/inventory')->assertForbidden();
        $this->getJson('/api/audit-log')->assertForbidden();
        $this->patchJson('/api/settings', ['salon_name' => 'Hacked'])->assertForbidden();

        Sanctum::actingAs($this->user('manager'));
        $this->getJson('/api/inventory')->assertOk();
        $this->getJson('/api/audit-log')->assertForbidden();

    }

    public function test_unauthenticated_requests_to_private_endpoints_get_401(): void
    {
        foreach (['/api/appointments', '/api/clients', '/api/inventory', '/api/audit-log', '/api/waitlist', '/api/auth/me'] as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
    }

    // ---- Reviews / waitlist -----------------------------------------------------

    public function test_review_visibility_depends_on_role(): void
    {
        $client = Client::create(['name' => 'Rev']);
        $stylist = $this->stylist();
        $appt = fn () => Appointment::create([
            'client_id' => $client->id, 'stylist_id' => $stylist->id, 'service_id' => $this->service()->id,
            'scheduled_at' => $this->nextWeekdayAt('10:00'), 'status' => 'completed', 'price' => 50,
        ]);
        foreach (['approved', 'pending', 'rejected'] as $status) {
            Review::create(['appointment_id' => $appt()->id, 'client_id' => $client->id, 'stylist_id' => $stylist->id, 'rating' => 5, 'comment' => $status, 'status' => $status]);
        }

        $this->getJson('/api/reviews')->assertOk()->assertJsonCount(1); // guests: approved only

        $manager = $this->user('manager');
        $token = $manager->createToken('t')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/reviews', ['Authorization' => "Bearer {$token}"])->assertOk()->assertJsonCount(3);
    }

    public function test_waitlist_entry_created_by_staff_with_client_id_gets_a_name(): void
    {
        Sanctum::actingAs($this->user('manager'));
        $client = Client::create(['name' => 'Named Client']);
        $this->postJson('/api/waitlist', [
            'client_id' => $client->id, 'service_id' => $this->service()->id, 'preferred_date' => now()->addDay()->toDateString(),
        ])->assertCreated();

        $this->assertSame('Named Client', WaitlistEntry::first()->client_name);
    }
}
