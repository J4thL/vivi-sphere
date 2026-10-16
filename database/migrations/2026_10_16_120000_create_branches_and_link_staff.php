<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-branch, step 1 + 2. Purely additive:
 *  - new `branches` table, seeded with ONE default branch built from the existing salon settings
 *  - nullable users.branch_id and stylists.branch_id
 *  - every existing stylist, manager and stylist login is assigned to that default branch
 * Nothing is deleted or rewritten except filling the new branch_id columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('map_query')->nullable();
            $table->time('open_time')->default('09:00');
            $table->time('close_time')->default('19:00');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        $settings = DB::table('salon_settings')->first();
        $now = now();
        $branchId = DB::table('branches')->insertGetId([
            'name' => 'Main Branch',
            'address' => $settings->contact_address ?? null,
            'phone' => $settings->contact_phone ?? null,
            'map_query' => $settings->map_query ?? null,
            'open_time' => $settings->open_time ?? '09:00',
            'close_time' => $settings->close_time ?? '19:00',
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach (['users', 'stylists'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            });
        }

        DB::table('stylists')->update(['branch_id' => $branchId]);
        DB::table('users')->whereIn('role', ['manager', 'stylist'])->update(['branch_id' => $branchId]);
    }

    public function down(): void
    {
        foreach (['users', 'stylists'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('branch_id');
            });
        }
        Schema::dropIfExists('branches');
    }
};
