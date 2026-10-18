<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Staff editing of a client (name, phone, private notes): PATCH /api/clients/{id}. */
class ClientNotesTest extends TestCase
{
    use RefreshDatabase;

    private function as(string $role): User
    {
        $u = User::create(['name' => ucfirst($role), 'email' => $role.uniqid().'@t.test', 'password' => 'password', 'role' => $role, 'branch_id' => $role === 'admin' ? null : 1]);
        Sanctum::actingAs($u);

        return $u;
    }

    public function test_a_manager_saves_notes_and_they_come_back_in_the_detail_view(): void
    {
        $client = Client::create(['name' => 'Elena', 'visits' => 3, 'spend' => 150]);
        $manager = $this->as('manager');

        $this->patchJson("/api/clients/{$client->id}", ['notes' => '  Allergic to ammonia dye  '])
            ->assertOk()->assertJsonPath('notes', 'Allergic to ammonia dye');
        $this->getJson("/api/clients/{$client->id}")->assertOk()->assertJsonPath('client.notes', 'Allergic to ammonia dye');

        // The audit entry records which fields changed, never their (possibly private) values.
        $log = AuditLog::where('action', 'client.updated')->firstOrFail();
        $this->assertSame($manager->id, $log->user_id);
        $this->assertSame(['fields' => ['notes']], $log->meta);

        $this->patchJson("/api/clients/{$client->id}", ['notes' => ''])->assertOk();
        $this->assertNull($client->fresh()->notes);
    }

    public function test_renaming_a_client_with_a_login_renames_the_login_too_and_system_fields_are_ignored(): void
    {
        $user = User::create(['name' => 'Elena', 'email' => 'e@t.test', 'password' => 'password', 'role' => 'client']);
        $client = Client::create(['user_id' => $user->id, 'name' => 'Elena', 'visits' => 3, 'spend' => 150]);
        $this->as('admin');

        $this->patchJson("/api/clients/{$client->id}", ['name' => 'Elena Marsh', 'phone' => '0917 111 2222', 'visits' => 99, 'spend' => 9999, 'user_id' => 1])->assertOk();

        $this->assertSame('Elena Marsh', $user->fresh()->name);
        $client->refresh();
        $this->assertSame('Elena Marsh', $client->name);
        $this->assertSame(3, $client->visits);
        $this->assertSame('150.00', (string) $client->spend);
        $this->assertSame($user->id, $client->user_id);

        $this->patchJson("/api/clients/{$client->id}", ['name' => '   '])->assertStatus(422);
        $this->patchJson("/api/clients/{$client->id}", ['notes' => str_repeat('x', 2001)])->assertStatus(422);
    }

    public function test_a_phone_already_used_by_another_client_needs_confirmation(): void
    {
        Client::create(['name' => 'Mum', 'phone' => '0917-111-2222']);
        $kid = Client::create(['name' => 'Kid']);
        $this->as('manager');

        $this->patchJson("/api/clients/{$kid->id}", ['phone' => '(0917) 111 2222'])
            ->assertStatus(422)->assertJsonPath('code', 'duplicate_phone')->assertJsonPath('duplicates.0.name', 'Mum');
        $this->assertNull($kid->fresh()->phone);

        $this->patchJson("/api/clients/{$kid->id}", ['phone' => '0917 111 2222', 'confirm_duplicate' => true])->assertOk();
        $this->assertSame('0917 111 2222', $kid->fresh()->phone);

        // Still refused on a later save while the number matches another client, until confirmed.
        $this->patchJson("/api/clients/{$kid->id}", ['phone' => '0917 111 2222', 'notes' => 'x'])->assertStatus(422);
        $this->patchJson("/api/clients/{$kid->id}", ['notes' => 'only notes'])->assertOk();
    }

    public function test_only_managers_can_edit_and_notes_never_reach_clients_or_stylists(): void
    {
        $user = User::create(['name' => 'Elena', 'email' => 'e@t.test', 'password' => 'password', 'role' => 'client']);
        $client = Client::create(['user_id' => $user->id, 'name' => 'Elena', 'notes' => 'PRIVATE STAFF NOTE']);

        $this->patchJson("/api/clients/{$client->id}", ['notes' => 'x'])->assertUnauthorized();

        Sanctum::actingAs($user);
        $this->patchJson("/api/clients/{$client->id}", ['notes' => 'x'])->assertForbidden();
        $this->assertStringNotContainsString('PRIVATE STAFF NOTE', $this->getJson('/api/clients')->assertOk()->getContent());
        $this->assertStringNotContainsString('PRIVATE STAFF NOTE', $this->patchJson('/api/auth/profile', ['name' => 'Elena'])->assertOk()->getContent());

        $this->as('stylist');
        $this->patchJson("/api/clients/{$client->id}", ['notes' => 'x'])->assertForbidden();
        $this->getJson("/api/clients/{$client->id}")->assertForbidden();
        $this->assertSame('PRIVATE STAFF NOTE', $client->fresh()->notes);
    }
}
