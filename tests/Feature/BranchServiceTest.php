<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchService;
use App\Models\Client;
use App\Models\Service;
use App\Models\Stylist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Multi-branch step 5: a branch can have its own price, length and availability for a service. */
class BranchServiceTest extends TestCase
{
    use RefreshDatabase;

    private Branch $a;
    private Branch $b;
    private Stylist $ana;
    private Stylist $ben;
    private Service $cut;
    private Service $color;
    private string $day;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Branch::findOrFail(1);
        $this->b = Branch::create(['name' => 'Second']);
        $this->ana = Stylist::create(['name' => 'Ana', 'role' => 'X', 'active' => true, 'branch_id' => $this->a->id]);
        $this->ben = Stylist::create(['name' => 'Ben', 'role' => 'X', 'active' => true, 'branch_id' => $this->b->id]);
        $this->cut = Service::create(['name' => 'Cut', 'price' => 50, 'duration_minutes' => 30]);
        $this->color = Service::create(['name' => 'Color', 'price' => 120, 'duration_minutes' => 90]);
        $this->day = now()->addDays(3)->format('Y-m-d');
    }

    private function as(string $role, ?int $branchId = null): User
    {
        $u = User::create(['name' => ucfirst($role), 'email' => $role.uniqid().'@t.test', 'password' => 'password', 'role' => $role, 'branch_id' => $branchId]);
        Sanctum::actingAs($u);

        return $u;
    }

    private function setTerms(Branch $b, Service $s, array $body)
    {
        return $this->putJson("/api/branches/{$b->id}/services/{$s->id}", $body);
    }

    public function test_with_no_overrides_every_branch_uses_the_normal_price_and_length(): void
    {
        $this->as('client');
        $id = $this->postJson('/api/appointments', ['client_name' => 'Me', 'stylist_id' => $this->ben->id, 'service_id' => $this->cut->id, 'scheduled_at' => "{$this->day} 11:00:00"])->assertCreated()->json('id');

        $appt = Appointment::findOrFail($id);
        $this->assertSame('50.00', (string) $appt->price);
        $this->assertSame(30, (int) $appt->duration_minutes);
    }

    public function test_a_booking_uses_the_branchs_own_price_and_length(): void
    {
        $this->as('admin');
        $this->setTerms($this->b, $this->cut, ['price' => 65, 'duration_minutes' => 45])->assertOk();

        $this->as('client');
        $atB = $this->postJson('/api/appointments', ['client_name' => 'Me', 'stylist_id' => $this->ben->id, 'service_id' => $this->cut->id, 'scheduled_at' => "{$this->day} 11:00:00"])->assertCreated()->json('id');
        $atA = $this->postJson('/api/appointments', ['client_name' => 'Me2', 'stylist_id' => $this->ana->id, 'service_id' => $this->cut->id, 'scheduled_at' => "{$this->day} 11:00:00"])->assertCreated()->json('id');

        $this->assertSame('65.00', (string) Appointment::findOrFail($atB)->price);
        $this->assertSame(45, (int) Appointment::findOrFail($atB)->duration_minutes);
        $this->assertSame('50.00', (string) Appointment::findOrFail($atA)->price);
    }

    public function test_a_branch_can_stop_offering_a_service(): void
    {
        $this->as('admin');
        $this->setTerms($this->b, $this->color, ['available' => false])->assertOk()->assertJsonPath('available', false);

        $this->as('client');
        $this->postJson('/api/appointments', ['client_name' => 'Me', 'stylist_id' => $this->ben->id, 'service_id' => $this->color->id, 'scheduled_at' => "{$this->day} 11:00:00"])
            ->assertStatus(422)->assertJsonValidationErrors('service_id');
        $this->postJson('/api/appointments', ['client_name' => 'Me', 'stylist_id' => $this->ana->id, 'service_id' => $this->color->id, 'scheduled_at' => "{$this->day} 11:00:00"])->assertCreated();
        $this->postJson('/api/waitlist', ['client_name' => 'Me', 'service_id' => $this->color->id, 'branch_id' => $this->b->id, 'preferred_date' => $this->day])->assertStatus(422);
    }

    public function test_changing_a_branch_price_does_not_change_existing_appointments(): void
    {
        $this->as('client');
        $id = $this->postJson('/api/appointments', ['client_name' => 'Me', 'stylist_id' => $this->ben->id, 'service_id' => $this->cut->id, 'scheduled_at' => "{$this->day} 11:00:00"])->assertCreated()->json('id');

        $this->as('admin');
        $this->setTerms($this->b, $this->cut, ['price' => 99])->assertOk();

        $this->assertSame('50.00', (string) Appointment::findOrFail($id)->price);
    }

    public function test_clearing_the_overrides_goes_back_to_the_normal_values_and_removes_the_row(): void
    {
        $this->as('admin');
        $this->setTerms($this->b, $this->cut, ['price' => 65, 'duration_minutes' => 45, 'available' => false])->assertOk();
        $this->assertSame(1, BranchService::count());

        $this->setTerms($this->b, $this->cut, ['price' => null, 'duration_minutes' => null, 'available' => true])->assertOk()->assertJsonPath('price', null);
        $this->assertSame(0, BranchService::count());
        $this->assertSame(2, AuditCount::of('branch_service.'));
    }

    public function test_only_the_admin_can_change_them_but_anyone_can_read_them(): void
    {
        foreach (['manager', 'stylist', 'client'] as $role) {
            $this->as($role, $this->b->id);
            $this->setTerms($this->b, $this->cut, ['price' => 1])->assertForbidden();
        }
        $this->assertSame(0, BranchService::count());

        $this->as('admin');
        $this->setTerms($this->b, $this->cut, ['price' => 65])->assertOk();
        $this->setTerms($this->b, $this->cut, ['price' => -5])->assertStatus(422);
        $this->setTerms($this->b, $this->cut, ['duration_minutes' => 2])->assertStatus(422);

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/branch-services')->assertOk()->assertJsonCount(1)->assertJsonPath('0.price', 65);
    }

    public function test_changing_to_a_service_not_offered_at_the_branch_is_refused(): void
    {
        $this->as('admin');
        $this->setTerms($this->b, $this->color, ['available' => false])->assertOk();
        $client = Client::create(['name' => 'C']);
        $appt = Appointment::create(['client_id' => $client->id, 'stylist_id' => $this->ben->id, 'service_id' => $this->cut->id, 'branch_id' => $this->b->id, 'scheduled_at' => "{$this->day} 11:00:00", 'status' => 'confirmed', 'price' => 50]);

        $this->patchJson("/api/appointments/{$appt->id}", ['service_id' => $this->color->id])->assertStatus(422);
    }
}

/** Tiny helper so the test above reads clearly. */
class AuditCount
{
    public static function of(string $prefix): int
    {
        return \App\Models\AuditLog::where('action', 'like', $prefix.'%')->count();
    }
}
