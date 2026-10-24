<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dashboard's fetch calls don't send an `Accept: application/json` header. An expired
 * or missing token used to produce a 500 error page (Laravel tried to redirect to a login
 * route that doesn't exist), so the dashboard could never tell the session had expired.
 */
class Tier3AuthResponsesTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_token_gets_a_json_401_even_without_an_accept_header(): void
    {
        // get() / patch() / post() deliberately do NOT set Accept: application/json
        $this->get('/api/appointments')
            ->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);

        $this->patch('/api/stylists/1', ['active' => true])->assertStatus(401);
        $this->post('/api/appointments', [])->assertStatus(401);
    }

    public function test_invalid_or_revoked_token_gets_a_json_401(): void
    {
        $this->withHeader('Authorization', 'Bearer 999|not-a-real-token')
            ->get('/api/appointments')
            ->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_token_stops_working_once_deleted(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $token = $user->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)->get('/api/appointments')->assertOk();

        $user->tokens()->delete();
        $this->app['auth']->forgetGuards(); // drop the guard's cached user, as a fresh request would

        $this->withHeader('Authorization', 'Bearer '.$token)->get('/api/appointments')->assertStatus(401);
    }

    public function test_public_endpoints_still_work_without_a_token(): void
    {
        $this->get('/api/services')->assertOk();
        $this->get('/api/stylists')->assertOk();
    }

    public function test_wrong_password_is_still_a_plain_401_with_a_message(): void
    {
        $this->post('/api/auth/login', ['email' => 'nobody@example.test', 'password' => 'wrong-password'])
            ->assertStatus(401)
            ->assertJson(['message' => 'Invalid credentials.']);
    }
}
