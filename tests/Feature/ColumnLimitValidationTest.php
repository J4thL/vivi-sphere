<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Values that would overflow a database column must be answered with 422, not a 500. */
class ColumnLimitValidationTest extends TestCase
{
    use RefreshDatabase;

    private function asAdmin(): void
    {
        Sanctum::actingAs(User::create(['name' => 'Admin', 'email' => 'a@t.test', 'password' => 'password', 'role' => 'admin']));
    }

    public function test_service_price_duration_and_photo_are_bounded(): void
    {
        $this->asAdmin();

        $this->postJson('/api/services', ['name' => 'X', 'price' => 99999999, 'duration_minutes' => 30])->assertStatus(422)->assertJsonValidationErrors('price');
        $this->postJson('/api/services', ['name' => 'X', 'price' => 10, 'duration_minutes' => 70000])->assertStatus(422)->assertJsonValidationErrors('duration_minutes');
        $this->postJson('/api/services', ['name' => 'X', 'price' => 10, 'duration_minutes' => 30, 'photo_url' => 'javascript://x.com/%0Aalert(1)'])->assertStatus(422)->assertJsonValidationErrors('photo_url');
        $this->postJson('/api/services', ['name' => 'X', 'price' => 10, 'duration_minutes' => 30, 'photo_url' => 'https://example.com/a.jpg'])->assertCreated();
    }

    public function test_inventory_numbers_are_bounded(): void
    {
        $this->asAdmin();

        $this->postJson('/api/inventory', ['name' => 'Dye', 'qty' => 5000000000, 'reorder_level' => 1, 'unit_cost' => 1])->assertStatus(422)->assertJsonValidationErrors('qty');
        $this->postJson('/api/inventory', ['name' => 'Dye', 'qty' => 5, 'reorder_level' => 1, 'unit_cost' => 99999999])->assertStatus(422)->assertJsonValidationErrors('unit_cost');
    }
}
