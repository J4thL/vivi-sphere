<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\Stylist;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Role scoping and validation paths not exercised elsewhere. */
class CoverageGapsTest extends TestCase
{
    use RefreshDatabase;

    private Stylist $ana;

    private Stylist $ben;

    private Service $service;

    private string $day;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ana = Stylist::create(['branch_id' => 1, 'name' => 'Ana', 'role' => 'Colorist', 'active' => true]);
        $this->ben = Stylist::create(['branch_id' => 1, 'name' => 'Ben', 'role' => 'Barber', 'active' => true]);
        $this->service = Service::create(['name' => 'Cut', 'price' => 50, 'duration_minutes' => 60]);
        $this->day = now()->addDays(3)->format('Y-m-d');
    }

    private function as(string $role, array $extra = []): User
    {
        $user = User::create(['name' => ucfirst($role), 'email' => $role.uniqid().'@t.test', 'password' => 'password', 'role' => $role, 'branch_id' => in_array($role, ['manager', 'stylist']) ? 1 : null] + $extra);
        Sanctum::actingAs($user);

        return $user;
    }

    private function appt(Stylist $stylist, string $time, ?Client $client = null): Appointment
    {
        return Appointment::create(['branch_id' => 1,
            'client_id' => ($client ?? Client::create(['name' => 'C'.uniqid()]))->id, 'stylist_id' => $stylist->id,
            'service_id' => $this->service->id, 'scheduled_at' => "{$this->day} $time", 'status' => 'confirmed', 'price' => 50,
        ]);
    }

    public function test_a_stylist_sees_and_changes_only_their_own_appointments(): void
    {
        $this->as('stylist', ['stylist_id' => $this->ana->id]);
        $mine = $this->appt($this->ana, '10:00:00');
        $theirs = $this->appt($this->ben, '10:00:00');

        $ids = collect($this->getJson('/api/appointments')->assertOk()->json())->pluck('id');
        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id));

        $this->patchJson("/api/appointments/{$mine->id}", ['status' => 'in-progress'])->assertOk();
        $this->patchJson("/api/appointments/{$theirs->id}", ['status' => 'in-progress'])->assertForbidden();
        $this->assertSame('confirmed', $theirs->fresh()->status);
    }

    public function test_a_client_cannot_book_under_someone_elses_client_record(): void
    {
        $me = $this->as('client');
        $other = Client::create(['name' => 'Somebody Else']);

        $res = $this->postJson('/api/appointments', [
            'client_id' => $other->id, 'stylist_id' => $this->ana->id,
            'service_id' => $this->service->id, 'scheduled_at' => "{$this->day} 11:00:00",
        ])->assertCreated();

        $booked = Appointment::findOrFail($res->json('id'));
        $this->assertNotSame($other->id, $booked->client_id);
        $this->assertSame($me->id, $booked->client->user_id);
        $this->assertSame(0, $other->appointments()->count());
    }

    public function test_stylist_fields_are_validated_on_update(): void
    {
        $this->as('manager');

        $this->patchJson("/api/stylists/{$this->ana->id}", ['photo_url' => 'not a url'])->assertStatus(422)->assertJsonValidationErrors('photo_url');
        $this->patchJson("/api/stylists/{$this->ana->id}", ['name' => str_repeat('x', 300)])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->patchJson("/api/stylists/{$this->ana->id}", ['active' => 'maybe'])->assertStatus(422)->assertJsonValidationErrors('active');
        $this->assertSame('Ana', $this->ana->fresh()->name);
    }

    public function test_service_names_stay_unique_when_renaming(): void
    {
        $this->as('admin');
        $other = Service::create(['name' => 'Colour', 'price' => 90, 'duration_minutes' => 90]);

        $this->patchJson("/api/services/{$other->id}", ['name' => 'Cut'])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->patchJson("/api/services/{$other->id}", ['name' => 'Colour'])->assertOk(); // its own name is fine
    }

    public function test_removing_a_waitlist_entry_keeps_the_record(): void
    {
        $this->as('manager');
        $entry = WaitlistEntry::create(['branch_id' => 1, 'client_name' => 'Wanda', 'service_id' => $this->service->id, 'preferred_date' => $this->day, 'status' => 'waiting']);

        $this->deleteJson("/api/waitlist/{$entry->id}")->assertOk();

        $this->assertSame('cancelled', $entry->fresh()->status); // soft-removed, never deleted
        $this->assertSame([], $this->getJson('/api/waitlist')->assertOk()->json());
    }

    // ---- same-name clients --------------------------------------------------------------------

    private function staffBook(array $who, string $time): TestResponse
    {
        return $this->postJson('/api/appointments', $who + [
            'stylist_id' => $this->ana->id, 'service_id' => $this->service->id, 'scheduled_at' => "{$this->day} $time",
        ]);
    }

    public function test_a_typed_name_that_already_exists_is_not_silently_merged(): void
    {
        $this->as('manager');
        $existing = Client::create(['name' => 'Maria Santos', 'visits' => 7, 'spend' => 700]);

        $this->staffBook(['client_name' => 'Maria Santos'], '10:00:00')->assertStatus(422)->assertJsonValidationErrors('client_name');
        $this->assertSame(0, Appointment::count());
        $this->assertSame(1, Client::where('name', 'Maria Santos')->count());
        $this->assertSame(7, (int) $existing->fresh()->visits);
    }

    public function test_staff_can_pick_the_existing_client_by_id(): void
    {
        $this->as('manager');
        $existing = Client::create(['name' => 'Maria Santos']);

        $res = $this->staffBook(['client_id' => $existing->id, 'client_name' => 'Maria Santos'], '10:00:00')->assertCreated();

        $this->assertSame($existing->id, $res->json('client_id'));
        $this->assertSame(1, Client::where('name', 'Maria Santos')->count());
    }

    public function test_staff_can_explicitly_book_a_different_person_with_the_same_name(): void
    {
        $this->as('stylist', ['stylist_id' => $this->ana->id]);
        $existing = Client::create(['name' => 'Maria Santos']);

        $res = $this->staffBook(['client_name' => 'Maria Santos', 'new_client' => true], '10:00:00')->assertCreated();

        $this->assertNotSame($existing->id, $res->json('client_id'));
        $this->assertSame(2, Client::where('name', 'Maria Santos')->count());
    }

    public function test_a_brand_new_name_needs_no_extra_step(): void
    {
        $this->as('manager');

        $this->staffBook(['client_name' => 'Totally New'], '10:00:00')->assertCreated();
        $this->assertSame(1, Client::where('name', 'Totally New')->count());
    }
}
