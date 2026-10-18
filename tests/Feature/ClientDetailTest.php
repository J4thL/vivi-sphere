<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Review;
use App\Models\Service;
use App\Models\Stylist;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** The staff client detail panel: GET /api/clients/{id}. */
class ClientDetailTest extends TestCase
{
    use RefreshDatabase;

    private function as(string $role, ?int $branchId = null): User
    {
        $u = User::create(['name' => ucfirst($role), 'email' => $role.uniqid().'@t.test', 'password' => 'password', 'role' => $role, 'branch_id' => $branchId]);
        Sanctum::actingAs($u);

        return $u;
    }

    private function seedClient(): Client
    {
        $second = Branch::create(['name' => 'Second']);
        $cut = Service::create(['name' => 'Cut', 'price' => 50, 'duration_minutes' => 30]);
        $dye = Service::create(['name' => 'Colour', 'price' => 90, 'duration_minutes' => 60]);
        $ana = Stylist::create(['name' => 'Ana', 'role' => 'X', 'branch_id' => 1]);
        $ben = Stylist::create(['name' => 'Ben', 'role' => 'X', 'branch_id' => $second->id]);
        $client = Client::create(['name' => 'Elena Marsh', 'phone' => '0917', 'visits' => 3, 'spend' => 190]);

        $book = fn (Service $s, Stylist $st, string $status, int $daysAgo) => Appointment::create([
            'client_id' => $client->id, 'stylist_id' => $st->id, 'service_id' => $s->id, 'branch_id' => $st->branch_id,
            'scheduled_at' => now()->subDays($daysAgo), 'status' => $status, 'price' => $s->price,
        ]);
        $book($cut, $ana, 'completed', 30);
        $book($cut, $ana, 'completed', 20);
        $last = $book($dye, $ben, 'completed', 10);
        $book($cut, $ana, 'cancelled', 5);
        Review::create(['appointment_id' => $last->id, 'client_id' => $client->id, 'stylist_id' => $ben->id, 'rating' => 4, 'status' => 'pending']);
        WaitlistEntry::create(['client_id' => $client->id, 'client_name' => $client->name, 'service_id' => $cut->id, 'branch_id' => 1, 'preferred_date' => now()->addDay()->toDateString(), 'status' => 'waiting']);

        return $client;
    }

    public function test_a_manager_sees_the_whole_history_across_branches(): void
    {
        $client = $this->seedClient();
        $this->as('manager', 1);

        $r = $this->getJson("/api/clients/{$client->id}")->assertOk();
        $r->assertJsonPath('client.name', 'Elena Marsh')
            ->assertJsonCount(4, 'visits')
            ->assertJsonPath('favourite_service', 'Cut')
            ->assertJsonPath('favourite_stylist', 'Ana')
            ->assertJsonPath('reviews.0.status', 'pending')
            ->assertJsonCount(1, 'waitlist');
        $this->assertEquals(63.33, $r->json('average_spend'));
        $this->assertContains('Second', collect($r->json('visits'))->pluck('branch')->all());
        // Newest first.
        $dates = collect($r->json('visits'))->pluck('scheduled_at')->all();
        $sorted = $dates;
        rsort($sorted);
        $this->assertSame($sorted, $dates);
    }

    public function test_a_new_client_has_no_favourites_or_average(): void
    {
        $client = Client::create(['name' => 'Fresh']);
        $this->as('admin');

        $this->getJson("/api/clients/{$client->id}")->assertOk()
            ->assertJsonPath('favourite_service', null)
            ->assertJsonPath('average_spend', null)
            ->assertJsonCount(0, 'visits');
    }

    public function test_only_managers_can_open_a_client_and_unknown_ids_404(): void
    {
        $client = $this->seedClient();

        $this->getJson("/api/clients/{$client->id}")->assertUnauthorized();

        $this->as('stylist', 1);
        $this->getJson("/api/clients/{$client->id}")->assertForbidden();

        $own = $this->as('client');
        Client::create(['user_id' => $own->id, 'name' => 'Me']);
        $this->getJson("/api/clients/{$client->id}")->assertForbidden();

        $this->as('admin');
        $this->getJson('/api/clients/999999')->assertNotFound();
    }
}
