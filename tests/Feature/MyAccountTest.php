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
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** The customer-facing "My account": editing one's own profile, and seeing only one's own waitlist and reviews. */
class MyAccountTest extends TestCase
{
    use RefreshDatabase;

    private function client(string $name = 'Elena Marsh'): array
    {
        $user = User::create(['name' => $name, 'email' => uniqid().'@t.test', 'password' => 'password', 'role' => 'client']);
        $client = Client::create(['user_id' => $user->id, 'name' => $name, 'visits' => 4, 'spend' => 120]);

        return [$user, $client];
    }

    private function stylistAndService(): array
    {
        return [
            Stylist::create(['name' => 'Ana', 'role' => 'Stylist', 'branch_id' => 1]),
            Service::create(['name' => 'Cut', 'price' => 50, 'duration_minutes' => 30]),
        ];
    }

    public function test_me_returns_email_verification_state(): void
    {
        [$user] = $this->client();
        Sanctum::actingAs($user);

        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('email_verified_at', null);

        $user->forceFill(['email_verified_at' => now()])->save();
        $this->assertNotNull($this->getJson('/api/auth/me')->json('email_verified_at'));
    }

    public function test_a_client_can_update_their_name_and_phone_together(): void
    {
        [$user, $client] = $this->client();
        Sanctum::actingAs($user);

        $this->patchJson('/api/auth/profile', ['name' => 'Elena M. Marsh', 'phone' => '0917 123 4567'])
            ->assertOk()
            ->assertJsonPath('user.name', 'Elena M. Marsh')
            ->assertJsonPath('client.phone', '0917 123 4567');

        $this->assertSame('Elena M. Marsh', $user->fresh()->name);
        $this->assertSame('Elena M. Marsh', $client->fresh()->name);
        $this->assertSame('0917 123 4567', $client->fresh()->phone);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'profile.updated']);
    }

    public function test_a_blank_phone_clears_it_and_the_name_is_required(): void
    {
        [$user, $client] = $this->client();
        $client->update(['phone' => '123']);
        Sanctum::actingAs($user);

        $this->patchJson('/api/auth/profile', ['name' => 'Elena Marsh', 'phone' => ''])->assertOk();
        $this->assertNull($client->fresh()->phone);

        $this->patchJson('/api/auth/profile', ['name' => '', 'phone' => '1'])->assertStatus(422);
        $this->patchJson('/api/auth/profile', ['name' => '   '])->assertStatus(422);
        $this->patchJson('/api/auth/profile', ['name' => str_repeat('a', 256)])->assertStatus(422);
        $this->assertSame('Elena Marsh', $user->fresh()->name);
    }

    public function test_the_profile_cannot_change_system_controlled_fields_or_someone_elses_record(): void
    {
        [$user, $client] = $this->client();
        [, $other] = $this->client('Other Person');
        Sanctum::actingAs($user);

        $this->patchJson('/api/auth/profile', [
            'name' => 'Elena Marsh', 'visits' => 999, 'spend' => 99999, 'last_visit_at' => '2020-01-01',
            'user_id' => 12345, 'client_id' => $other->id, 'role' => 'admin', 'email' => 'x@y.test',
        ])->assertOk();

        $client->refresh();
        $this->assertSame(4, $client->visits);
        $this->assertSame('120.00', (string) $client->spend);
        $this->assertNull($client->last_visit_at);
        $this->assertSame($user->id, $client->user_id);
        $this->assertSame('client', $user->fresh()->role);
        $this->assertNotSame('x@y.test', $user->fresh()->email);
        $this->assertSame('Other Person', $other->fresh()->name);
    }

    public function test_only_clients_and_signed_in_people_can_use_the_profile_endpoint(): void
    {
        $this->patchJson('/api/auth/profile', ['name' => 'Nobody'])->assertUnauthorized();

        $admin = User::create(['name' => 'Boss', 'email' => 'boss@t.test', 'password' => 'password', 'role' => 'admin']);
        Sanctum::actingAs($admin);
        $this->patchJson('/api/auth/profile', ['name' => 'Renamed'])->assertForbidden();
        $this->assertSame('Boss', $admin->fresh()->name);
    }

    public function test_changing_the_password_needs_the_current_one_and_signs_out_other_devices(): void
    {
        [$user] = $this->client();
        $user->update(['password' => 'old-password-1']);
        $phone = $user->createToken('phone')->accessToken;
        $current = $user->createToken('web');
        $this->withHeader('Authorization', 'Bearer '.$current->plainTextToken);

        $this->postJson('/api/auth/change-password', ['current_password' => 'wrong', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
            ->assertStatus(422)->assertJsonValidationErrors(['current_password']);
        $this->postJson('/api/auth/change-password', ['current_password' => 'old-password-1', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertStatus(422)->assertJsonValidationErrors(['password']);

        $this->postJson('/api/auth/change-password', ['current_password' => 'old-password-1', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $phone->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $current->accessToken->id]);
        $this->assertTrue(Hash::check('new-password-1', $user->fresh()->password));
    }

    public function test_a_client_sees_and_can_remove_only_their_own_waitlist_requests(): void
    {
        [$user, $client] = $this->client();
        [, $otherClient] = $this->client('Other Person');
        [$stylist, $service] = $this->stylistAndService();
        $make = fn (Client $c, string $status = 'waiting') => WaitlistEntry::create(['client_id' => $c->id, 'client_name' => $c->name, 'service_id' => $service->id, 'stylist_id' => $stylist->id, 'branch_id' => 1, 'preferred_date' => now()->addDay()->toDateString(), 'status' => $status]);
        $mine = $make($client);
        $make($client, 'cancelled');
        $theirs = $make($otherClient);
        $walkIn = WaitlistEntry::create(['client_name' => 'Walk In', 'service_id' => $service->id, 'branch_id' => 1, 'preferred_date' => now()->addDay()->toDateString(), 'status' => 'waiting']);

        Sanctum::actingAs($user);
        $this->getJson('/api/waitlist')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $mine->id);

        $this->deleteJson("/api/waitlist/{$theirs->id}")->assertForbidden();
        $this->deleteJson("/api/waitlist/{$walkIn->id}")->assertForbidden();
        $this->assertSame('waiting', $theirs->fresh()->status);
        $this->assertSame('waiting', $walkIn->fresh()->status);

        $this->deleteJson("/api/waitlist/{$mine->id}")->assertOk();
        $this->assertSame('cancelled', $mine->fresh()->status);
        $this->getJson('/api/waitlist')->assertOk()->assertJsonCount(0);
    }

    public function test_managers_still_see_the_whole_waitlist_and_stylists_remain_blocked(): void
    {
        [, $client] = $this->client();
        [$stylist, $service] = $this->stylistAndService();
        WaitlistEntry::create(['client_id' => $client->id, 'client_name' => $client->name, 'service_id' => $service->id, 'branch_id' => 1, 'preferred_date' => now()->addDay()->toDateString(), 'status' => 'waiting']);

        Sanctum::actingAs(User::create(['name' => 'Boss', 'email' => 'b@t.test', 'password' => 'password', 'role' => 'admin']));
        $this->getJson('/api/waitlist')->assertOk()->assertJsonCount(1);

        Sanctum::actingAs(User::create(['name' => 'Sty', 'email' => 's@t.test', 'password' => 'password', 'role' => 'stylist', 'stylist_id' => $stylist->id, 'branch_id' => 1]));
        $this->getJson('/api/waitlist')->assertForbidden();
    }

    public function test_a_client_also_gets_their_own_pending_and_rejected_reviews_but_never_other_peoples(): void
    {
        [$user, $client] = $this->client();
        [, $otherClient] = $this->client('Other Person');
        [$stylist, $service] = $this->stylistAndService();
        $review = function (Client $c, string $status) use ($stylist, $service) {
            $appt = Appointment::create(['client_id' => $c->id, 'stylist_id' => $stylist->id, 'service_id' => $service->id, 'branch_id' => 1, 'scheduled_at' => now()->subDay(), 'status' => 'completed', 'price' => 50]);

            return Review::create(['appointment_id' => $appt->id, 'client_id' => $c->id, 'stylist_id' => $stylist->id, 'rating' => 5, 'status' => $status]);
        };
        $mine = $review($client, 'pending');
        $mineRejected = $review($client, 'rejected');
        $mineApproved = $review($client, 'approved');
        $theirsPending = $review($otherClient, 'pending');
        $theirsApproved = $review($otherClient, 'approved');

        // Signed out: only approved ones, no appointment ids.
        $public = $this->getJson('/api/reviews')->assertOk();
        $this->assertEqualsCanonicalizing([$mineApproved->id, $theirsApproved->id], collect($public->json())->pluck('id')->all());
        $this->assertNull($public->json('0.appointment_id'));

        Sanctum::actingAs($user);
        $rows = collect($this->getJson('/api/reviews')->assertOk()->json());
        $this->assertEqualsCanonicalizing([$mine->id, $mineRejected->id, $mineApproved->id, $theirsApproved->id], $rows->pluck('id')->all());
        $this->assertNotContains($theirsPending->id, $rows->pluck('id')->all());
        $this->assertSame('pending', $rows->firstWhere('id', $mine->id)['status']);
        $this->assertSame($mine->appointment_id, $rows->firstWhere('id', $mine->id)['appointment_id']);
        $this->assertArrayNotHasKey('appointment_id', $rows->firstWhere('id', $theirsApproved->id));
    }
}
