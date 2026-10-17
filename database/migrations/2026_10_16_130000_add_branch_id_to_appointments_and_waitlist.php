<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-branch, step 3. Purely additive: nullable branch_id on appointments and waitlist_entries,
 * filled from the stylist's branch (waitlist entries with no stylist go to the first branch).
 * No existing rows are deleted or otherwise changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['appointments', 'waitlist_entries'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            });
        }

        $default = DB::table('branches')->orderBy('id')->value('id');

        DB::table('appointments')->update([
            'branch_id' => DB::raw('(select stylists.branch_id from stylists where stylists.id = appointments.stylist_id)'),
        ]);
        DB::table('waitlist_entries')->whereNotNull('stylist_id')->update([
            'branch_id' => DB::raw('(select stylists.branch_id from stylists where stylists.id = waitlist_entries.stylist_id)'),
        ]);
        // Anything still empty (no stylist, or a stylist without a branch) goes to the first branch.
        DB::table('appointments')->whereNull('branch_id')->update(['branch_id' => $default]);
        DB::table('waitlist_entries')->whereNull('branch_id')->update(['branch_id' => $default]);

        Schema::table('appointments', function (Blueprint $table) {
            $table->index(['branch_id', 'scheduled_at'], 'appointments_branch_scheduled_index');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('appointments_branch_scheduled_index');
        });
        foreach (['appointments', 'waitlist_entries'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('branch_id');
            });
        }
    }
};
