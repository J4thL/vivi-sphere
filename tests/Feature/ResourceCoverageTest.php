<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\SalonSetting;
use App\Models\Service;
use App\Models\Stylist;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Who may use each resource, and what each endpoint accepts and rejects. */
class ResourceCoverageTest extends TestCase
{
    use RefreshDatabase;

    private function as(string $role, array $extra = []): User
    {
        $user = User::create(['name' => ucfirst($role), 'email' => $role.uniqid().'@t.test', 'password' => 'password', 'role' => $role, 'branch_id' => in_array($role, ['manager', 'stylist']) ? 1 : null] + $extra);
        Sanctum::actingAs($user);

        return $user;
    }

    private function stylist(string $name = 'Marisol', bool $active = true): Stylist
    {
        return Stylist::create(['branch_id' => 1, 'name' => $name, 'role' => 'Colorist', 'active' => $active, 'branch_id' => 1]);
    }

    // ---- inventory -------------------------------------------------------------------

    public function test_only_managers_can_see_or_change_inventory(): void
    {
        $item = InventoryItem::create(['name' => 'Developer', 'qty' => 5, 'reorder_level' => 2, 'unit_cost' => 10]);

        foreach (['client', 'stylist'] as $role) {
            $this->as($role);
            $this->getJson('/api/inventory')->assertForbidden();
            $this->postJson('/api/inventory', ['name' => 'X', 'qty' => 1, 'reorder_level' => 1, 'unit_cost' => 1])->assertForbidden();
            $this->patchJson("/api/inventory/{$item->id}", ['qty' => 99])->assertForbidden();
        }
        $this->assertSame(5, $item->fresh()->qty);
    }

    public function test_a_manager_can_add_and_adjust_inventory_with_an_audit_trail(): void
    {
        $this->as('manager');

        $id = $this->postJson('/api/inventory', ['name' => 'Shampoo', 'category' => 'Care', 'qty' => 10, 'reorder_level' => 3, 'unit_cost' => 4.5])
            ->assertCreated()->json('id');
        $this->patchJson("/api/inventory/{$id}", ['qty' => 2])->assertOk()->assertJsonPath('qty', 2);
        $this->getJson('/api/inventory')->assertOk()->assertJsonCount(1);

        $this->assertSame(1, AuditLog::where('action', 'inventory.created')->count());
        $this->assertSame(1, AuditLog::where('action', 'inventory.updated')->count());
    }

    public function test_inventory_rejects_negative_or_non_numeric_values(): void
    {
        $this->as('manager');
        $item = InventoryItem::create(['name' => 'Developer', 'qty' => 5, 'reorder_level' => 2, 'unit_cost' => 10]);

        $this->postJson('/api/inventory', ['name' => 'X', 'qty' => -1, 'reorder_level' => 1, 'unit_cost' => 1])->assertStatus(422)->assertJsonValidationErrors('qty');
        $this->postJson('/api/inventory', ['name' => 'X', 'qty' => 1, 'reorder_level' => 1, 'unit_cost' => 'free'])->assertStatus(422)->assertJsonValidationErrors('unit_cost');
        $this->patchJson("/api/inventory/{$item->id}", ['qty' => -3])->assertStatus(422);
        $this->patchJson("/api/inventory/{$item->id}", ['qty' => 1.5])->assertStatus(422);
        $this->assertSame(5, $item->fresh()->qty);
    }

    // ---- services --------------------------------------------------------------------

    public function test_the_service_list_is_public(): void
    {
        Service::create(['name' => 'Cut', 'price' => 50, 'duration_minutes' => 45]);

        $this->getJson('/api/services')->assertOk()->assertJsonCount(1);
    }

    public function test_only_admins_can_manage_services(): void
    {
        $service = Service::create(['name' => 'Cut', 'price' => 50, 'duration_minutes' => 45]);

        $this->as('manager');
        $this->postJson('/api/services', ['name' => 'New', 'price' => 10, 'duration_minutes' => 30])->assertForbidden();
        $this->patchJson("/api/services/{$service->id}", ['price' => 1])->assertForbidden();
        $this->deleteJson("/api/services/{$service->id}")->assertForbidden();

        $this->assertSame('50.00', (string) $service->fresh()->price);
    }

