<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Review;
use App\Models\Service;
use App\Models\Stylist;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Tier1HardeningTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, array $extra = []): User
    {
        return User::factory()->create(['role' => $role] + $extra);
    }

    private function service(): Service
    {
        return Service::create(['name' => 'Cut '.uniqid(), 'price' => 50, 'duration_minutes' => 45]);
    }

    private function stylist(): Stylist
    {
        return Stylist::create(['name' => 'S '.uniqid(), 'role' => 'Stylist', 'active' => true]);
    }

    private function appointment(Client $client, string $when, string $status = 'confirmed', ?Stylist $stylist = null): Appointment
    {
        return Appointment::create([
            'client_id' => $client->id, 'stylist_id' => ($stylist ?? $this->stylist())->id, 'service_id' => $this->service()->id,
            'scheduled_at' => $when, 'status' => $status, 'price' => 50,
        ]);
    }

    private function approvedReviewFor(Client $client): Review
    {
        $appt = $this->appointment($client, '2026-01-05 10:00:00', 'completed');

        return Review::create([
            'appointment_id' => $appt->id, 'client_id' => $client->id, 'stylist_id' => $appt->stylist_id,
            'rating' => 5, 'comment' => 'Great', 'status' => 'approved',
        ]);
    }

    // ---- T1.1 reviews privacy -------------------------------------------------

    public function test_public_reviews_do_not_expose_client_private_data(): void
    {
        $client = Client::create(['name' => 'Elena Marsh', 'phone' => '(415) 555-0138', 'visits' => 14, 'spend' => 1840]);
        $this->approvedReviewFor($client);

        $response = $this->getJson('/api/reviews')->assertOk()->assertJsonCount(1);
        $response->assertJsonPath('0.client.name', 'Elena M.')
            ->assertJsonPath('0.rating', 5)
            ->assertJsonMissingPath('0.client.phone')
            ->assertJsonMissingPath('0.client.spend')
            ->assertJsonMissingPath('0.client.visits')
            ->assertJsonMissingPath('0.client.user_id')
            ->assertJsonMissingPath('0.client_id')
            ->assertJsonMissingPath('0.appointment_id');
        $this->assertStringNotContainsString('1840', $response->getContent());
        $this->assertStringNotContainsString('555-0138', $response->getContent());
    }

    public function test_client_still_sees_appointment_id_only_on_their_own_review(): void
    {
        $me = $this->user('client');
        $mine = Client::create(['user_id' => $me->id, 'name' => 'Me Myself']);
        $other = Client::create(['name' => 'Other Person']);
        $myReview = $this->approvedReviewFor($mine);
        $this->approvedReviewFor($other);

        Sanctum::actingAs($me);
        $rows = collect($this->getJson('/api/reviews')->assertOk()->json());
        $this->assertSame($myReview->appointment_id, $rows->firstWhere('id', $myReview->id)['appointment_id']);
        $this->assertArrayNotHasKey('appointment_id', $rows->firstWhere('id', '!=', $myReview->id));
    }

    public function test_landing_page_shows_reviewer_as_first_name_and_initial(): void
    {
        $this->approvedReviewFor(Client::create(['name' => 'Elena Marsh']));
        $this->get('/')->assertOk()->assertSee('Elena M.')->assertDontSee('Marsh');
    }

    // ---- T1.2 tokens ------------------------------------------------------------

    public function test_api_tokens_expire_after_seven_days(): void
    {
        $this->assertSame(10080, config('sanctum.expiration'));

        $token = $this->user('client')->createToken('t')->plainTextToken;
        $headers = ['Authorization' => "Bearer {$token}"];

        $this->travel(6)->days();
        $this->getJson('/api/auth/me', $headers)->assertOk();

        $this->app['auth']->forgetGuards();
        $this->travel(2)->days();
        $this->getJson('/api/auth/me', $headers)->assertUnauthorized();
    }

    public function test_password_reset_revokes_existing_tokens(): void
    {
        $user = $this->user('client', ['email' => 'a@example.test']);
        $user->createToken('old-device');
        $this->assertSame(1, $user->tokens()->count());

        $this->postJson('/api/auth/reset-password', [
            'token' => Password::createToken($user), 'email' => 'a@example.test',
            'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
        ])->assertOk();

        $this->assertSame(0, $user->fresh()->tokens()->count());
    }

    // ---- T1.3 headers -----------------------------------------------------------

    public function test_security_headers_are_sent_on_pages_and_api(): void
    {
        foreach (['/', '/app/login', '/api/settings'] as $url) {
            $r = $this->get($url);
            $r->assertHeader('X-Content-Type-Options', 'nosniff');
            $r->assertHeader('X-Frame-Options', 'SAMEORIGIN');
            $r->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
            $r->assertHeader('Permissions-Policy');
            $csp = $r->headers->get('Content-Security-Policy');
            $this->assertStringContainsString("frame-ancestors 'self'", $csp);
            $this->assertStringContainsString("object-src 'none'", $csp);
            $this->assertStringContainsString("script-src 'self' 'nonce-", $csp);
            $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);
        }
    }

    public function test_csp_nonce_matches_the_inline_script_in_the_spa_shell(): void
    {
        $r = $this->get('/app/login');
        preg_match("/'nonce-([^']+)'/", $r->headers->get('Content-Security-Policy'), $m);
        $this->assertNotEmpty($m[1] ?? null);
        $this->assertStringContainsString('nonce="'.$m[1].'"', $r->getContent());
    }

    public function test_hsts_only_on_https(): void
    {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');
        $this->withServerVariables(['HTTPS' => 'on'])->get('https://localhost/')->assertHeader('Strict-Transport-Security');
    }

    // ---- T1.6 appointment rules ---------------------------------------------------

    public function test_bookings_in_the_past_are_rejected_with_a_short_grace_period(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 10:00:00', config('app.salon_timezone')));
        Sanctum::actingAs($this->user('client'));
        $stylist = $this->stylist();
        $service = $this->service();
        $book = fn ($t) => $this->postJson('/api/appointments', [
            'client_name' => 'Me', 'stylist_id' => $stylist->id, 'service_id' => $service->id, 'scheduled_at' => $t,
        ]);

        $book('2026-10-13 10:00:00')->assertStatus(422)->assertJsonValidationErrors('scheduled_at');
        $book('2026-10-14 09:30:00')->assertStatus(422)->assertJsonValidationErrors('scheduled_at');
        $book('2026-10-14 09:50:00')->assertCreated(); // within the 15-minute grace
        $book('2026-10-15 10:00:00')->assertCreated();
    }

    public function test_cannot_reschedule_into_the_past(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 10:00:00', config('app.salon_timezone')));
        $admin = $this->user('admin');
        Sanctum::actingAs($admin);
        $appt = $this->appointment(Client::create(['name' => 'X']), '2026-10-15 10:00:00');

        $this->patchJson("/api/appointments/{$appt->id}", ['scheduled_at' => '2026-10-10 10:00:00'])
            ->assertStatus(422)->assertJsonValidationErrors('scheduled_at');
        $this->patchJson("/api/appointments/{$appt->id}", ['status' => 'completed'])->assertOk(); // status change is unaffected
    }

    public function test_client_can_only_modify_confirmed_appointments(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 08:00:00', config('app.salon_timezone')));
        $me = $this->user('client');
        $client = Client::create(['user_id' => $me->id, 'name' => $me->name]);
        $done = $this->appointment($client, '2026-10-14 08:30:00', 'completed');
        $cancelled = $this->appointment($client, '2026-10-15 10:00:00', 'cancelled');
        $open = $this->appointment($client, '2026-10-15 12:00:00');
        Sanctum::actingAs($me);

        $this->patchJson("/api/appointments/{$done->id}", ['scheduled_at' => '2026-10-16 10:00:00'])->assertForbidden();
        $this->patchJson("/api/appointments/{$cancelled->id}", ['scheduled_at' => '2026-10-16 10:00:00'])->assertForbidden();
        $this->patchJson("/api/appointments/{$open->id}", ['scheduled_at' => '2026-10-16 10:00:00'])->assertOk();
    }

    public function test_stylist_cannot_hand_their_appointment_to_another_stylist_but_manager_can(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 08:00:00', config('app.salon_timezone')));
        $mine = $this->stylist();
        $other = $this->stylist();
        $stylistUser = $this->user('stylist', ['stylist_id' => $mine->id]);
        $appt = $this->appointment(Client::create(['name' => 'X']), '2026-10-15 10:00:00', 'confirmed', $mine);

        Sanctum::actingAs($stylistUser);
        $this->patchJson("/api/appointments/{$appt->id}", ['stylist_id' => $other->id])->assertForbidden();
        $this->patchJson("/api/appointments/{$appt->id}", ['status' => 'in-progress'])->assertOk();

        Sanctum::actingAs($this->user('manager'));
        $this->patchJson("/api/appointments/{$appt->id}", ['stylist_id' => $other->id])->assertOk();
    }
}