    public function test_an_admin_can_create_reprice_and_delete_a_service_with_audit_entries(): void
    {
        $this->as('admin');

        $id = $this->postJson('/api/services', ['name' => 'Gloss', 'price' => 40, 'duration_minutes' => 30])->assertCreated()->json('id');
        $this->patchJson("/api/services/{$id}", ['price' => 55])->assertOk();
        $log = AuditLog::where('action', 'service.price_updated')->firstOrFail();
        $this->assertEquals(40, $log->meta['old']);
        $this->assertEquals(55, $log->meta['new']);

        $this->deleteJson("/api/services/{$id}")->assertOk();
        $this->assertNull(Service::find($id));
    }

    public function test_service_input_is_validated(): void
    {
        $this->as('admin');
        Service::create(['name' => 'Cut', 'price' => 50, 'duration_minutes' => 45]);

        $this->postJson('/api/services', ['name' => 'Cut', 'price' => 10, 'duration_minutes' => 30])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->postJson('/api/services', ['name' => 'Free', 'price' => -5, 'duration_minutes' => 30])->assertStatus(422)->assertJsonValidationErrors('price');
        $this->postJson('/api/services', ['name' => 'Instant', 'price' => 5, 'duration_minutes' => 0])->assertStatus(422)->assertJsonValidationErrors('duration_minutes');
        $this->postJson('/api/services', ['name' => 'Pic', 'price' => 5, 'duration_minutes' => 30, 'photo_url' => 'javascript:alert(1)'])->assertStatus(422)->assertJsonValidationErrors('photo_url');
    }

    public function test_a_service_with_bookings_cannot_be_deleted(): void
    {
        $this->as('admin');
        $service = Service::create(['name' => 'Cut', 'price' => 50, 'duration_minutes' => 45]);
        $client = Client::create(['name' => 'C']);
        Appointment::create(['branch_id' => 1, 'client_id' => $client->id, 'stylist_id' => $this->stylist()->id, 'service_id' => $service->id, 'scheduled_at' => now()->addDay()->format('Y-m-d').' 10:00:00', 'status' => 'confirmed', 'price' => 50]);

        $this->deleteJson("/api/services/{$service->id}")->assertStatus(422);
        $this->assertNotNull($service->fresh());
        $this->assertSame(1, Appointment::count());
    }

    // ---- stylists --------------------------------------------------------------------

    public function test_the_stylist_list_is_public(): void
    {
        $this->stylist();

        $this->getJson('/api/stylists')->assertOk()->assertJsonCount(1);
    }

    public function test_only_managers_can_create_or_edit_stylists(): void
    {
        $stylist = $this->stylist();

        foreach (['client', 'stylist'] as $role) {
            $this->as($role);
            $this->postJson('/api/stylists', ['name' => 'X', 'role' => 'Y'])->assertForbidden();
            $this->patchJson("/api/stylists/{$stylist->id}", ['active' => false])->assertForbidden();
        }
        $this->assertTrue((bool) $stylist->fresh()->active);
    }

    public function test_a_manager_can_deactivate_a_stylist_and_it_is_audit_logged(): void
    {
        $this->as('manager');
        $stylist = $this->stylist();

        $this->patchJson("/api/stylists/{$stylist->id}", ['active' => false])->assertOk();

        $this->assertFalse((bool) $stylist->fresh()->active);
        $this->assertSame(1, AuditLog::where('action', 'stylist.updated')->count());
    }

    public function test_creating_a_stylist_validates_input_and_unique_login_email(): void
    {
        $this->as('manager');
        User::create(['name' => 'Taken', 'email' => 'taken@t.test', 'password' => 'password', 'role' => 'client']);

        $this->postJson('/api/stylists', ['role' => 'Colorist'])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->postJson('/api/stylists', ['name' => 'Ana', 'role' => 'Colorist', 'email' => 'taken@t.test'])->assertStatus(422)->assertJsonValidationErrors('email');
        $this->postJson('/api/stylists', ['name' => 'Ana', 'role' => 'Colorist', 'photo_url' => 'not a url'])->assertStatus(422)->assertJsonValidationErrors('photo_url');
        $this->assertSame(0, Stylist::count());
    }

    // ---- settings --------------------------------------------------------------------

    public function test_settings_are_public_to_read_but_only_admins_can_change_them(): void
    {
        $this->getJson('/api/settings')->assertOk()->assertJsonStructure(['salon_name', 'open_time', 'close_time']);

        foreach (['client', 'stylist', 'manager'] as $role) {
            $this->as($role);
            $this->patchJson('/api/settings', ['salon_name' => 'Hacked'])->assertForbidden();
        }
        $this->assertNotSame('Hacked', SalonSetting::current()->salon_name);
    }

    public function test_settings_field_limits_are_enforced(): void
    {
        $this->as('admin');

        $this->patchJson('/api/settings', ['salon_name' => str_repeat('a', 256)])->assertStatus(422)->assertJsonValidationErrors('salon_name');
        $this->patchJson('/api/settings', ['open_time' => '9am'])->assertStatus(422)->assertJsonValidationErrors('open_time');
        $this->patchJson('/api/settings', ['contact_phone' => str_repeat('1', 51)])->assertStatus(422);
        $this->patchJson('/api/settings', ['salon_name' => 'Vivi Davao', 'contact_phone' => null])->assertOk()->assertJsonPath('salon_name', 'Vivi Davao');
    }

    // ---- waitlist --------------------------------------------------------------------

    public function test_only_managers_can_see_or_remove_waitlist_entries(): void
    {
        $service = Service::create(['name' => 'Cut', 'price' => 50, 'duration_minutes' => 45]);
        $entry = WaitlistEntry::create(['branch_id' => 1, 'client_name' => 'W', 'service_id' => $service->id, 'preferred_date' => now()->addDay()->format('Y-m-d'), 'status' => 'waiting']);

        foreach (['client', 'stylist'] as $role) {
            $this->as($role);
            // A client may open the list, but it holds only their own requests (see MyAccountTest); stylists stay blocked.
            if ($role === 'client') {
                $this->getJson('/api/waitlist')->assertOk()->assertJsonCount(0);
            } else {
                $this->getJson('/api/waitlist')->assertForbidden();
            }
            $this->deleteJson("/api/waitlist/{$entry->id}")->assertForbidden();
        }
        $this->assertSame('waiting', $entry->fresh()->status);
    }

    public function test_a_manager_sees_waiting_entries_and_removal_hides_them(): void
    {
        $this->as('manager');
        $service = Service::create(['name' => 'Cut', 'price' => 50, 'duration_minutes' => 45]);
        $day = now()->addDays(2)->format('Y-m-d');
        $id = $this->postJson('/api/waitlist', ['client_name' => 'Wanda', 'service_id' => $service->id, 'preferred_date' => $day])->assertCreated()->json('id');

        $this->getJson('/api/waitlist')->assertOk()->assertJsonCount(1);
        $this->deleteJson("/api/waitlist/{$id}")->assertOk();
        $this->getJson('/api/waitlist')->assertOk()->assertJsonCount(0);
        $this->assertSame('cancelled', WaitlistEntry::find($id)->status, 'removed entries are kept, not erased');
    }

    public function test_a_client_joining_the_waitlist_is_linked_to_their_own_record(): void
    {
        $user = $this->as('client');
        $service = Service::create(['name' => 'Cut', 'price' => 50, 'duration_minutes' => 45]);
        $other = Client::create(['name' => 'Someone Else']);

        $res = $this->postJson('/api/waitlist', ['client_id' => $other->id, 'service_id' => $service->id, 'branch_id' => 1, 'preferred_date' => now()->addDays(2)->format('Y-m-d')])->assertCreated();

        $own = Client::where('user_id', $user->id)->firstOrFail();
        $this->assertSame($own->id, $res->json('client_id'), 'a client cannot put the entry under another client');
    }

    // ---- clients & audit log -----------------------------------------------------------

    public function test_client_records_are_private(): void
    {
        $mine = $this->as('client');
        Client::create(['name' => 'Mine', 'user_id' => $mine->id]);
        Client::create(['name' => 'Not Mine', 'phone' => '555-0100']);

        $this->getJson('/api/clients')->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'Mine');

        $this->as('stylist');
        $this->getJson('/api/clients')->assertForbidden();

        $this->as('manager');
        $this->getJson('/api/clients')->assertOk()->assertJsonCount(2);
    }

    public function test_the_audit_log_is_admin_only(): void
    {
        foreach (['client', 'stylist', 'manager'] as $role) {
            $this->as($role);
            $this->getJson('/api/audit-log')->assertForbidden();
        }
        $this->as('admin');
        $this->getJson('/api/audit-log')->assertOk();
    }
}
